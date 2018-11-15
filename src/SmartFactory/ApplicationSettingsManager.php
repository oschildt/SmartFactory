<?php
/**
 * This file contains the implementation of the interface ISettingsManager
 * in the class ApplicationSettingsManager for management of the
 * application settings.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\Interfaces\ISettingsManager;

/**
 * Class for management of the application settings.
 *
 * This settings manager supports the multistep wizard like processing. That means
 * that you do not have to put all settings in one big form and save them in one action.
 * You can collect them over multiple steps (requests), go forward ad back, validate them
 * on each step and finally save them after the final step.
 *
 * You do not need any preliminary special settings name definitions. When you introduce
 * a new setting, just start saving and getting it. But you need provide a settings table
 * with a column where the settings data will be saved.
 *
 * @see ConfigSettingsManager
 * @see UserSettingsManager
 *
 * @uses DatabaseWorkers\DBWorker
 *
 * @author Oleg Schildt
 */
class ApplicationSettingsManager implements ISettingsManager
{
  /**
   * Internal variable for storing the dbworker.
   *
   * @var DatabaseWorkers\DBWorker
   *
   * @author Oleg Schildt
   */
  protected $dbworker;

  /**
   * Internal variable for storing the target settings table name.
   *
   * @var string
   *
   * @author Oleg Schildt
   */
  protected $settings_table;

  /**
   * Internal variable for storing the target column.
   *
   * @var string
   *
   * @author Oleg Schildt
   */
  protected $settings_column;

  /**
   * Internal variable for storing the current context.
   *
   * @var string
   *
   * @see getContext()
   * @see setContext()
   *
   * @author Oleg Schildt
   */
  protected $context = "default";

  /**
   * Internal variable for storing the validator.
   *
   * @var Interfaces\ISettingsValidator
   *
   * @see getValidator()
   * @see setValidator()
   *
   * @author Oleg Schildt
   */
  protected $validator = null;

  /**
   * Internal variable for storing the array of settings values.
   *
   * @var array
   *
   * @author Oleg Schildt
   */
  protected $temp_settings;

  /**
   * Internal variable for storing the array of changed settings values.
   *
   * @var array
   *
   * The changes are set to the temp_settings and are persisted and
   * written to the storage by saving.
   *
   * @author Oleg Schildt
   */
  protected $settings;

  /**
   * This is internal auxiliary function for checking that the settings
   * manager is intialized correctly.
   *
   * @return boolean
   * It should return true if the settings manager is intialized correctly, otherwise false.
   *
   * @author Oleg Schildt
   */
  protected function basic_check()
  {
    if(empty($this->dbworker))
    {
      trigger_error("DBWorker is not defined!", E_USER_ERROR);
      return false;
    }

    if(empty($this->settings_table))
    {
      trigger_error("Settings table is not defined!", E_USER_ERROR);
      return false;
    }

    if(empty($this->settings_column))
    {
      trigger_error("Settings column is not defined!", E_USER_ERROR);
      return false;
    }

    return true;
  } // basic_check

  /**
   * This is internal auxiliary function for converting the settings to XML and storing it
   * to the target table defined by the iniailization.
   *
   * @param array $data
   * The array with the settings values to be saved.
   *
   * @return boolean
   * Returns true if the data has been successfully saved, otherwise false.
   *
   * @see loadXMLfromDB()
   *
   * @author Oleg Schildt
   */
  protected function saveXMLtoDB(&$data)
  {
    $xmldoc = new \DOMDocument("1.0", "UTF-8");
    $xmldoc->formatOutput = true;

    $root = $xmldoc->createElement("array");
    $root = $xmldoc->appendChild($root);

    array_to_dom($root, $data);

    $xml = $xmldoc->saveXML();

    // check existance

    $query = "SELECT 1 FROM " . $this->settings_table;

    if(!$this->dbworker->execute_query($query))
    {
      return sql_error($this->dbworker);
    }

    $must_insert = true;

    if($this->dbworker->fetch_row())
    {
      $must_insert = false;
    }

    $this->dbworker->free_result();

    $xml = $this->dbworker->escape($xml);

    if($must_insert)
    {
      $query = "INSERT INTO " . $this->settings_table . "(" . $this->settings_column . ")\n";
      $query .= "VALUES ('$xml')";
    }
    else
    {
      $query = "UPDATE " . $this->settings_table . " SET " . $this->settings_column . " = '$xml'";
    }

    if(!$this->dbworker->execute_query($query))
    {
      return sql_error($this->dbworker);
    }

    return true;
  } // saveXMLtoDB

  /**
   * This is internal auxiliary function for loading the settings from the target table
   * defined by the iniailization.
   *
   * @param array $data
   * The target array with the settings values to be loaded.
   *
   * @return boolean
   * Returns true if the data has been successfully loaded, otherwise false.
   *
   * @see saveXMLtoDB()
   *
   * @author Oleg Schildt
   */
  protected function loadXMLfromDB(&$data)
  {
    $query = "SELECT " . $this->settings_column . " FROM " . $this->settings_table;

    if(!$this->dbworker->execute_query($query))
    {
      return sql_error($this->dbworker);
    }

    $xml = "";

    if($this->dbworker->fetch_row())
    {
      $xml = $this->dbworker->field_by_name($this->settings_column);
    }

    $this->dbworker->free_result();

    if(empty($xml)) return true;

    $xmldoc = new \DOMDocument();

    if(!@$xmldoc->loadXML($xml)) return false;

    dom_to_array($xmldoc->documentElement, $data);

    return true;
  } // loadXMLfromDB

  /**
   * Constructor.
   *
   * @author Oleg Schildt
   */
  public function __construct()
  {
    $this->settings = &session()->vars()["__application_settings"];
    $this->temp_settings = &session()->vars()["__temp_application_settings"];
  } // __construct

  /**
   * Initializes the settings manager parameters.
   *
   * @param array $parameters
   * Settings for saving and loading as an associative array in the form key => value:
   *
   * - $parameters["dbworker"] - the dbworker to used for loading and storing settings.
   *
   * - $parameters["settings_table"] - the name of the table for the storing of the settings.
   *
   * - $parameters["settings_column"] - the name of the column for the storing of the settings.
   *
   * @return boolean
   * Returns true upon successful initialization, otherwise false.
   *
   * @author Oleg Schildt
   */
  public function init($parameters)
  {
    if(!empty($parameters["dbworker"])) $this->dbworker = $parameters["dbworker"];
    if(!empty($parameters["settings_table"])) $this->settings_table = $parameters["settings_table"];
    if(!empty($parameters["settings_column"])) $this->settings_column = $parameters["settings_column"];

    return true;
  } // init

  /**
   * Sets the validator for the settings.
   *
   * @param Interfaces\ISettingsValidator $validator
   * The settings validator.
   *
   * @return void
   *
   * @see getValidator()
   * @see validateSettings()
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
   * @return Interfaces\ISettingsValidator|null
   * Returns the validator for the settings or null if none is defined.
   *
   * @see setValidator()
   * @see validateSettings()
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
   * @see getContext()
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
   * @see setContext()
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
   * @see getParameter()
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
   * @see setParameter()
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
   * @see getValidator()
   * @see setValidator()
   *
   * @author Oleg Schildt
   */
  public function validateSettings()
  {
    if(empty($this->validator)) return true;

    return $this->validator->validate($this, $this->context);
  } // validateSettings

  /**
   * Loads the settings from the target table.
   *
   * @return boolean
   * Returns true if the settings have been successfully loaded, otherwise false.
   *
   * @see saveSettings()
   *
   * @author Oleg Schildt
   */
  public function loadSettings()
  {
    if(!$this->basic_check()) return false;

    if($this->isDirty(true)) return true;

    if(!$this->loadXMLfromDB($this->settings)) return false;

    $this->temp_settings = $this->settings;

    unset($this->temp_settings["__dirty"]);

    return true;
  } // loadSettings

  /**
   * Saves the settings from to the target table.
   *
   * @return boolean
   * Returns true if the settings have been successfully saved, otherwise false.
   *
   * @see loadSettings()
   *
   * @author Oleg Schildt
   */
  public function saveSettings()
  {
    if(!$this->basic_check()) return false;

    $old_dirty_state = $this->temp_settings["__dirty"];
    unset($this->temp_settings["__dirty"]);

    if($this->saveXMLtoDB($this->temp_settings))
    {
      $this->settings = $this->temp_settings;
      return true;
    }

    $this->temp_settings["__dirty"] = $old_dirty_state;

    return false;
  } // saveSettings
} // ApplicationSettingsManager