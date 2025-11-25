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
     * Initializes the language manager with parameters.
     *
     * @param array $parameters
     * The parameters may vary for each language manager.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any system errors.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void;
    
    /**
     * This is function for loading the translations from the source JSON file.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw the following exceptions in the case of any errors:
     *
     * - if the translation file is invalid.
     *
     * @author Oleg Schildt
     */
    public function loadDictionary(): void;

    /**
     * Adds additional translations to the dictionary.
     *
     * @param array $dictionary
     * The array with additional translations.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function extendDictionary(array $dictionary): void;

    /**
     * Adds additional localization files to the dictionary.
     *
     * @param string $localization_file
     * The path of the additional localization file.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function addLocalizationFile(string $localization_file): void;

    /**
     * This function should detect the current language based on cookies, browser languages etc.
     *
     * Some applications may consist of two parts - administration
     * console and public site. A usual example is a CMS system.
     *
     * For example, you are using administration console in English
     * and editing the public site for German and French.
     * When you open the public site for preview in German or French,
     * you want it to be open in the corresponding language, but
     * the administration console should remain in English.
     *
     * With the help of $context, you are able to maintain different
     * languages for different parts of your application.
     * If you do not need the $context, just do not specify it.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function detectLanguage(): void;
    
    /**
     * Sets the current context.
     *
     * Some applications may consist of two parts - administration
     * console and public site. A usual example is a CMS system.
     *
     * For example, you are using administration console in English
     * and editing the public site for German and French.
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
    public function setContext(string $context): void;

    /**
     * Returns the current context.
     *
     * Some applications may consist of two parts - administration
     * console and public site. A usual example is a CMS system.
     *
     * For example, you are using administration console in English
     * and editing the public site for German and French.
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
     * @author Oleg Schildt
     */
    public function getContext(): string;
    
    /**
     * Returns the list of supported languages.
     *
     * @return array
     * Returns the list of supported languages.
     *
     * @author Oleg Schildt
     */
    public function getSupportedLanguages(): array;
    
    /**
     * Sets the current language.
     *
     * @param string $language
     * The language ISO code to be set.
     *
     * @return void
     *
     * @see ILanguageManager::getCurrentLanguage()
     *
     * @author Oleg Schildt
     */
    public function setCurrentLanguage(string $language): void;
    
    /**
     * Returns the current language.
     *
     * @return string
     * Returns the current language ISO code.
     *
     * @see ILanguageManager::setCurrentLanguage()
     *
     * @author Oleg Schildt
     */
    public function getCurrentLanguage(): string;
    
    /**
     * Returns the fallback language. If set and a translation
     * is missing on a language, the translation on this language will be used.
     *
     * @return string
     * Returns the fallback language.
     *
     * @author Oleg Schildt
     */
    public function getFallbackLanguage(): string;

    /**
     * Provides the text translation for the text ID for the given language.
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
     * Returns the translation text or the $default_text/$text_id if no translation
     * is found.
     *
     * @author Oleg Schildt
     */
    public function text(string $text_id, string $lng = "", string $default_text = ""): string;
    
    /**
     * Provides the text translation for the text ID for the given language if the translation exists.
     * Otherwise, it returns the text ID and emits no warning.
     *
     * @param string $text_id
     * Text ID
     *
     * @param string $lng
     * The language. If it is not specified,
     * the default language is used.
     *
     * @return string
     * Returns the translation text or the $default_text/$text_id if no translation
     * is found.
     *
     * @author Oleg Schildt
     */
    public function try_text(string $text_id, string $lng = ""): string;

    /**
     * Checks whether the text translation for the text ID for the given language exists.
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
     * @see ILanguageManager::validateLanguageCode()
     * @see ILanguageManager::getLanguageList()
     * @see ILanguageManager::getCountryName()
     *
     * @author Oleg Schildt
     */
    public function getLanguageName(string $code, string $lng = ""): string;
    
    /**
     * Tries to find the language code by the given name.
     *
     * @param string $lang_name
     * The name of the language in any supported language.
     *
     * @return string
     * Returns the language code if it could be found, otherwise an empty string.
     *
     * @see ILanguageManager::getLanguageName()
     * @see ILanguageManager::validateLanguageCode()
     * @see ILanguageManager::getLanguageList()
     * @see ILanguageManager::getCountryCode()
     *
     * @author Oleg Schildt
     */
    public function getLanguageCode(string $lang_name): string;
    
    /**
     * Checks whether the language code is valid (has translation).
     *
     * @param string $code
     * Language ISO code (lowercase, e.g. en, de, fr).
     *
     * @param string $lng
     * The language. If it is not specified,
     * the default language is used.
     *
     * @return bool
     * Returns true if the language code is valid (has translation), otherwise false.
     *
     * @see ILanguageManager::getLanguageName()
     * @see ILanguageManager::getLanguageCode()
     * @see ILanguageManager::getLanguageList()
     * @see ILanguageManager::validateCountryCode()
     *
     * @author Oleg Schildt
     */
    public function validateLanguageCode(string $code, string $lng = ""): bool;
    
    /**
     * Provides the list of languages for the given language in the form "code" => "translation".
     *
     * @param array &$language_list
     * Target array where the language list should be loaded.
     *
     * @param string $lng
     * The language. If it is not specified,
     * the default language is used.
     *
     * @param array $display_first
     * List of the language codes to be displayed first in the order, they appear in the list.
     *
     * @return bool
     * Returns true if the language list is successfully retrieved, otherwise false.
     *
     * @see ILanguageManager::getLanguageName()
     * @see ILanguageManager::getLanguageCode()
     * @see ILanguageManager::validateLanguageCode()
     * @see ILanguageManager::getCountryList()
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
     * @see ILanguageManager::validateCountryCode()
     * @see ILanguageManager::getCountryList()
     * @see ILanguageManager::getLanguageName()
     *
     * @author Oleg Schildt
     */
    public function getCountryName(string $code, string $lng = ""): string;
    
    /**
     * Tries to find the country code by the given name.
     *
     * @param string $country_name
     * The name of the country in any supported language.
     *
     * @return string
     * Returns the country code if it could be found, otherwise an empty string.
     *
     * @see ILanguageManager::getCountryName()
     * @see ILanguageManager::validateCountryCode()
     * @see ILanguageManager::getCountryList()
     * @see ILanguageManager::getLanguageCode()
     *
     * @author Oleg Schildt
     */
    public function getCountryCode(string $country_name): string;
    
    /**
     * Checks whether the country code is valid (has translation).
     *
     * @param string $code
     * Country ISO code (uppercase, e.g. US, DE, FR).
     *
     * @param string $lng
     * The language. If it is not specified,
     * the default language is used.
     *
     * @return bool
     * Returns true if the country code is valid (has translation), otherwise false.
     *
     * @see ILanguageManager::getCountryName()
     * @see ILanguageManager::getCountryCode()
     * @see ILanguageManager::getCountryList()
     * @see ILanguageManager::validateLanguageCode()
     *
     * @author Oleg Schildt
     */
    public function validateCountryCode(string $code, string $lng = ""): bool;
    
    /**
     * Provides the list of countries for the given language in the form "code" => "translation".
     *
     * @param array &$country_list
     * Target array where the country list should be loaded.
     *
     * @param string $lng
     * The language. If it is not specified,
     * the default language is used.
     *
     * @param array $display_first
     * List of the country codes to be displayed first in the order, they appear in the list.
     *
     * @return bool
     * Returns true if the country list is successfully retrieved, otherwise false.
     *
     * @see ILanguageManager::getCountryName()
     * @see ILanguageManager::getCountryCode()
     * @see ILanguageManager::validateCountryCode()
     * @see ILanguageManager::getLanguageList()
     *
     * @author Oleg Schildt
     */
    public function getCountryList(array &$country_list, string $lng = "", array $display_first = []): bool;
} // ILanguageManager
