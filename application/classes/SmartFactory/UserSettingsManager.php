<?php
/**
 * This file contains the implementation of the interface ISettingsManager 
 * in the class UserSettingsManager for management of the
 * application settings.
 *
 * @package System
 *
 * @author Oleg Schildt 
 */
 
namespace SmartFactory;

use SmartFactory\Interfaces\ISettingsManager;
use SmartFactory\DatabaseWorkers\DBWorker;

/**
 * Class for management of the user settings.
 *
 * When you introduce a new user setting. You have to create a column for it in the corresponding
 * table add it to the initialization and specify the data type.
 * 
 * The user settings are loaded only once by the start of the session and are kept until the session 
 * is valid. Saving of the user settings updates both the session and the database. When a settings is 
 * requested, it is taken from the session, not from the database.
 *
 * @see ConfigSettingsManager
 * @see ApplicationSettingsManager
 *
 * @uses DatabaseWorkers\DBWorker
 *
 * @author Oleg Schildt 
 */
class UserSettingsManager implements ISettingsManager
{
  /**
   * @var DatabaseWorkers\DBWorker
   * Internal variable for storing the dbworker.
   *
   * @author Oleg Schildt 
   */
  protected $dbworker;

  /**
   * @var string
   * Internal variable for storing the target user table name.
   *
   * @author Oleg Schildt 
   */
  protected $user_table;

  /**
   * @var array
   * Internal array for storing the target colums for each settings.
   *
   * @author Oleg Schildt 
   */
  protected $settings_fields;

  /**
   * @var string
   * Internal variable for storing the field name that identifies the user record.
   *
   * @author Oleg Schildt 
   */
  protected $user_id_field;
  
    /**
   * @var callable
   * Internal variable for storing the function for getting the ID value of the current user.
   *
   * @author Oleg Schildt 
   */
  protected $user_id_getter;

  /**
   * @var string
   * Internal variable for storing the current context.
   *
   * @see getContext()
   * @see setContext()
   *
   * @author Oleg Schildt 
   */
  protected $context = "default";

  /**
   * @var \SmartFactory\Interfaces\ISettingsValidator
   * Internal variable for storing the validator.
   *
   * @see getValidator()
   * @see setValidator()
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
    
    if(empty($this->user_table)) 
    {
      trigger_error("User table is not defined!", E_USER_ERROR);
      return false;
    }

    if(empty($this->settings_fields)) 
    {
      trigger_error("Settings fields are not defined!", E_USER_ERROR);
      return false;
    }
    
    if(!is_array($this->settings_fields)) 
    {
      trigger_error("Settings fields definition must be an array - field => type!", E_USER_ERROR);
      return false;
    }

    if(empty($this->user_id_field)) 
    {
      trigger_error("User id field is not defined!", E_USER_ERROR);
      return false;
    }

    if(empty($this->user_id_getter)) 
    {
      trigger_error("Function for user id retrieval is not defined!", E_USER_ERROR);
      return false;
    }

    return true;
  } // basic_check

  /**
   * This is internal auxiliary function for getting where clause for getting and updating 
   * the user record.
   *
   * @return string
   * Returns the where clause for getting and updating the user record.
   *
   * @author Oleg Schildt 
   */
  protected function getWhereClause()
  {
    $where = "WHERE\n";
    
    $value = $this->user_id_getter->invoke();
    
    if(empty($value) && (string)$value != "0")
    {
      $where .= $this->user_id_field . " IS NULL";
    }
    else switch(checkempty($this->settings_fields[$this->user_id_field]))
    {
      case DBWorker::db_number:
      $where .= $this->user_id_field . " = " . $value;
      break;

      case DBWorker::db_datetime:
      $where .= $this->user_id_field . " = '" . $this->dbworker->format_datetime($value) . "'";
      break;

      case DBWorker::db_date:
      $where .= $this->user_id_field . " = '" . $this->dbworker->format_date($value) . "'";
      break;
      
      default:
      $where .= $this->user_id_field . " = '" . $value . "'";
    }
    
    return $where;
  } // getWhereClause

  /**
   * This is internal auxiliary function for storing the settings
   * to the target user table defined by the iniailization.
   *
   * @param array $data 
   * The array with the settings values to be saved.
   *
   * @return boolean
   * Returns true if the data has been successfully saved, otherwise false.
   *
   * @see loadSettingsData()
   *
   * @author Oleg Schildt 
   */
  protected function saveSettingsData(&$data)
  {
    $update_string = "";
    
    foreach($this->settings_fields as $field => $type)
    {
      if($field == $this->user_id_field) continue;        
      
      $value = $this->dbworker->escape(checkempty($data[$field]));
      
      if(empty($value) && (string)$value != "0")
      {
        $update_string .= $field . " = NULL,\n";
      }
      else switch($type)
      {
        case DBWorker::db_number:
        $update_string .= $field . " = " . $value . ",\n";
        break;

        case DBWorker::db_datetime:
        $update_string .= $field . " = '" . $this->dbworker->format_datetime($value) . "',\n";
        break;

        case DBWorker::db_date:
        $update_string .= $field . " = '" . $this->dbworker->format_date($value) . "',\n";
        break;
        
        default:
        $update_string .= $field . " = '" . $value . "',\n";
      }
    }

    $query  = "UPDATE " . $this->user_table . " SET\n";
    $query .= trim($update_string, ",\n") . "\n";
    $query .= $this->getWhereClause();
    
    if(!$this->dbworker->execute_query($query))
    {
      return sql_error($this->dbworker);
    }
    
    return true;
  } // saveSettingsData

  /**
   * This is internal auxiliary function for loading the settings from the target user table 
   * defined by the iniailization.
   *
   * @param array $data 
   * The target array with the settings values to be loaded.
   *
   * @return boolean
   * Returns true if the data has been successfully loaded, otherwise false.
   *
   * @see saveSettingsData()
   *
   * @author Oleg Schildt 
   */
  protected function loadSettingsData(&$data)
  {
    $query = "SELECT\n";
    
    $query .= implode(", ", array_keys($this->settings_fields)) . "\n";
    
    $query .= "FROM " . $this->user_table . "\n";

    $query .= $this->getWhereClause();

    if(!$this->dbworker->execute_query($query))
    {
      return sql_error($this->dbworker);
    }
    
    if($this->dbworker->fetch_row())
    {
      foreach($this->settings_fields as $field => $type)
      {
        $data[$field] = $this->dbworker->field_by_name($field);
        
        if($type == DBWorker::db_date || $type == DBWorker::db_datetime) $data[$field] = strtotime($data[$field]);
      }
    }

    $this->dbworker->free_result();
    
    return true;
  } // loadSettingsData

  /**
   * Default constructor.
   *
   * @author Oleg Schildt
   */
  public function __construct()
  {
    $this->settings = &session()->vars()["__user_settings"];
    $this->temp_settings = &session()->vars()["__temp_user_settings"];
  } // __construct

  /**
   * Initializes the settings manager parameters.
   * 
   * @param array $parameters 
   * Settings for saving ad loading as an associative array in the form key => value:
   *
   * - $parameters["dbworker"] - the dbworker to used for loading and storing settings.
   *
   * - $parameters["user_table"] - the name of the user table for the storing of the settings.
   *
   * - $parameters["settings_fields"] - the array of the fields for saving each setting.
   *
   * - $parameters["user_id_field"] - the name of the user ID field for identifzing the user record.
   *
   * - $parameters["user_id_getter"] - the function for getting the ID of the current user.
   *
   * Example:
   *
   * ```
   *   $usmanager->init(["dbworker" => dbworker(),
                         "user_table" => "USERS",
                         "settings_fields" => [
                         "ID" => DBWorker::db_number,
                         "SIGNATURE" => DBWorker::db_string,
                         "STATUS" => DBWorker::db_string,
                         "HIDE_PICTURES" => DBWorker::db_number,
                         "HIDE_SIGNATURES" => DBWorker::db_number,
                         "LANGUAGE" => DBWorker::db_string,
                         "TIME_ZONE" => DBWorker::db_string
                        ],
                        "user_id_field" => "ID",
                        "user_id_getter" => function() { return 1; }
                       ]);
   * ```
   *
   * @return boolean 
   * Returns true upon successful initialization, otherwise false.   
   *
   * @author Oleg Schildt 
   */
  public function init($parameters)
  {
    if(!empty($parameters["dbworker"])) $this->dbworker = $parameters["dbworker"];

    if(!empty($parameters["user_table"])) $this->user_table = $parameters["user_table"];
    if(!empty($parameters["settings_fields"])) $this->settings_fields = $parameters["settings_fields"];
    if(!empty($parameters["user_id_field"])) $this->user_id_field = $parameters["user_id_field"];
    
    if(!empty($parameters["user_id_getter"])) 
    {
      if(!is_callable($parameters["user_id_getter"])) throw new \Exception(sprintf("'%s' is not a function!", $parameters["user_id_getter"]));
      
      $this->user_id_getter = new \ReflectionFunction($parameters["user_id_getter"]);
    }
    
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
   * @return \SmartFactory\Interfaces\ISettingsValidator|null  
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
   * @uses Interfaces\ISettingsValidator
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
   * Loads the settings from the target user table.
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

    // user settings are loaded once per session and
    // maintained in the session.    
    
    if(!empty($this->temp_settings["__loaded"])) return true;
    
    if(!$this->loadSettingsData($this->settings)) return false;
    
    $this->temp_settings = $this->settings;

    unset($this->temp_settings["__dirty"]);
    
    $this->temp_settings["__loaded"] = true;
    
    return true;
  } // loadSettings

  /**
   * Saves the settings from to the target user table.
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
    
    if($this->saveSettingsData($this->temp_settings))
    {
      $this->settings = $this->temp_settings;
      return true;
    }
     
    $this->temp_settings["__dirty"] = $old_dirty_state;
    
    return false;  
  } // saveSettings
} // UserSettingsManager
