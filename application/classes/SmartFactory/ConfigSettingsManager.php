<?php
/**
 * This file contains the implementation of the interface ISettingsManager 
 * in the class ConfigSettingsManager for management of the
 * config settings.
 *
 * @package System
 *
 * @author Oleg Schildt 
 */
 
namespace SmartFactory;

use SmartFactory\Interfaces\ISettingsManager;

/**
 * Class for management of the config settings stored in a config XML dile.
 *
 * This settings manager supports the multistep wizard like processing. That means 
 * that you do not have to put all settings in one big form and save them in one action. 
 * You can collect them over multiple steps (requests), go forward ad back, validate them 
 * on each step and finally save them after the final step.
 *
 * You do not need any preliminary special settings name definitions. When you introduce
 * a new setting, just start saving and getting it.
 *
 * @see ApplicationSettingsManager
 * @see UserSettingsManager
 *
 * @author Oleg Schildt 
 */
class ConfigSettingsManager implements ISettingsManager
{
  /**
   * @var string
   * Internal variable that holds the target file path for storing the settings data.
   *
   * @author Oleg Schildt 
   */
  protected $save_path;

  /**
   * @var string
   * Internal variable for storing the state whether the data should be encrypted.
   * before saving.
   *
   * @author Oleg Schildt 
   */
  protected $save_encrypted;

  /**
   * @var string
   * Internal variable for storing the salt key if the data should be encrypted
   * before saving.
   *
   * @author Oleg Schildt 
   */
  protected $salt_key = "default";

  /**
   * @var string
   * Internal variable for storing the state whether the config file must exist.
   * before saving.
   *
   * @author Oleg Schildt 
   */
  protected $config_file_must_exist = false;

  /**
   * @var string
   * Internal variable for storing the current context.
   *
   * @see getContext
   * @see setContext
   *
   * @author Oleg Schildt 
   */
  protected $context = "default";

  /**
   * @var \SmartFactory\Interfaces\ISettingsValidator
   * Internal variable for storing the validator.
   *
   * @see getValidator
   * @see setValidator
   *
   * @author Oleg Schildt 
   */
  protected $validator = null;  

  /**
   * @var array
   * Internal variable for storing the array of settings values.
   *
   * @author Oleg Schildt 
   */
  protected $temp_settings;

  /**
   * @var array
   * Internal variable for storing the array of changed settings values.
   *
   * The changes are set to the temp_settings and are persisted and
   * written to the storage by saving.
   *
   * @author Oleg Schildt 
   */
  protected $settings;

  /**
   * This is internal auxiliary function for converting the settings to XML and storing it
   * to the target file defined by the iniailization.
   *
   * @param array $data 
   * The array with the settings values to be saved.
   *
   * @return boolean
   * Returns true if the data has been successfully saved, otherwise false.
   *
   * @see loadXML
   *
   * @author Oleg Schildt 
   */
  protected function saveXML(&$data)
  {
    $xmldoc = new \DOMDocument("1.0", "UTF-8");
    $xmldoc->formatOutput = true;

    $root = $xmldoc->createElement("array");
    $root = $xmldoc->appendChild($root);

    array_to_dom($root, $data);
    
    $xml = $xmldoc->saveXML();
    
    if(!empty($this->save_encrypted))
    {
      $xml = aes_256_encrypt($xml, $this->salt_key);
    }
    
    if((!file_exists($this->save_path) || is_writable($this->save_path)) &&
       is_writable(dirname($this->save_path)) &&
       file_put_contents($this->save_path, $xml) !== false
      ) 
    {
      return true;
    }   
    
    return false;
  } // saveXML

  /**
   * This is internal auxiliary function for loading the settings from the target file
   * defined by the iniailization.
   *
   * @param array $data 
   * The target array with the settings values to be loaded.
   *
   * @return boolean
   * Returns true if the data has been successfully loaded, otherwise false.
   *
   * @see saveXML
   *
   * @author Oleg Schildt 
   */
  protected function loadXML(&$data)
  {
    if(!file_exists($this->save_path) && empty($this->config_file_must_exist)) return true;
    
    if(!file_exists($this->save_path) || !is_readable($this->save_path)) return false;
    
    $xml = file_get_contents($this->save_path);
    if($xml === false) return false;
    
    if(!empty($this->save_encrypted))
    {
      $xml = aes_256_decrypt($xml, $this->salt_key);
    }

    $xmldoc = new \DOMDocument();

    if(!@$xmldoc->loadXML($xml)) return false;

    dom_to_array($xmldoc->documentElement, $data);
    
    return true;
  } // loadXML

  /**
   * Default constructor.
   *
   * @author Oleg Schildt
   */
  public function __construct()
  {
    $this->settings = &session()->vars()["__config_settings"];
    $this->temp_settings = &session()->vars()["__temp_config_settings"];
  } // __construct

  /**
   * Initializes the settings manager parameters.
   * 
   * @param array $parameters 
   * Settings for saving ad loading as an associative array in the form key => value:
   *
   * - $parameters["save_path"] - the target file path where the settings data should be stored.
   *
   * - $parameters["save_encrypted"] - if it is true, the data is encrypted before saving.
   *
   * - $parameters["salt_key"] - the salt key if the data should be encrypted before saving.
   *
   * - $parameters["config_file_must_exist"] - if this paremeter is true and the config file does not exist,
   * the loading function will fail.
   *
   * @return boolean 
   * Returns true upon successful initialization, otherwise false.   
   *
   * @author Oleg Schildt 
   */
  public function init($parameters)
  {
    $this->save_path = $parameters["save_path"];
    
    if(!empty($parameters["save_encrypted"])) $this->save_encrypted = $parameters["save_encrypted"];
    if(!empty($parameters["salt_key"])) $this->salt_key = $parameters["salt_key"];
    if(!empty($parameters["config_file_must_exist"])) $this->config_file_must_exist = $parameters["config_file_must_exist"];

    return true;
  } // init

  /**
   * Sets the validator for the settings.
   * 
   * @param \SmartFactory\Interfaces\ISettingsValidator $validator 
   * The settings validator.
   *
   * @return void   
   *
   * @see getValidator
   * @see validateSettings
   *
   * @author Oleg Schildt 
   */
  public function setValidator($validator)
  {
    $this->validator = $validator;
  } // setValidator

  /**
   * Returns the validator for the settings.
   * 
   * @return \SmartFactory\Interfaces\ISettingsValidator|null  
   * Returns the validator for the settings or null if none is defined.
   *
   * @see setValidator
   * @see validateSettings
   *
   * @author Oleg Schildt 
   */
  public function getValidator()
  {
    return $this->validator;
  } // getValidator

  /**
   * Sets the settings context.
   *
   * Settings might be edited not in one dialog,
   * There can be several masks for different type of settings,
   * or the settings can be configured in a wizard.
   * In this case, only a subset of settings has to be
   * saved and validated.
   * To be able to write a flexible validator for the subsets,
   * the $context is used. It can be - step1, step1, server_settings,
   * db_connection_settings etc.
   *
   * @param string $context
   * The settings context.
   *
   * @return void
   *
   * @see getContext
   *
   * @author Oleg Schildt 
   */
  public function setContext($context = "default")
  {
    $this->context = $context;
  } // setContext

  /**
   * Returns the current settings context.
   *
   * Settings might be edited not in one dialog,
   * There can be several masks for different type of settings,
   * or the settings can be configured in a wizard.
   * In this case, only a subset of settings has to be
   * saved and validated.
   * To be able to write a flexible validator for the subsets,
   * the $context is used. It can be - step1, step1, server_settings,
   * db_connection_settings etc.
   *
   * @return string
   * Returns the current settings context.
   *
   * @see setContext
   *
   * @author Oleg Schildt 
   */
  public function getContext()
  {
    return $this->context;
  } // getContext

  /**
   * Checks whether the settings data is dirty (not saved) within a context or globally.
   * 
   * @param boolean $global 
   * If $global is false, the dirty state is checked only within the current context.
   * If $global is true, the dirty state is checked globally.
   *
   * @return boolean 
   * Returs true if the settings data is dirty, otherwise false.   
   *
   * @author Oleg Schildt 
   */
  public function isDirty($global = false)
  {
    if($global) return !empty($this->temp_settings["__dirty"]);
    
    return !empty($this->temp_settings["__dirty"][$this->context]);
  } // isDirty

  /**
   * Sets a settings parameter.
   * 
   * @param string $name 
   * The name of the settings parameter.
   *
   * @param mixed $value 
   * The value of the settings parameter.
   *
   * @return void   
   *
   * @see getParameter
   *
   * @author Oleg Schildt 
   */
  public function setParameter($name, $value)
  {
    $this->temp_settings[$name] = $value;

    $this->temp_settings["__dirty"][$this->context] = true;
  } // setParameter

  /**
   * Returns the value of a settings parameter.
   * 
   * @param string $name 
   * The name of the settings parameter.
   *
   * @param boolean $get_dirty 
   * If settings are not saved yet, the unsaved new value
   * of the parameter is returned if $get_dirty is true.
   *
   * @param mixed $default 
   * The default value of the settings parameter if it is not set yet.
   * The parameter is a confortable way to pre-set a parameter 
   * to a default value if its value is not set yet.
   * However, if the status of the data is dirty and the unsaved
   * last entered value is requested, then always the actual
   * last entered value is returned and this paramter is ignored.
   *
   * @return mixed   
   * Returns the value of the settings parameter.
   *
   * @see setParameter
   *
   * @author Oleg Schildt 
   */
  public function getParameter($name, $get_dirty = false, $default = null)
  {
    if($get_dirty && $this->isDirty())
    {
      if(empty($this->temp_settings[$name])) return null;
      
      return $this->temp_settings[$name];
    }
    
    if(!isset($this->settings[$name])) return $default;
    
    return $this->settings[$name];
  } // getParameter

  /**
   * Validates the current settings values.
   *
   * It should be called after settings the new values of the parameters
   * and before their saving.
   *
   * @return boolean
   * Returns true if there is no validator defined, otherwise lets 
   * the validator validate the settings.   
   *
   * @uses \SmartFactory\Interfaces\ISettingsValidator
   *
   * @see getValidator
   * @see setValidator
   *
   * @author Oleg Schildt 
   */
  public function validateSettings()
  {
    if(empty($this->validator)) return true;
    
    return $this->validator->validate($this, $this->context);
  } // validateSettings

  /**
   * Loads the settings from the target file.
   *
   * @return boolean
   * Returns true if the settings have been successfully loaded, otherwise false.
   *
   * @see saveSettings
   *
   * @author Oleg Schildt 
   */
  public function loadSettings()
  {
    if($this->isDirty(true)) return true;
    
    if(!$this->loadXML($this->settings)) 
    {
      messenger()->setError(text("ErrLoadingSettings"),
                            sprintf(text("ErrReadingFile"), $this->save_path)
                           );
      return false;
    }
    
    $this->temp_settings = $this->settings;

    unset($this->temp_settings["__dirty"]);
    
    return true;
  } // loadSettings

  /**
   * Saves the settings from to the target file.
   *
   * @return boolean
   * Returns true if the settings have been successfully saved, otherwise false.
   *
   * @see loadSettings
   *
   * @author Oleg Schildt 
   */
  public function saveSettings()
  {
    $old_dirty_state = $this->temp_settings["__dirty"];
    unset($this->temp_settings["__dirty"]);
    
    if($this->saveXML($this->temp_settings))
    {
      $this->settings = $this->temp_settings;
      return true;
    }
     
    $this->temp_settings["__dirty"] = $old_dirty_state;
    
    messenger()->setError(text("ErrSavingSettings"),
                          sprintf(text("ErrWritingFile"), $this->save_path)
                         );
     
    return false;  
  } // saveSettings
} // ConfigSettingsManager
