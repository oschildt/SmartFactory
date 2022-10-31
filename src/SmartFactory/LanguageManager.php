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
 * Class for working with localization of texts.
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
    protected $localization_path;

    /**
     * Internal variable for storing the paths of the additional localization files.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected $additional_localization_files;

    /**
     * Internal variable for storing the current context.
     *
     * @var string
     *
     * @see LanguageManager::getContext()
     *
     * @author Oleg Schildt
     */
    static protected $context = "default";

    /**
     * Internal variable for storing the current language.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    static protected $current_language = "";

    /**
     * Internal variable for storing the state whether the dictionary is loaded or not.
     *
     * @var boolean
     *
     * @author Oleg Schildt
     */
    static protected $dictionary_loaded = false;

    /**
     * Internal variable for storing the state whether the APCU should be used.
     *
     * @var boolean
     *
     * @author Oleg Schildt
     */
    protected $use_apcu = false;

    /**
     * Internal variable for storing the state whether the E_USER_NOTICE is triggered in the case of missing translations.
     *
     * @var boolean
     *
     * @author Oleg Schildt
     */
    protected $warn_missing = false;

    /**
     * Internal array for storing the list of supported languages.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    static protected $supported_languages = [];

    /**
     * Internal array for storing the list of language name translations.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    static protected $languages = [];

    /**
     * Internal array for storing the list of country name translations.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    static protected $countries = [];

    /**
     * Internal array for storing the list of text translations.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    static protected $texts = [];

    /**
     * Initializes the language manager with parameters.
     *
     * @param array $parameters
     * Settings for logging as an associative array in the form key => value:
     *
     * - $parameters["localization_path"] - the path where the localization files are stored.
     *
     * - $parameters["additional_localization_files"] - the paths of the additional localization files.
     *
     * - $parameters["use_apcu"] - if installed, apcu can be used to cache the translations in the memory.
     *
     * - $parameters["warn_missing"] - If it is set to true, the E_USER_NOTICE is triggered in the case of missing translations.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function init($parameters)
    {
        if (!empty($parameters["localization_path"])) {
            $this->localization_path = $parameters["localization_path"];
        }

        if (!empty($parameters["additional_localization_files"])) {
            $this->additional_localization_files = $parameters["additional_localization_files"];
        }

        if (!empty($parameters["use_apcu"])) {
            $this->use_apcu = $parameters["use_apcu"];
        }

        if (!empty($parameters["warn_missing"])) {
            $this->warn_missing = $parameters["warn_missing"];
        }
    }

    /**
     * This is internal auxiliary function for loading the translations from the source JSON file.
     *
     * @return boolean
     * It should return true if the dictoinary has been successfully loaded, otherwise false.
     *
     * @throws \Exception
     * It might throw the following exceptions in the case of any errors:
     *
     * - if the translation file is invalid.
     *
     * @author Oleg Schildt
     */
    public function loadDictionary()
    {
        if (self::$dictionary_loaded) {
            return true;
        }

        if ($this->use_apcu) {
            do {
                if (!apcu_exists("dictionary_supported_languages")) {
                    break;
                }

                self::$supported_languages = apcu_fetch("dictionary_supported_languages");
                if (empty(self::$supported_languages)) {
                    break;
                }

                if (!apcu_exists("dictionary_languages")) {
                    break;
                }
                self::$languages = apcu_fetch("dictionary_languages");
                if (empty(self::$languages)) {
                    break;
                }

                if (!apcu_exists("dictionary_countries")) {
                    break;
                }
                self::$countries = apcu_fetch("dictionary_countries");
                if (empty(self::$countries)) {
                    break;
                }

                if (!apcu_exists("dictionary_texts")) {
                    break;
                }
                self::$texts = apcu_fetch("dictionary_texts");
                if (empty(self::$texts)) {
                    break;
                }

                return true;
            } while (false);
        }

        $json_array = [];

        $json = file_get_contents($this->localization_path . "config.json");
        if ($json === false) {
            throw new \Exception("Translation file '" . $this->localization_path . "config.json" . "' cannot be loaded or does not exist!");
        }

        try {
            json_to_array($json, $json_array);
        } catch (\Throwable $ex) {
            throw new \Exception("Translation file '" . $this->localization_path . "config.json" . "' is invalid!" . "\n\n" . $ex->getMessage());
        }

        $json = file_get_contents($this->localization_path . "languages.json");
        if ($json === false) {
            throw new \Exception("Translation file '" . $this->localization_path . "languages.json" . "' cannot be loaded or does not exist!");
        }

        try {
            json_to_array($json, $json_array["languages"]);
        } catch (\Throwable $ex) {
            throw new \Exception("Translation file '" . $this->localization_path . "languages.json" . "' is invalid!" . "\n\n" . $ex->getMessage());
        }

        $json = file_get_contents($this->localization_path . "countries.json");
        if ($json === false) {
            throw new \Exception("Translation file '" . $this->localization_path . "countries.json" . "' cannot be loaded or does not exist!");
        }

        try {
            json_to_array($json, $json_array["countries"]);
        } catch (\Throwable $ex) {
            throw new \Exception("Translation file '" . $this->localization_path . "countries.json" . "' is invalid!" . "\n\n" . $ex->getMessage());
        }

        $json = file_get_contents($this->localization_path . "texts.json");
        if ($json === false) {
            throw new \Exception("Translation file '" . $this->localization_path . "texts.json" . "' cannot be loaded or does not exist!");
        }

        try {
            json_to_array($json, $json_array["texts"]);
        } catch (\Throwable $ex) {
            throw new \Exception("Translation file '" . $this->localization_path . "texts.json" . "' is invalid!" . "\n\n" . $ex->getMessage());
        }

        if (!empty($this->additional_localization_files)) {
            foreach ($this->additional_localization_files as $localization_file) {
                $json = file_get_contents($localization_file);
                if ($json === false) {
                    throw new \Exception("Translation file '" . $localization_file . "' cannot be loaded or does not exist!");
                }

                try {
                    $translation_texts = [];
                    json_to_array($json, $translation_texts);

                    $json_array["texts"] = array_merge($json_array["texts"], $translation_texts);
                } catch (\Throwable $ex) {
                    throw new \Exception("Translation file '" . $localization_file . "' is invalid!" . "\n\n" . $ex->getMessage());
                }
            }
        }

        if (!empty($json_array["interface_languages"])) {
            foreach ($json_array["interface_languages"] as $lang_code) {
                self::$supported_languages[$lang_code] = $lang_code;
            }
        }

        if (!empty($json_array["languages"])) {
            foreach ($json_array["languages"] as $text_id => &$translations) {
                foreach ($translations as $lang_code => $translation) {
                    self::$languages[$lang_code][$text_id] = $translation;
                }
            }
        }

        if (!empty($json_array["countries"])) {
            foreach ($json_array["countries"] as $text_id => &$translations) {
                foreach ($translations as $lang_code => $translation) {
                    self::$countries[$lang_code][$text_id] = $translation;
                }
            }
        }

        if (!empty($json_array["texts"])) {
            foreach ($json_array["texts"] as $text_id => &$translations) {
                foreach ($translations as $lang_code => $translation) {
                    self::$texts[$lang_code][$text_id] = $translation;
                }
            }
        }

        self::$dictionary_loaded = true;

        foreach (self::$supported_languages as $lng) {
            self::$current_language = $lng;
            break;
        }

        if ($this->use_apcu) {
            apcu_store("dictionary_supported_languages", self::$supported_languages);
            apcu_store("dictionary_languages", self::$languages);
            apcu_store("dictionary_countries", self::$countries);
            apcu_store("dictionary_texts", self::$texts);
        }

        return true;
    } // loadDictionary

    /**
     * This function should detect the current language based on cookies, browser languages etc.
     *
     * Priority:
     *
     * 1. explicitly set by the request parameter language.
     * 2. header 'Content-Language'.
     * 3. last language in the cookie.
     * 4. browser default language.
     * 5. the first one from the supported list.
     * 6. English.
     *
     * @param string $context
     * The context of the application.
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
    public function detectLanguage($context = "default")
    {
        self::$context = $context;

        // Let's go

        // 6. English
        $language = "en";

        // 5. the first one from the supported list
        foreach (self::$supported_languages as $lng) {
            $language = $lng;
            break;
        }

        // 4. browser default language
        if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) && trim($_SERVER["HTTP_ACCEPT_LANGUAGE"]) != "") {
            $accepted = explode(',', $_SERVER["HTTP_ACCEPT_LANGUAGE"]);

            foreach ($accepted as $key => $name) {
                $code = explode(';', $name);
                // handle the cases like en-ca => en
                $code = explode("-", $code[0]);

                if (!empty(self::$supported_languages[$code[0]])) {
                    $language = $code[0];
                    break;
                }
            }
        }

        // 3. last language in the cookie
        $tmp = get_cookie(self::$context . "_language");
        if (!empty($tmp) && !empty(self::$supported_languages[$tmp])) {
            $language = $tmp;
        }

        // 2. header 'Content-Language'.
        $header = get_header("Content-Language");
        if (!empty($header) && !empty(self::$supported_languages[$header])) {
            $language = $header;
        }

        // 1. explicitly set by request parameter language
        if (!empty($_REQUEST["language"]) && !empty(self::$supported_languages[$_REQUEST["language"]])) {
            $language = $_REQUEST["language"];
        }

        $this->setCurrentLanguage($language);
    } // detectLanguage

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
    public function getContext()
    {
        return self::$context;
    } // getContext

    /**
     * Returns the list of supported languages.
     *
     * @return array
     * Returns the list of supported languages.
     *
     * @author Oleg Schildt
     */
    public function getSupportedLanguages()
    {
        return self::$supported_languages;
    } // getSupportedLanguages

    /**
     * Sets the current language.
     *
     * @param string $language
     * The language ISO code to be set.
     *
     * @return boolean
     * Returns true if the current language has been successfully set, otherwise false.
     *
     * @see LanguageManager::getCurrentLanguage()
     *
     * @author Oleg Schildt
     */
    public function setCurrentLanguage($language)
    {
        if (empty(self::$supported_languages[$language])) {
            return false;
        }

        self::$current_language = $language;

        if (get_cookie("language_cookie_allowed")) {
            set_cookie(self::$context . "_language", $language, time() + 365 * 24 * 3600, ["samesite" => "strict"]);
        }

        return true;
    } // setCurrentLanguage

    /**
     * Returns the current language.
     *
     * @return string
     * Returns the current language ISO code.
     *
     * @see LanguageManager::setCurrentLanguage()
     *
     * @author Oleg Schildt
     */
    public function getCurrentLanguage()
    {
        return self::$current_language;
    } // getCurrentLanguage

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
    public function text($text_id, $lng = "", $default_text = "")
    {
        if (empty($lng)) {
            $lng = $this->getCurrentLanguage();
        }

        if (empty(self::$texts[$lng][$text_id])) {
            if ($this->warn_missing) {
                trigger_error("No translation for the text '$text_id' in the language [$lng]!", E_USER_NOTICE);
            }

            if (empty($default_text)) {
                return $text_id;
            } else {
                return $default_text;
            }
        }

        return self::$texts[$lng][$text_id];
    } // text

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
    public function hasTranslation($text_id, $lng = "")
    {
        if (empty($lng)) {
            $lng = $this->getCurrentLanguage();
        }

        return !empty(self::$texts[$lng][$text_id]);
    } // hasTranslation

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
     * @see LanguageManager::getLanguageCode()
     * @see LanguageManager::validateLanguageCode()
     * @see LanguageManager::getLanguageList()
     * @see LanguageManager::getCountryName()
     *
     * @author Oleg Schildt
     */
    public function getLanguageName($code, $lng = "")
    {
        if (empty($lng)) {
            $lng = $this->getCurrentLanguage();
        }

        if (empty(self::$languages[$lng][$code])) {
            if ($this->warn_missing) {
                trigger_error("No translation for the language name [$code] in the language [$lng]!", E_USER_NOTICE);
            }
            return $code;
        }

        return self::$languages[$lng][$code];
    } // getLanguageName

    /**
     * Tries to find the language code by the given name.
     *
     * @param string $lang_name
     * The name of the language in any supported language.
     *
     * @return string
     * Returns the language code if it could be found, otherwise an empty string.
     *
     * @see LanguageManager::getLanguageName()
     * @see LanguageManager::validateLanguageCode()
     * @see LanguageManager::getLanguageList()
     * @see LanguageManager::getCountryCode()
     *
     * @author Oleg Schildt
     */
    public function getLanguageCode($lang_name)
    {
        foreach (self::$supported_languages as $lng) {
            if (empty(self::$languages[$lng])) {
                continue;
            }

            foreach (self::$languages[$lng] as $code => $translation) {
                if (strcasecmp($lang_name, $translation) == 0) {
                    return $code;
                }
            } // foreach
        } // foreach

        return "";
    } // getLanguageCode

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
     * @see LanguageManager::getLanguageName()
     * @see LanguageManager::getLanguageCode()
     * @see LanguageManager::getLanguageList()
     * @see LanguageManager::validateCountryCode()
     *
     * @author Oleg Schildt
     */
    public function validateLanguageCode($code, $lng = "")
    {
        if (empty($lng)) {
            $lng = $this->getCurrentLanguage();
        }

        return !empty(self::$languages[$lng][$code]);
    } // validateLanguageCode

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
     * @see LanguageManager::getLanguageName()
     * @see LanguageManager::getLanguageCode()
     * @see LanguageManager::validateLanguageCode()
     * @see LanguageManager::getCountryList()
     *
     * @author Oleg Schildt
     */
    public function getLanguageList(&$language_list, $lng = "", $display_first = [])
    {
        if (empty($lng)) {
            $lng = $this->getCurrentLanguage();
        }

        if (empty(self::$languages[$lng])) {
            return false;
        }

        $language_list = array_flip($display_first);

        asort(self::$languages[$lng], SORT_LOCALE_STRING);

        foreach (self::$languages[$lng] as $code => $name) {
            $language_list[$code] = $name;
        }

        return true;
    } // getLanguageList

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
     * @see LanguageManager::getCountryCode()
     * @see LanguageManager::validateCountryCode()
     * @see LanguageManager::getCountryList()
     * @see LanguageManager::getLanguageName()
     *
     * @author Oleg Schildt
     */
    public function getCountryName($code, $lng = "")
    {
        if (empty($lng)) {
            $lng = $this->getCurrentLanguage();
        }

        if (empty(self::$countries[$lng][$code])) {
            if ($this->warn_missing) {
                trigger_error("No translation for the country name [$code] in the language [$lng]!", E_USER_NOTICE);
            }
            return $code;
        }

        return self::$countries[$lng][$code];
    } // getCountryName

    /**
     * Tries to find the country code by the given name.
     *
     * @param string $country_name
     * The name of the country in any supported language.
     *
     * @return string
     * Returns the country code if it could be found, otherwise an empty string.
     *
     * @see LanguageManager::getCountryName()
     * @see LanguageManager::validateCountryCode()
     * @see LanguageManager::getCountryList()
     * @see LanguageManager::getLanguageCode()
     *
     * @author Oleg Schildt
     */
    public function getCountryCode($country_name)
    {
        foreach (self::$supported_languages as $lng) {
            if (empty(self::$countries[$lng])) {
                continue;
            }

            foreach (self::$countries[$lng] as $code => $translation) {
                if (strcasecmp($country_name, $translation) == 0) {
                    return $code;
                }
            } // foreach
        } // foreach

        return "";
    } // getCountryCode

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
     * @see LanguageManager::getCountryName()
     * @see LanguageManager::getCountryCode()
     * @see LanguageManager::getCountryList()
     * @see LanguageManager::validateLanguageCode()
     *
     * @author Oleg Schildt
     */
    public function validateCountryCode($code, $lng = "")
    {
        if (empty($lng)) {
            $lng = $this->getCurrentLanguage();
        }

        return !empty(self::$countries[$lng][$code]);
    } // validateCountryCode

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
     * @see LanguageManager::getCountryName()
     * @see LanguageManager::getCountryCode()
     * @see LanguageManager::validateCountryCode()
     * @see LanguageManager::getLanguageList()
     *
     * @author Oleg Schildt
     */
    public function getCountryList(&$country_list, $lng = "", $display_first = [])
    {
        if (empty($lng)) {
            $lng = $this->getCurrentLanguage();
        }

        if (empty(self::$countries[$lng])) {
            return false;
        }

        $country_list = array_flip($display_first);

        asort(self::$countries[$lng], SORT_LOCALE_STRING);

        foreach (self::$countries[$lng] as $code => $name) {
            $country_list[$code] = $name;
        }

        return true;
    } // getCountryList
} // LanguageManager
