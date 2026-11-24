<?php
/**
 * This file contains the declaration of the interface ILanguageManager for localization support.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\Interfaces;

/**
 * Interface for localization support.
 *
 * @author Oleg Schildt
 */
interface ILanguageManager extends IInitable
{
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
    public function init(array $parameters): void;

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
    public function loadDictionary(bool $force_reload = false): void;

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
    public function extendDictionary(array $dictionary): void;

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
    public function addLocalizationFile(string $localization_file): void;

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
    public function detectLanguage(): void;

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
     * @see ILanguageManager::getContext()
     *
     * @author Oleg Schildt
     */
    public function setContext(string $context): void;

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
     * @see ILanguageManager::setContext()
     *
     * @author Oleg Schildt
     */
    public function getContext(): string;

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
    public function getSupportedLanguages(): array;

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
     * @see ILanguageManager::getCurrentLanguage()
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
    public function setCurrentLanguage(string $language): void;

    /**
     * Returns the current language.
     *
     * Loads the dictionary if necessary. New contexts use the first configured language.
     *
     * @return string
     * Returns the normalized supported language tag.
     *
     * @see ILanguageManager::setCurrentLanguage()
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
    public function getCurrentLanguage(): string;

    /**
     * Returns the normalized fallback tag, or an empty string when disabled. If set and a translation
     * is missing on a language, the translation on this language will be used.
     *
     * @return string
     * Returns the normalized fallback tag, or an empty string when disabled.
     *
     * @author Oleg Schildt
     */
    public function getFallbackLanguage(): string;

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
    public function text(string $text_id, string $lng = "", string $default_text = ""): string;

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
    public function try_text(string $text_id, string $lng = ""): string;

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
    public function hasTranslation(string $text_id, string $lng = ""): bool;

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
     * @see ILanguageManager::getLanguageCode()
     * @see ILanguageManager::hasLanguageName()
     * @see ILanguageManager::getLanguageList()
     * @see ILanguageManager::getCountryName()
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
    public function getLanguageName(string $code, string $lng = ""): string;

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
     * @see ILanguageManager::getLanguageName()
     * @see ILanguageManager::hasLanguageName()
     * @see ILanguageManager::getLanguageList()
     * @see ILanguageManager::getCountryCode()
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
    public function getLanguageCode(string $lang_name): string;

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
     * @see ILanguageManager::getLanguageName()
     * @see ILanguageManager::getLanguageCode()
     * @see ILanguageManager::getLanguageList()
     * @see ILanguageManager::hasCountryName()
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
    public function hasLanguageName(string $code, string $lng = ""): bool;

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
     * @see ILanguageManager::getLanguageName()
     * @see ILanguageManager::getLanguageCode()
     * @see ILanguageManager::hasLanguageName()
     * @see ILanguageManager::getCountryList()
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
    public function getLanguageList(array &$language_list, string $lng = "", array $display_first = []): bool;

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
     * @see ILanguageManager::getCountryCode()
     * @see ILanguageManager::hasCountryName()
     * @see ILanguageManager::getCountryList()
     * @see ILanguageManager::getLanguageName()
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
    public function getCountryName(string $code, string $lng = ""): string;

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
     * @see ILanguageManager::getCountryName()
     * @see ILanguageManager::hasCountryName()
     * @see ILanguageManager::getCountryList()
     * @see ILanguageManager::getLanguageCode()
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
    public function getCountryCode(string $country_name): string;

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
     * @see ILanguageManager::getCountryName()
     * @see ILanguageManager::getCountryCode()
     * @see ILanguageManager::getCountryList()
     * @see ILanguageManager::hasLanguageName()
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
    public function hasCountryName(string $code, string $lng = ""): bool;

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
     * @see ILanguageManager::getCountryName()
     * @see ILanguageManager::getCountryCode()
     * @see ILanguageManager::hasCountryName()
     * @see ILanguageManager::getLanguageList()
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
    public function getCountryList(array &$country_list, string $lng = "", array $display_first = []): bool;
} // ILanguageManager
