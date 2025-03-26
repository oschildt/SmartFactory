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
    public function init($parameters);
    
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
    public function loadDictionary();

    /**
     * Adds additional translations to the dictionary.
     *
     * @param array &$dictionary
     * The array with additional translations.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function extendDictionary(&$dictionary);

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
    public function addLocalizationFile($localization_file);

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
    public function detectLanguage();
    
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
    public function setContext($context);

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
    public function getContext();
    
    /**
     * Returns the list of supported languages.
     *
     * @return array
     * Returns the list of supported languages.
     *
     * @author Oleg Schildt
     */
    public function getSupportedLanguages();
    
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
    public function setCurrentLanguage($language);
    
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
    public function getCurrentLanguage();
    
    /**
     * Returns the fallback language. If set and a tranlation 
     * is missing on a langauge, the translation on this language will be used.
     *
     * @return string
     * Returns the fallback language.
     *
     * @author Oleg Schildt
     */
    public function getFallbackLanguage();

    /**
     * Provides the text translation for the text ID for the given langauge.
     *
     * @param string $text_id
     * Text ID
     *
     * @param string $lng
     * The langauge. If it is not specified,
     * the default langauge is used.
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
    public function text($text_id, $lng = "", $default_text = "");
    
    /**
     * Provides the text translation for the text ID for the given langauge if the translation exists.
     * Otherwise it returns the text ID and emits no warning.
     *
     * @param string $text_id
     * Text ID
     *
     * @param string $lng
     * The langauge. If it is not specified,
     * the default langauge is used.
     *
     * @return string
     * Returns the translation text or the $default_text/$text_id if no translation
     * is found.
     *
     * @author Oleg Schildt
     */
    public function try_text($text_id, $lng = "");

    /**
     * Checks whether the text translation for the text ID for the given langauge exists.
     *
     * @param string $text_id
     * Text ID
     *
     * @param string $lng
     * The langauge. If it is not specified,
     * the default langauge is used.
     *
     * @return boolean
     * Returns true if the translation exists, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function hasTranslation($text_id, $lng = "");
    
    /**
     * Provides the text translation for the language name by the code
     * for the given langauge.
     *
     * @param string $code
     * Language ISO code (lowercase, e.g. en, de, fr).
     *
     * @param string $lng
     * The langauge. If it is not specified,
     * the default langauge is used.
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
    public function getLanguageName($code, $lng = "");
    
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
    public function getLanguageCode($lang_name);
    
    /**
     * Checks whether the language code is valid (has translation).
     *
     * @param string $code
     * Language ISO code (lowercase, e.g. en, de, fr).
     *
     * @param string $lng
     * The langauge. If it is not specified,
     * the default langauge is used.
     *
     * @return boolean
     * Returns true if the langauge code is valid (has translation), otherwise false.
     *
     * @see ILanguageManager::getLanguageName()
     * @see ILanguageManager::getLanguageCode()
     * @see ILanguageManager::getLanguageList()
     * @see ILanguageManager::validateCountryCode()
     *
     * @author Oleg Schildt
     */
    public function validateLanguageCode($code, $lng = "");
    
    /**
     * Provides the list of languages for the given language in the form "code" => "translation".
     *
     * @param array &$language_list
     * Target array where the language list should be loaded.
     *
     * @param string $lng
     * The langauge. If it is not specified,
     * the default langauge is used.
     *
     * @param array $display_first
     * List of the language codes to be displayed first in the order, they appear in the list.
     *
     * @return boolean
     * Returns true if the langauge list is successfully retrieved, otherwise false.
     *
     * @see ILanguageManager::getLanguageName()
     * @see ILanguageManager::getLanguageCode()
     * @see ILanguageManager::validateLanguageCode()
     * @see ILanguageManager::getCountryList()
     *
     * @author Oleg Schildt
     */
    public function getLanguageList(&$language_list, $lng = "", $display_first = []);
    
    /**
     * Provides the text translation for the country name by the code
     * for the given langauge.
     *
     * @param string $code
     * Country ISO code (uppercase, e.g. US, DE, FR).
     *
     * @param string $lng
     * The langauge. If it is not specified,
     * the default langauge is used.
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
    public function getCountryName($code, $lng = "");
    
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
    public function getCountryCode($country_name);
    
    /**
     * Checks whether the country code is valid (has translation).
     *
     * @param string $code
     * Country ISO code (uppercase, e.g. US, DE, FR).
     *
     * @param string $lng
     * The langauge. If it is not specified,
     * the default langauge is used.
     *
     * @return boolean
     * Returns true if the country code is valid (has translation), otherwise false.
     *
     * @see ILanguageManager::getCountryName()
     * @see ILanguageManager::getCountryCode()
     * @see ILanguageManager::getCountryList()
     * @see ILanguageManager::validateLanguageCode()
     *
     * @author Oleg Schildt
     */
    public function validateCountryCode($code, $lng = "");
    
    /**
     * Provides the list of countries for the given language in the form "code" => "translation".
     *
     * @param array &$country_list
     * Target array where the country list should be loaded.
     *
     * @param string $lng
     * The langauge. If it is not specified,
     * the default langauge is used.
     *
     * @param array $display_first
     * List of the country codes to be displayed first in the order, they appear in the list.
     *
     * @return boolean
     * Returns true if the country list is successfully retrieved, otherwise false.
     *
     * @see ILanguageManager::getCountryName()
     * @see ILanguageManager::getCountryCode()
     * @see ILanguageManager::validateCountryCode()
     * @see ILanguageManager::getLanguageList()
     *
     * @author Oleg Schildt
     */
    public function getCountryList(&$country_list, $lng = "", $display_first = []);
} // ILanguageManager
