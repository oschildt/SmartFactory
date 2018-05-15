<?php
/**
 * This file contains the implementation of the interface ILanguageManager 
 * in the class LanguageManager for working with localization of texts.
 *
 * @package Sytem
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
   * @var string
   * Internal variable for storing the current context.
   *
   * @see getContext()
   *
   * @author Oleg Schildt 
   */
  static protected $context = "default";

  /**
   * @var string
   * Internal variable for storing the current language.
   *
   * @author Oleg Schildt 
   */
  static protected $current_language = "";

  /**
   * @var string
   * Internal variable for storing the state whether the dictionary is loaded or not.
   *
   * @author Oleg Schildt 
   */
  static protected $dictionary_loaded = false;

  /**
   * @var array
   * Internal array for storing the list of supported languages.
   *
   * @author Oleg Schildt 
   */
  static protected $supported_languages = [];

  /**
   * @var array
   * Internal array for storing the list of language name translations.
   *
   * @author Oleg Schildt 
   */
  static protected $languages = [];

  /**
   * @var array
   * Internal array for storing the list of country name translations.
   *
   * @author Oleg Schildt 
   */
  static protected $countries = [];

  /**
   * @var array
   * Internal array for storing the list of text translations.
   *
   * @author Oleg Schildt 
   */
  static protected $texts = [];

  /**
   * This is internal auxiliary function for loading the translations from the source 
   * XML file.
   *
   * @return boolean
   * It should return true if the dictoinary has been successfully loaded, otherwise false.
   *
   * @author Oleg Schildt 
   */
  protected function loadDictionary()
  {
    if(self::$dictionary_loaded) return true;
    
    $xmldoc = new \DOMDocument();
    
    if(!$xmldoc->load(APPLICATION_ROOT . "localization/texts.xml"))
    {
      trigger_error("Translation file 'localization/texts.xml' cannot be loaded!", E_USER_ERROR);
      return false;
    }
    
    $xsdpath = new \DOMXPath($xmldoc);
    
    $nodes = $xsdpath->evaluate("/document/interface_languages/language");
    foreach($nodes as $node)
    {
      $lang_code = $node->getAttribute("id");
      if(!empty($lang_code)) self::$supported_languages[$lang_code] = $lang_code;
    }
    
    $nodes = $xsdpath->evaluate("/document/languages/language/*");
    foreach($nodes as $node)
    {
      $pnode = $node->parentNode;

      $lang_code = $node->nodeName;
      $text_id = $pnode->getAttribute("id");
      
      if(!empty($lang_code) && !empty($text_id) && !empty($node->nodeValue)) self::$languages[$lang_code][$text_id] = $node->nodeValue;
    }
    
    $nodes = $xsdpath->evaluate("/document/countries/country/*");
    foreach($nodes as $node)
    {
      $pnode = $node->parentNode;

      $lang_code = $node->nodeName;
      $text_id = $pnode->getAttribute("id");
      
      if(!empty($lang_code) && !empty($text_id) && !empty($node->nodeValue)) self::$countries[$lang_code][$text_id] = $node->nodeValue;
    }

    $nodes = $xsdpath->evaluate("/document/texts/text/*");
    foreach($nodes as $node)
    {
      $pnode = $node->parentNode;

      $lang_code = $node->nodeName;
      $text_id = $pnode->getAttribute("id");
      
      if(!empty($lang_code) && !empty($text_id) && !empty($node->nodeValue)) self::$texts[$lang_code][$text_id] = $node->nodeValue;
    }

    self::$dictionary_loaded = true;
    
    foreach(self::$supported_languages as $lng)
    {
      self::$current_language = $lng;
      break;
    }
    
    return true;
  } // loadDictionary

  /**
   * Default constructor.
   *
   * @author Oleg Schildt
   */
  public function __construct()
  {
    $this->loadDictionary();
  } // __construct

  /**
   * This function should detect the current language based on cookies, browser languages etc.
   *
   * Priority:
   *
   * 1. explicitly set by the request parameter language.
   * 2. last language in the session.
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

   * @return void
   *
   * @author Oleg Schildt 
   */
  public function detectLanguage($context = "default")
  {
    self::$context = $context;
    
    // Priority:

    // 1) explicitly set by the request parameter language
    // 2) last language in the session
    // 3) last language in the cookie
    // 4) browser default language
    // 5) the first one from the supported list
    // 6) English
    
    // Let's go
  
    $language = "en";
    
    // 5) the first one from the supported list
    foreach(self::$supported_languages as $lng)
    {
      $language = $lng;
      break;
    }
    
    // 4) browser default 
    if(isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) && trim($_SERVER["HTTP_ACCEPT_LANGUAGE"]) != "")
    {
      $accepted = explode(',', $_SERVER["HTTP_ACCEPT_LANGUAGE"]);

      foreach($accepted as $key => $name)
      {
        $code = explode(';', $name);
        // handle the cases like en-ca => en
        $code = explode("-", $code[0]);

        if(!empty(self::$supported_languages[$code[0]]))
        {
          $language = $code[0];
          break;
        }
      }
    }
    
    // 3) last language in the cookie
    if(!empty($_COOKIE[self::$context . "_language"]) && 
       !empty(self::$supported_languages[$_COOKIE[self::$context . "_language"]])
      )
    {
      $language = $_COOKIE[self::$context . "_language"];
    }
    
    // 2) last language in the session
    if(!empty(session()->vars()[self::$context . "_language"]) &&
       !empty(self::$supported_languages[session()->vars()[self::$context . "_language"]])
      ) 
    {
      $language = $_COOKIE[self::$context . "_language"];
    }

    // 1) explicitly set by request parameter language
    if(!empty($_REQUEST["language"]) &&
       !empty(self::$supported_languages[$_REQUEST["language"]])
      ) 
    {
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
   * @return boolean
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
   * @see getCurrentLanguage()
   *
   * @author Oleg Schildt 
   */
  public function setCurrentLanguage($language)
  {
    if(empty(self::$supported_languages[$language])) return false;
    
    self::$current_language = $language;
    
    session()->vars()[self::$context . "_language"] = $language;

    setcookie(self::$context . "_language", $language, time() + 365*24*3600);
    
    return true;
  } // setCurrentLanguage

  /**
   * Returns the current language.
   *
   * @return string
   * Returns the current language ISO code.
   *
   * @see setCurrentLanguage()
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
   * @param boolean $warn_missing 
   * If it is set to true, 
   * the E_USER_NOTICE is triggered in the case of mussing
   * translations.
   *
   * @return string
   * Returns the translation text or the $text_id if no translation
   * is found.
   *
   * @author Oleg Schildt 
   */
  public function text($text_id, $lng = "", $warn_missing = true)
  {
    if(empty($lng)) $lng = $this->getCurrentLanguage();
    
    if(empty(self::$texts[$lng][$text_id]))
    {
      if($warn_missing) trigger_error("No translation for the text '$text_id' in the language [$lng]!", E_USER_NOTICE);
      return $text_id;
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
    if(empty($lng)) $lng = $this->getCurrentLanguage();
    
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
   * @param boolean $warn_missing 
   * If it is set to true, 
   * the E_USER_NOTICE is triggered in the case of mussing
   * translations.
   *
   * @return string
   * Returns the translation text for the language name or the $code if no translation
   * is found.
   *
   * @see getLanguageCode()
   * @see validateLanguageCode()
   * @see getLanguageList()
   * @see getCountryName()
   *
   * @author Oleg Schildt 
   */
  public function getLanguageName($code, $lng = "", $warn_missing = true)
  {
    if(empty($lng)) $lng = $this->getCurrentLanguage();
    
    if(empty(self::$languages[$lng][$code]))
    {
      if($warn_missing) trigger_error("No translation for the language name [$code] in the language [$lng]!", E_USER_NOTICE);
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
   * @see getLanguageName()
   * @see validateLanguageCode()
   * @see getLanguageList()
   * @see getCountryCode()
   *
   * @author Oleg Schildt 
   */
  public function getLanguageCode($lang_name)
  {
    foreach(self::$supported_languages as $lng)
    {
      if(empty(self::$languages[$lng])) continue;

      foreach(self::$languages[$lng] as $code => $translation)
      {
        if(strcasecmp($lang_name, $translation) == 0) return $code;
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
   * @see getLanguageName()
   * @see getLanguageCode()
   * @see getLanguageList()
   * @see validateCountryCode()
   *
   * @author Oleg Schildt 
   */
  public function validateLanguageCode($code, $lng = "")
  {
    if(empty($lng)) $lng = $this->getCurrentLanguage();
    
    return !empty(self::$languages[$lng][$code]);
  } // validateLanguageCode

  /**
   * Provides the list of languages for the given language in the form "code" => "translation".
   * 
   * @param array $language_list 
   * Target array where the language list should be loaded.
   *
   * @param string $lng 
   * The langauge. If it is not specified,
   * the default langauge is used.
   *
   * @return boolean
   * Returns true if the langauge list is successfully retrieved, otherwise false.
   *
   * @see getLanguageName()
   * @see getLanguageCode()
   * @see validateLanguageCode()
   * @see getCountryList()
   *
   * @author Oleg Schildt 
   */
  public function getLanguageList(&$language_list, $lng = "")
  {
    if(empty($lng)) $lng = $this->getCurrentLanguage();

    if(empty(self::$languages[$lng])) return false;

    $language_list += self::$languages[$lng];

    asort($language_list, SORT_LOCALE_STRING);
    
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
   * @param boolean $warn_missing 
   * If it is set to true, 
   * the E_USER_NOTICE is triggered in the case of mussing
   * translations.
   *
   * @return string
   * Returns the translation text for the country name or the $code if no translation
   * is found.
   *
   * @see getCountryCode()
   * @see validateCountryCode()
   * @see getCountryList()
   * @see getLanguageName()
   *
   * @author Oleg Schildt 
   */
  public function getCountryName($code, $lng = "", $warn_missing = true)
  {
    if(empty($lng)) $lng = $this->getCurrentLanguage();
    
    if(empty(self::$countries[$lng][$code]))
    {
      if($warn_missing) trigger_error("No translation for the country name [$code] in the language [$lng]!", E_USER_NOTICE);
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
   * @see getCountryName()
   * @see validateCountryCode()
   * @see getCountryList()
   * @see getLanguageCode()
   *
   * @author Oleg Schildt 
   */
  public function getCountryCode($country_name)
  {
    foreach(self::$supported_languages as $lng)
    {
      if(empty(self::$countries[$lng])) continue;

      foreach(self::$countries[$lng] as $code => $translation)
      {
        if(strcasecmp($country_name, $translation) == 0) return $code;
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
   * @see getCountryName()
   * @see getCountryCode()
   * @see getCountryList()
   * @see validateLanguageCode()
   *
   * @author Oleg Schildt 
   */
  public function validateCountryCode($code, $lng = "")
  {
    if(empty($lng)) $lng = $this->getCurrentLanguage();
    
    return !empty(self::$countries[$lng][$code]);
  } // validateCountryCode

  /**
   * Provides the list of countries for the given language in the form "code" => "translation".
   * 
   * @param array $country_list 
   * Target array where the country list should be loaded.
   *
   * @param string $lng 
   * The langauge. If it is not specified,
   * the default langauge is used.
   *
   * @return boolean
   * Returns true if the country list is successfully retrieved, otherwise false.
   *
   * @see getCountryName()
   * @see getCountryCode()
   * @see validateCountryCode()
   * @see getLanguageList()
   *
   * @author Oleg Schildt 
   */
  public function getCountryList(&$country_list, $lng = "")
  {
    if(empty($lng)) $lng = $this->getCurrentLanguage();

    if(empty(self::$countries[$lng])) return false;

    $country_list += self::$countries[$lng];

    asort($country_list, SORT_LOCALE_STRING);
    
    return true;
  } // getCountryList
} // LanguageManager
