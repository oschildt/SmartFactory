<?php
/**
 * This file contains the implementation of the interface ILanguageManager
 * in the class LanguageManager for working with localization of texts.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\Interfaces\ILanguageManager;

/**
 * Manages instance-local localization dictionaries, context selections and optional APCu caching.
 *
 * @author Oleg Schildt
 */
class LanguageManager implements ILanguageManager
{
    /**
     * Internal variable for storing the path with the localization files.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $localization_path = "";

    /**
     * Internal variable for storing the paths of the additional localization files.
     *
     * @var list<string>
     *
     * @author Oleg Schildt
     */
    protected array $additional_localization_files = [];

    /**
     * Internal variable for storing the fallback language.
     * If set and a translation is missing on a language, the translation on this language will be used.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $use_fallback_language = "";

    /**
     * Internal variable for storing the current context.
     *
     * @var string
     *
     * @see LanguageManager::getContext()
     *
     * @author Oleg Schildt
     */
    protected string $context = "default";

    /**
     * Internal variable for storing the state whether the dictionary is loaded or not.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $dictionary_loaded = false;

    /**
     * Internal variable for storing the state whether the APCU should be used.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $use_apcu = false;

    /**
     * Internal variable for storing the state whether the last selected language can be stored to cookie.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $use_cookie = false;

    /**
     * Internal variable for storing the cookie path.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $cookie_path = "/";

    /**
     * Internal variable for storing the state whether the E_USER_WARNING is triggered in the case of missing translations.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $warn_missing = true;

    /**
     * Internal array for storing the list of supported languages.
     *
     * @var array<string, string>
     *
     * @author Oleg Schildt
     */
    protected array $supported_languages = [];

    /**
     * Internal variable for storing the current language.
     *
     * @var array<string, string>
     *
     * @author Oleg Schildt
     */
    protected array $current_language = [];

    /**
     * Internal array for storing the list of language name translations.
     *
     * @var array<string, array<array-key, string>>
     *
     * @author Oleg Schildt
     */
    protected array $languages = [];

    /**
     * Internal array for storing the list of country name translations.
     *
     * @var array<string, array<array-key, string>>
     *
     * @author Oleg Schildt
     */
    protected array $countries = [];

    /**
     * Internal array for storing the list of text translations.
     *
     * @var array<string, array<array-key, string>>
     *
     * @author Oleg Schildt
     */
    protected array $texts = [];

    /**
     * Replaces the complete localization configuration and resets all instance state.
     *
     * @param array{localization_path: string, additional_localization_files?: string|list<string>, use_cookie?: bool, use_fallback_language?: string, cookie_path?: string, use_apcu?: bool, warn_missing?: bool} $parameters
     * The source directory, optional ordered extra text files, fallback tag and cookie/cache options.
     * The directory may omit its final separator. Missing options use defaults: cookies and APCu
     * are disabled, cookie_path is "/", fallback is empty and warn_missing is true.
     * False values and an empty fallback are respected. Additional files override individual languages.
     *
     * @return void
     * Commits validated options without reading documents and resets the context to "default".
     *
     * @throws \InvalidArgumentException
     * If paths or string options are invalid, the fallback tag is malformed, or requested APCu is unavailable.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void
    {
        $path = $parameters['localization_path'] ?? null;

        if (!is_string($path) || $path === '' || !is_dir($path)) {
            throw new \InvalidArgumentException("An existing 'localization_path' directory is required.");
        }

        $canonical = realpath($path);
        $files = $this->normalizeLocalizationFiles($parameters['additional_localization_files'] ?? []);
        $fallback = $parameters['use_fallback_language'] ?? '';
        $cookie_path = $parameters['cookie_path'] ?? '/';
        $use_apcu = (bool) ($parameters['use_apcu'] ?? false);

        if ($canonical === false || !is_string($fallback) || !is_string($cookie_path)) {
            throw new \InvalidArgumentException("Localization paths and the fallback language must be valid strings.");
        }

        $fallback = $this->normalizeLanguage($fallback);

        if ($fallback !== '' && !$this->isLanguageTag($fallback)) {
            throw new \InvalidArgumentException("The fallback language must be a language tag or an empty string.");
        }

        if ($use_apcu && (!function_exists('apcu_enabled') || !apcu_enabled())) {
            throw new \InvalidArgumentException("APCu is not enabled for this PHP execution mode.");
        }

        $this->localization_path = rtrim(str_replace('\\', '/', $canonical), '/') . '/';
        $this->additional_localization_files = $files;
        $this->use_fallback_language = $fallback;
        $this->cookie_path = $cookie_path;
        $this->use_cookie = (bool) ($parameters['use_cookie'] ?? false);
        $this->use_apcu = $use_apcu;
        $this->warn_missing = (bool) ($parameters['warn_missing'] ?? true);
        $this->dictionary_loaded = false;
        $this->context = 'default';
        $this->current_language = [];
        $this->supported_languages = [];
        $this->languages = [];
        $this->countries = [];
        $this->texts = [];
    } // init

    /**
     * Registers an additional text document and invalidates the loaded-source flag.
     *
     * @param string $localization_file
     * A file whose individual language values override earlier sources on the next load.
     *
     * @return void
     * Preserves the file order and selects a cache key specific to the new source list.
     *
     * @throws \InvalidArgumentException
     * If the path is empty or its parent directory does not exist.
     *
     * @author Oleg Schildt
     */
    public function addLocalizationFile(string $localization_file): void
    {
        $files = $this->normalizeLocalizationFiles([$localization_file]);
        $this->additional_localization_files[] = $files[0];
        $this->dictionary_loaded = false;
    } // addLocalizationFile

    /**
     * Loads a complete validated dictionary from JSON documents or APCu.
     * Each texts.json is followed by its optional sibling user_texts.json. Only
     * nonempty user translations replace master values; the string "0" is nonempty.
     *
     * @param bool $force_reload
     * Bypasses instance and APCu snapshots, replaces deleted entries and refreshes APCu after success.
     * Otherwise source changes require explicit reload or cache eviction. Instance-only additions
     * from extendDictionary() are discarded by source reload.
     *
     * @return void
     * Applies all documents together. Read or validation failures preserve the previous snapshot.
     *
     * @throws \LogicException
     * If init() has not selected a localization directory.
     *
     * @throws \RuntimeException
     * If a source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON or a document structure is invalid, or the supported-language list is empty.
     *
     * @author Oleg Schildt
     */
    public function loadDictionary(bool $force_reload = false): void
    {
        if (!$force_reload && $this->dictionary_loaded) {
            return;
        }

        if ($this->localization_path === '') {
            throw new \LogicException("The localization directory has not been initialized.");
        }

        $key = $this->cacheKey();

        if (!$force_reload && $this->use_apcu) {
            $cached = apcu_fetch($key, $success);

            if ($success && $this->isValidSnapshot($cached)) {
                $this->applySnapshot($cached);

                return;
            }
        }

        $config = $this->readDocument($this->localization_path . 'config.json');
        $configured = $config['interface_languages'] ?? null;

        if (!is_array($configured) || !array_is_list($configured) || $configured === []) {
            throw new \UnexpectedValueException("config.json must define a nonempty interface_languages list.");
        }

        $supported = [];

        foreach ($configured as $language) {
            if (!is_string($language) || !$this->isLanguageTag($this->normalizeLanguage($language))) {
                throw new \UnexpectedValueException("Each interface language must be a valid language tag.");
            }

            $language = $this->normalizeLanguage($language);
            $supported[$language] = $language;
        }

        $snapshot = ['supported_languages' => $supported, 'texts' => []];

        foreach (['languages', 'countries'] as $name) {
            $file = $this->localization_path . $name . '.json';
            $snapshot[$name] = $this->transposeTranslations($this->readDocument($file), $file);
        }

        $text_files = [$this->localization_path . 'texts.json', ...$this->additional_localization_files];

        foreach ($text_files as $file) {
            $additional = $this->transposeTranslations($this->readDocument($file), $file);

            foreach ($additional as $language => $translations) {
                $snapshot['texts'][$language] = array_replace($snapshot['texts'][$language] ?? [], $translations);
            }

            $user_file = dirname($file) . '/user_texts.json';

            if (basename($file) !== 'texts.json' || !file_exists($user_file)) {
                continue;
            }

            $overrides = $this->transposeTranslations($this->readDocument($user_file), $user_file);

            foreach ($overrides as $language => $translations) {
                $nonempty = array_filter($translations, static fn(string $value): bool => $value !== '');
                $snapshot['texts'][$language] = array_replace($snapshot['texts'][$language] ?? [], $nonempty);
            }
        }

        $this->applySnapshot($snapshot);

        if ($this->use_apcu) {
            // Failed cache replacement must not leave an older valid snapshot available.
            if ($force_reload) {
                apcu_delete($key);
            }

            apcu_store($key, $snapshot);
        }
    } // loadDictionary

    /**
     * Adds validated translations to this instance without changing shared APCu data.
     *
     * @param array<array-key, array<string, string>> $dictionary
     * Text identifiers mapped to language-to-string maps; numeric identifiers are preserved.
     * Individual language values override previous values. An explicit source reload discards additions.
     *
     * @return void
     * Validates the entire addition before changing the dictionary.
     *
     * @throws \LogicException
     * If init() has not selected a localization directory.
     *
     * @throws \RuntimeException
     * If a source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON or a document structure is invalid, or the supported-language list is empty.
     *
     * @author Oleg Schildt
     */
    public function extendDictionary(array $dictionary): void
    {
        $additional = $this->transposeTranslations($dictionary, 'extendDictionary()');

        $this->loadDictionary();

        foreach ($additional as $language => $translations) {
            $this->texts[$language] = array_replace($this->texts[$language] ?? [], $translations);
        }
    } // extendDictionary

    /**
     * Detects the current language from request parameters, headers and cookies.
     *
     * Priority:
     *
     * 1. explicitly set by the request parameter language.
     * 2. header 'Content-Language'.
     * 3. last language in the cookie.
     * 4. browser default language.
     * 5. the first one from the supported list.
     *
     * Some applications may consist of two parts: an administration
     * console and a public site. A usual example is a CMS system.
     *
     * For example, you are using administration console in English
     * and editing the public site in German and French.
     * When you open the public site for preview in German or French,
     * you want it to be open in the corresponding language, but
     * the administration console should remain in English.
     *
     * With the help of $context, you are able to maintain different
     * languages for different parts of your application.
     * If you do not need the $context, just do not specify it.
     *
     * Malformed request values are ignored. Accept-Language respects quality weights,
     * exclusions, wildcard ranges and header order. Tags are normalized and regional tags
     * may fall back to supported parent tags. A nonempty supported-language list is required.
     *
     * @return void
     * Returns without a value.
     *
     * @throws \LogicException
     * If init() has not selected a localization directory.
     *
     * @throws \RuntimeException
     * If a source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON or a document structure is invalid, or the supported-language list is empty.
     *
     * @throws \ValueError
     * If enabled cookie storage encounters an invalid cookie name or path.
     *
     * @author Oleg Schildt
     */
    public function detectLanguage(): void
    {
        $this->loadDictionary();

        $preferences = [
            $_REQUEST['language'] ?? null,
            get_header('Content-Language'),
            get_cookie($this->context . '_language'),
            $this->browserLanguage(get_header('Accept-Language')),
            reset($this->supported_languages)
        ];

        foreach ($preferences as $preference) {
            $language = $this->supportedLanguage($preference);

            if ($language !== null) {
                $this->setCurrentLanguage($language);

                return;
            }
        }
    } // detectLanguage

    /**
     * Sets the current context.
     *
     * Some applications may consist of two parts: an administration
     * console and a public site. A usual example is a CMS system.
     *
     * For example, you are using administration console in English
     * and editing the public site in German and French.
     * When you open the public site for preview in German or French,
     * you want it to be open in the corresponding language, but
     * the administration console should remain in English.
     *
     * With the help of $context, you are able to maintain different
     * languages for different parts of your application.
     * If you do not need the $context, just do not specify it.
     *
     * @param string $context
     * The name of the context.
     *
     * @return void
     *
     * @see LanguageManager::getContext()
     *
     * @author Oleg Schildt
     */
    public function setContext(string $context): void
    {
        $this->context = $context;
    } // setContext

    /**
     * Returns the current context.
     *
     * Some applications may consist of two parts: an administration
     * console and a public site. A usual example is a CMS system.
     *
     * For example, you are using administration console in English
     * and editing the public site in German and French.
     * When you open the public site for preview in German or French,
     * you want it to be open in the corresponding language, but
     * the administration console should remain in English.
     *
     * With the help of $context, you are able to maintain different
     * languages for different parts of your application.
     * If you do not need the $context, just do not specify it.
     *
     * @return string
     * Returns the current context.
     *
     * @see LanguageManager::setContext()
     *
     * @author Oleg Schildt
     */
    public function getContext(): string
    {
        return $this->context;
    } // getContext

    /**
     * Returns normalized tags mapped to themselves in their configured order.
     *
     * @return array<string, string>
     * Returns normalized tags mapped to themselves in their configured order.
     *
     * @throws \LogicException
     * If init() has not selected a localization directory.
     *
     * @throws \RuntimeException
     * If a source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON or a document structure is invalid, or the supported-language list is empty.
     *
     * @author Oleg Schildt
     */
    public function getSupportedLanguages(): array
    {
        $this->loadDictionary();

        return $this->supported_languages;
    } // getSupportedLanguages

    /**
     * Sets the current language.
     *
     * Unsupported tags leave the selection unchanged. Matching ignores case and may
     * fall back from a regional tag to a supported parent tag.
     *
     * @param string $language
     * The language tag to select.
     *
     * @return void
     * Returns without a value.
     *
     * @see LanguageManager::getCurrentLanguage()
     *
     * @throws \LogicException
     * If init() has not selected a localization directory.
     *
     * @throws \RuntimeException
     * If a source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON or a document structure is invalid, or the supported-language list is empty.
     *
     * @throws \ValueError
     * If enabled cookie storage encounters an invalid cookie name or path.
     *
     * @author Oleg Schildt
     */
    public function setCurrentLanguage(string $language): void
    {
        $this->loadDictionary();

        $language = $this->supportedLanguage($language);

        if ($language === null) {
            return;
        }

        $this->current_language[$this->context] = $language;

        if ($this->use_cookie) {
            set_cookie($this->context . '_language', $language, time() + 365 * 24 * 3600, [
                'samesite' => 'strict', 'path' => $this->cookie_path
            ]);
        }
    } // setCurrentLanguage

    /**
     * Returns the normalized fallback tag, or an empty string when disabled. If set and a translation
     * is missing on a language, the translation on this language will be used.
     *
     * @return string
     * Returns the normalized fallback tag, or an empty string when disabled.
     *
     * @author Oleg Schildt
     */
    public function getFallbackLanguage(): string
    {
        return $this->use_fallback_language;
    } // getFallbackLanguage

    /**
     * Returns the current language.
     *
     * Loads the dictionary if necessary. New contexts use the first configured language.
     *
     * @return string
     * Returns the normalized supported language tag.
     *
     * @see LanguageManager::setCurrentLanguage()
     *
     * @throws \LogicException
     * If init() has not selected a localization directory.
     *
     * @throws \RuntimeException
     * If a source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON or a document structure is invalid, or the supported-language list is empty.
     *
     * @author Oleg Schildt
     */
    public function getCurrentLanguage(): string
    {
        $this->loadDictionary();

        return $this->current_language[$this->context] ??= reset($this->supported_languages);
    } // getCurrentLanguage

    /**
     * Provides the text translation for the text ID for the given language.
     *
     * The string "0" is a valid translation or default; an empty translation is missing.
     * Resolution order is the requested translation, nonempty default, fallback, then ID.
     * Configured warnings concern the missing requested translation, even when fallback exists.
     *
     * @param string $text_id
     * Text ID
     *
     * @param string $lng
     * The language. If it is not specified,
     * the default language is used.
     *
     * @param string $default_text
     * The default text to be used if there is no translation.
     *
     * @return string
     * Returns the requested translation, nonempty default, fallback translation or text ID.
     *
     * @throws \LogicException
     * If init() has not selected a localization directory.
     *
     * @throws \RuntimeException
     * If a source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON or a document structure is invalid, or the supported-language list is empty.
     *
     * @author Oleg Schildt
     */
    public function text(string $text_id, string $lng = "", string $default_text = ""): string
    {
        $lng = $lng === '' ? $this->getCurrentLanguage() : $this->normalizeLanguage($lng);

        if ($this->hasTranslation($text_id, $lng)) {
            return $this->texts[$lng][$text_id];
        }

        if ($this->warn_missing) {
            trigger_error("No translation for the text '$text_id' in the language [$lng]!", E_USER_WARNING);
        }

        if ($default_text !== '') {
            return $default_text;
        }

        return $this->try_text($text_id, $lng);
    } // text

    /**
     * Provides the text translation for the text ID for the given language if the translation exists.
     * Otherwise, it tries the configured fallback and returns the text ID if that is also missing.
     *
     * Uses this instance without accessing the global factory. The configured fallback
     * is tried before returning the identifier. Empty translations are considered missing.
     *
     * @param string $text_id
     * Text ID
     *
     * @param string $lng
     * The language. If it is not specified,
     * the default language is used.
     *
     * @return string
     * Returns the requested translation, fallback translation or text ID without missing-text warnings.
     *
     * @throws \LogicException
     * If init() has not selected a localization directory.
     *
     * @throws \RuntimeException
     * If a source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON or a document structure is invalid, or the supported-language list is empty.
     *
     * @author Oleg Schildt
     */
    public function try_text(string $text_id, string $lng = ""): string
    {
        $lng = $lng === '' ? $this->getCurrentLanguage() : $this->normalizeLanguage($lng);

        if ($this->hasTranslation($text_id, $lng)) {
            return $this->texts[$lng][$text_id];
        }

        if ($this->use_fallback_language !== '' && $this->hasTranslation($text_id, $this->use_fallback_language)) {
            return $this->texts[$this->use_fallback_language][$text_id];
        }

        return $text_id;
    } // try_text

    /**
     * Checks whether the text translation for the text ID for the given language exists.
     *
     * Checks only the requested language, without fallback. The string "0" exists;
     * an empty translation does not.
     *
     * @param string $text_id
     * Text ID
     *
     * @param string $lng
     * The language. If it is not specified,
     * the default language is used.
     *
     * @return bool
     * Returns true if the translation exists, otherwise false.
     *
     * @throws \LogicException
     * If init() has not selected a localization directory.
     *
     * @throws \RuntimeException
     * If a source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON or a document structure is invalid, or the supported-language list is empty.
     *
     * @author Oleg Schildt
     */
    public function hasTranslation(string $text_id, string $lng = ""): bool
    {
        $this->loadDictionary();

        $lng = $lng === '' ? $this->getCurrentLanguage() : $this->normalizeLanguage($lng);

        return isset($this->texts[$lng][$text_id]) && $this->texts[$lng][$text_id] !== '';
    } // hasTranslation

    /**
     * Provides the text translation for the language name by the code
     * for the given language.
     *
     * @param string $code
     * Language ISO code (lowercase, e.g. en, de, fr).
     *
     * @param string $lng
     * The language. If it is not specified,
     * the default language is used.
     *
     * @return string
     * Returns the translation text for the language name or the $code if no translation
     * is found.
     *
     * @see LanguageManager::getLanguageCode()
     * @see LanguageManager::hasLanguageName()
     * @see LanguageManager::getLanguageList()
     * @see LanguageManager::getCountryName()
     *
     * @throws \LogicException
     * If init() has not selected a localization directory.
     *
     * @throws \RuntimeException
     * If a source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON or a document structure is invalid, or the supported-language list is empty.
     *
     * @author Oleg Schildt
     */
    public function getLanguageName(string $code, string $lng = ""): string
    {
        $lng = $lng === '' ? $this->getCurrentLanguage() : $this->normalizeLanguage($lng);

        if ($this->hasLanguageName($code, $lng)) {
            return $this->languages[$lng][$code];
        }

        if ($this->warn_missing) {
            trigger_error("No translation for the language name [$code] in the language [$lng]!", E_USER_WARNING);
        }

        return $code;
    } // getLanguageName

    /**
     * Tries to find the language code by the given name.
     *
     * Name comparison is Unicode case-insensitive and searches supported languages
     * in their configured order.
     *
     * @param string $lang_name
     * The name of the language in any supported language.
     *
     * @return string
     * Returns the language code if it could be found, otherwise an empty string.
     *
     * @see LanguageManager::getLanguageName()
     * @see LanguageManager::hasLanguageName()
     * @see LanguageManager::getLanguageList()
     * @see LanguageManager::getCountryCode()
     *
     * @throws \LogicException
     * If init() has not selected a localization directory.
     *
     * @throws \RuntimeException
     * If a source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON or a document structure is invalid, or the supported-language list is empty.
     *
     * @author Oleg Schildt
     */
    public function getLanguageCode(string $lang_name): string
    {
        $this->loadDictionary();

        $needle = mb_strtolower($lang_name, 'UTF-8');

        if ($needle === '') {
            return '';
        }

        foreach ($this->supported_languages as $language) {
            foreach ($this->languages[$language] ?? [] as $code => $translation) {
                if (mb_strtolower($translation, 'UTF-8') === $needle) {
                    return (string) $code;
                }
            }
        }

        return '';
    } // getLanguageCode

    /**
     * Checks whether a nonempty localized language name exists.
     *
     * @param string $code
     * Language ISO code (lowercase, e.g. en, de, fr).
     *
     * @param string $lng
     * The language. If it is not specified,
     * the default language is used.
     *
     * @return bool
     * Returns true if a nonempty language name exists, otherwise false.
     *
     * @see LanguageManager::getLanguageName()
     * @see LanguageManager::getLanguageCode()
     * @see LanguageManager::getLanguageList()
     * @see LanguageManager::hasCountryName()
     *
     * @throws \LogicException
     * If init() has not selected a localization directory.
     *
     * @throws \RuntimeException
     * If a source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON or a document structure is invalid, or the supported-language list is empty.
     *
     * @author Oleg Schildt
     */
    public function hasLanguageName(string $code, string $lng = ""): bool
    {
        $this->loadDictionary();

        $lng = $lng === '' ? $this->getCurrentLanguage() : $this->normalizeLanguage($lng);

        return isset($this->languages[$lng][$code]) && $this->languages[$lng][$code] !== '';
    } // hasLanguageName

    /**
     * Provides the list of languages for the given language in the form "code" => "translation".
     *
     * Unknown preferred codes are ignored. Sorts a copy using the process locale,
     * leaving dictionary order unchanged. The output is empty when no names are available.
     *
     * @param array<array-key, string> &$language_list
     * Target array where the language list should be loaded.
     *
     * @param string $lng
     * The language. If it is not specified,
     * the default language is used.
     *
     * @param list<string> $display_first
     * List of the language codes to be displayed first in the order, they appear in the list.
     *
     * @return bool
     * Returns true if the language list is successfully retrieved, otherwise false.
     *
     * @see LanguageManager::getLanguageName()
     * @see LanguageManager::getLanguageCode()
     * @see LanguageManager::hasLanguageName()
     * @see LanguageManager::getCountryList()
     *
     * @throws \LogicException
     * If init() has not selected a localization directory.
     *
     * @throws \RuntimeException
     * If a source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON or a document structure is invalid, or the supported-language list is empty.
     *
     * @author Oleg Schildt
     */
    public function getLanguageList(array &$language_list, string $lng = "", array $display_first = []): bool
    {
        $this->loadDictionary();

        $lng = $lng === '' ? $this->getCurrentLanguage() : $this->normalizeLanguage($lng);
        $language_list = $this->orderedNames($this->languages[$lng] ?? [], $display_first);

        return $language_list !== [];
    } // getLanguageList

    /**
     * Provides the text translation for the country name by the code
     * for the given language.
     *
     * @param string $code
     * Country ISO code (uppercase, e.g. US, DE, FR).
     *
     * @param string $lng
     * The language. If it is not specified,
     * the default language is used.
     *
     * @return string
     * Returns the translation text for the country name or the $code if no translation
     * is found.
     *
     * @see LanguageManager::getCountryCode()
     * @see LanguageManager::hasCountryName()
     * @see LanguageManager::getCountryList()
     * @see LanguageManager::getLanguageName()
     *
     * @throws \LogicException
     * If init() has not selected a localization directory.
     *
     * @throws \RuntimeException
     * If a source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON or a document structure is invalid, or the supported-language list is empty.
     *
     * @author Oleg Schildt
     */
    public function getCountryName(string $code, string $lng = ""): string
    {
        $lng = $lng === '' ? $this->getCurrentLanguage() : $this->normalizeLanguage($lng);

        if ($this->hasCountryName($code, $lng)) {
            return $this->countries[$lng][$code];
        }

        if ($this->warn_missing) {
            trigger_error("No translation for the country name [$code] in the language [$lng]!", E_USER_WARNING);
        }

        return $code;
    } // getCountryName

    /**
     * Tries to find the country code by the given name.
     *
     * Name comparison is Unicode case-insensitive and searches supported languages
     * in their configured order.
     *
     * @param string $country_name
     * The name of the country in any supported language.
     *
     * @return string
     * Returns the country code if it could be found, otherwise an empty string.
     *
     * @see LanguageManager::getCountryName()
     * @see LanguageManager::hasCountryName()
     * @see LanguageManager::getCountryList()
     * @see LanguageManager::getLanguageCode()
     *
     * @throws \LogicException
     * If init() has not selected a localization directory.
     *
     * @throws \RuntimeException
     * If a source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON or a document structure is invalid, or the supported-language list is empty.
     *
     * @author Oleg Schildt
     */
    public function getCountryCode(string $country_name): string
    {
        $this->loadDictionary();

        $needle = mb_strtolower($country_name, 'UTF-8');

        if ($needle === '') {
            return '';
        }

        foreach ($this->supported_languages as $language) {
            foreach ($this->countries[$language] ?? [] as $code => $translation) {
                if (mb_strtolower($translation, 'UTF-8') === $needle) {
                    return (string) $code;
                }
            }
        }

        return '';
    } // getCountryCode

    /**
     * Checks whether a nonempty localized country name exists.
     *
     * @param string $code
     * Country ISO code (uppercase, e.g. US, DE, FR).
     *
     * @param string $lng
     * The language. If it is not specified,
     * the default language is used.
     *
     * @return bool
     * Returns true if a nonempty country name exists, otherwise false.
     *
     * @see LanguageManager::getCountryName()
     * @see LanguageManager::getCountryCode()
     * @see LanguageManager::getCountryList()
     * @see LanguageManager::hasLanguageName()
     *
     * @throws \LogicException
     * If init() has not selected a localization directory.
     *
     * @throws \RuntimeException
     * If a source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON or a document structure is invalid, or the supported-language list is empty.
     *
     * @author Oleg Schildt
     */
    public function hasCountryName(string $code, string $lng = ""): bool
    {
        $this->loadDictionary();

        $lng = $lng === '' ? $this->getCurrentLanguage() : $this->normalizeLanguage($lng);

        return isset($this->countries[$lng][$code]) && $this->countries[$lng][$code] !== '';
    } // hasCountryName

    /**
     * Provides the list of countries for the given language in the form "code" => "translation".
     *
     * Unknown preferred codes are ignored. Sorts a copy using the process locale,
     * leaving dictionary order unchanged. The output is empty when no names are available.
     *
     * @param array<array-key, string> &$country_list
     * Target array where the country list should be loaded.
     *
     * @param string $lng
     * The language. If it is not specified,
     * the default language is used.
     *
     * @param list<string> $display_first
     * List of the country codes to be displayed first in the order, they appear in the list.
     *
     * @return bool
     * Returns true if the country list is successfully retrieved, otherwise false.
     *
     * @see LanguageManager::getCountryName()
     * @see LanguageManager::getCountryCode()
     * @see LanguageManager::hasCountryName()
     * @see LanguageManager::getLanguageList()
     *
     * @throws \LogicException
     * If init() has not selected a localization directory.
     *
     * @throws \RuntimeException
     * If a source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON or a document structure is invalid, or the supported-language list is empty.
     *
     * @author Oleg Schildt
     */
    public function getCountryList(array &$country_list, string $lng = "", array $display_first = []): bool
    {
        $this->loadDictionary();

        $lng = $lng === '' ? $this->getCurrentLanguage() : $this->normalizeLanguage($lng);
        $country_list = $this->orderedNames($this->countries[$lng] ?? [], $display_first);

        return $country_list !== [];
    } // getCountryList
    /**
     * Canonicalizes ordered additional paths without requiring the files to exist yet.
     *
     * @param mixed $files
     * A single path or a list of paths with existing parent directories.
     *
     * @return list<string>
     * Returns absolute paths in their override order.
     *
     * @throws \InvalidArgumentException
     * If the input is not a path/list, a path is empty, or its parent directory is invalid.
     *
     * @author Oleg Schildt
     */
    protected function normalizeLocalizationFiles(mixed $files): array
    {
        $files = is_string($files) ? [$files] : $files;

        if (!is_array($files) || !array_is_list($files)) {
            throw new \InvalidArgumentException("Additional localization files must be a path or a list of paths.");
        }

        $result = [];

        foreach ($files as $file) {
            if (!is_string($file) || $file === '' || !is_dir(dirname($file))) {
                throw new \InvalidArgumentException(
                    "Each localization file requires a nonempty path and an existing parent directory."
                );
            }

            $path = realpath($file) ?: realpath(dirname($file)) . DIRECTORY_SEPARATOR . basename($file);
            $result[] = str_replace('\\', '/', $path);
        }

        return $result;
    } // normalizeLocalizationFiles

    /**
     * Normalizes tag case, surrounding whitespace and underscore separators.
     *
     * @param string $language
     * Language tag to normalize; an empty tag remains empty.
     *
     * @return string
     * Returns a lowercase tag with hyphen separators.
     *
     * @author Oleg Schildt
     */
    protected function normalizeLanguage(string $language): string
    {
        return strtolower(str_replace('_', '-', trim($language)));
    } // normalizeLanguage

    /**
     * Checks the normalized language-tag syntax accepted by source documents.
     *
     * @param string $language
     * Lowercase tag to validate.
     *
     * @return bool
     * True for an alphabetic primary subtag and optional alphanumeric subtags of 1 to 8 characters.
     *
     * @author Oleg Schildt
     */
    protected function isLanguageTag(string $language): bool
    {
        return preg_match('/^[a-z]{1,8}(?:-[a-z0-9]{1,8})*$/D', $language) === 1;
    } // isLanguageTag

    /**
     * Resolves a request value to a supported tag, trying regional parent tags in order.
     *
     * @param mixed $language
     * A string tag; malformed or nonstring values are ignored.
     *
     * @return string|null
     * Returns the matching supported tag, or null when none matches.
     *
     * @author Oleg Schildt
     */
    protected function supportedLanguage(mixed $language): ?string
    {
        if (!is_string($language)) {
            return null;
        }

        $language = $this->normalizeLanguage($language);

        if (!$this->isLanguageTag($language)) {
            return null;
        }

        while (true) {
            if (isset($this->supported_languages[$language])) {
                return $language;
            }

            $separator = strrpos($language, '-');

            if ($separator === false) {
                return null;
            }

            $language = substr($language, 0, $separator);
        }
    } // supportedLanguage

    /**
     * Selects a browser language from weighted ranges and explicit exclusions.
     *
     * @param string $header
     * Accept-Language value; malformed ranges and quality values are ignored.
     *
     * @return string|null
     * Returns the best positive-quality supported tag, or null when none matches.
     * Specific ranges override parent/wildcard ranges; header order breaks quality ties.
     *
     * @author Oleg Schildt
     */
    protected function browserLanguage(string $header): ?string
    {
        $ranges = [];

        foreach (explode(',', $header) as $order => $entry) {
            $parts = explode(';', $entry);
            $range = $this->normalizeLanguage(array_shift($parts));
            $quality = 1.0;

            if ($range !== '*' && !$this->isLanguageTag($range)) {
                continue;
            }

            foreach ($parts as $part) {
                if (preg_match('/^\s*q\s*=\s*(0(?:\.\d{0,3})?|1(?:\.0{0,3})?)\s*$/iD', $part, $match) !== 1) {
                    continue 2;
                }

                $quality = (float) $match[1];
            }

            $ranges[] = ['range' => $range, 'quality' => $quality, 'order' => $order];
        }

        $best = null;
        $best_quality = 0.0;
        $best_order = PHP_INT_MAX;

        foreach ($this->supported_languages as $language) {
            $match = null;
            $specificity = -1;

            foreach ($ranges as $range) {
                $tag = $range['range'];
                $matches = $tag === '*' || $language === $tag || str_starts_with($language, $tag . '-')
                    || $this->supportedLanguage($tag) === $language;
                $length = $tag === '*' ? 0 : substr_count($tag, '-') + 1;

                // Equally specific regional fallbacks compete by quality, not tag length.
                if (
                    $matches
                    && ($length > $specificity || ($length === $specificity && $range['quality'] > $match['quality']))
                ) {
                    $match = $range;
                    $specificity = $length;
                }
            }

            if (
                $match !== null
                && ($match['quality'] > $best_quality
                    || ($match['quality'] > 0 && $match['quality'] === $best_quality && $match['order'] < $best_order))
            ) {
                $best = $language;
                $best_quality = $match['quality'];
                $best_order = $match['order'];
            }
        }

        return $best;
    } // browserLanguage

    /**
     * Reads a JSON document, preserving a JSON parsing exception as the cause.
     *
     * @param string $file
     * Local source document path.
     *
     * @return array<array-key, mixed>
     * Returns decoded data for document-specific validation.
     *
     * @throws \RuntimeException
     * If the source document cannot be read.
     *
     * @throws \UnexpectedValueException
     * If JSON is malformed or its root is a scalar or null.
     *
     * @author Oleg Schildt
     */
    protected function readDocument(string $file): array
    {
        $json = @file_get_contents($file);

        if ($json === false) {
            throw new \RuntimeException("Translation file cannot be read: " . $file);
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $exception) {
            throw new \UnexpectedValueException("Translation file contains invalid JSON: " . $file, 0, $exception);
        }

        if (!is_array($data)) {
            throw new \UnexpectedValueException("Translation JSON must contain an object or array: " . $file);
        }

        return $data;
    } // readDocument

    /**
     * Validates and transposes identifier-to-language maps without changing identifiers.
     *
     * @param array<array-key, mixed> $dictionary
     * Identifiers mapped to language-to-string maps.
     *
     * @param string $source
     * Document path or operation included in validation errors.
     *
     * @return array<string, array<array-key, string>>
     * Returns normalized tags mapped to identifier-to-string maps.
     *
     * @throws \UnexpectedValueException
     * If an entry is not a map, a language tag is invalid, or a translation is not a string.
     *
     * @author Oleg Schildt
     */
    protected function transposeTranslations(array $dictionary, string $source): array
    {
        $result = [];

        foreach ($dictionary as $identifier => $translations) {
            if (!is_array($translations)) {
                throw new \UnexpectedValueException("Translations must be language-to-string maps: " . $source);
            }

            foreach ($translations as $language => $translation) {
                if (
                    !is_string($language)
                    || !$this->isLanguageTag($this->normalizeLanguage($language))
                    || !is_string($translation)
                ) {
                    throw new \UnexpectedValueException("Invalid language tag or nonstring translation: " . $source);
                }

                $result[$this->normalizeLanguage($language)][$identifier] = $translation;
            }
        }

        return $result;
    } // transposeTranslations

    /**
     * Derives a versioned key from the canonical directory and ordered additional files.
     *
     * @return string
     * Returns a key independent of modification times; explicit reload refreshes data.
     *
     * @author Oleg Schildt
     */
    protected function cacheKey(): string
    {
        return 'language_dictionary:v3:' . hash('sha256', serialize([
            $this->localization_path, $this->additional_localization_files
        ]));
    } // cacheKey

    /**
     * Checks an entire shared snapshot before changing any instance state.
     *
     * @param mixed $snapshot
     * Untrusted APCu value.
     *
     * @return bool
     * True for a nonempty supported-language map and three valid language-indexed tables.
     * Empty translation tables remain valid and cacheable.
     *
     * @author Oleg Schildt
     */
    protected function isValidSnapshot(mixed $snapshot): bool
    {
        if (
            !is_array($snapshot)
            || !isset($snapshot['supported_languages'])
            || !is_array($snapshot['supported_languages'])
            || $snapshot['supported_languages'] === []
        ) {
            return false;
        }

        foreach ($snapshot['supported_languages'] as $code => $language) {
            if (!is_string($language) || $code !== $language || !$this->isLanguageTag($language)) {
                return false;
            }
        }

        foreach (['languages', 'countries', 'texts'] as $name) {
            if (!isset($snapshot[$name]) || !is_array($snapshot[$name])) {
                return false;
            }

            foreach ($snapshot[$name] as $language => $translations) {
                if (!is_string($language) || !$this->isLanguageTag($language) || !is_array($translations)) {
                    return false;
                }

                foreach ($translations as $translation) {
                    if (!is_string($translation)) {
                        return false;
                    }
                }
            }
        }

        return true;
    } // isValidSnapshot

    /**
     * Replaces all dictionary tables and drops selections no longer supported.
     *
     * @param array{supported_languages: array<string, string>, languages: array<string, array<array-key, string>>, countries: array<string, array<array-key, string>>, texts: array<string, array<array-key, string>>} $snapshot
     * A complete validated dictionary from source documents or APCu.
     *
     * @return void
     * Marks the source configuration loaded and retains valid context selections.
     *
     * @author Oleg Schildt
     */
    protected function applySnapshot(array $snapshot): void
    {
        $this->supported_languages = $snapshot['supported_languages'];
        $this->languages = $snapshot['languages'];
        $this->countries = $snapshot['countries'];
        $this->texts = $snapshot['texts'];
        $this->dictionary_loaded = true;

        foreach ($this->current_language as $context => $language) {
            if (!isset($this->supported_languages[$language])) {
                unset($this->current_language[$context]);
            }
        }
    } // applySnapshot

    /**
     * Sorts a copy of localized names and prepends only existing preferred codes.
     *
     * @param array<array-key, string> $names
     * Names to sort using the process locale.
     *
     * @param list<string> $display_first
     * Preferred codes; unknown codes and nonstring entries are ignored.
     *
     * @return array<array-key, string>
     * Returns preferred entries followed by the remaining names without duplicate codes.
     *
     * @author Oleg Schildt
     */
    protected function orderedNames(array $names, array $display_first): array
    {
        $result = [];

        asort($names, SORT_LOCALE_STRING);

        foreach ($display_first as $code) {
            if (is_string($code) && array_key_exists($code, $names)) {
                $result[$code] = $names[$code];
            }
        }

        return $result + $names;
    } // orderedNames
} // LanguageManager
