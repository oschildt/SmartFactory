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
 * @see  ConfigSettingsManager
 * @see  RuntimeSettingsManager
 *
 * @uses DatabaseWorkers\DBWorker
 *
 * @author Oleg Schildt
 */
class UserSettingsManager implements ISettingsManager
{
    /**
     * Internal variable for storing the user id.
     *
     * @var string
     *
     * @see setUserID()
     *
     * @author Oleg Schildt
     */
    protected $user_id = "";
    
    /**
     * Internal variable for storing the dbworker.
     *
     * @var DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    protected $dbworker;
    
    /**
     * Internal variable for storing the target user table name.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected $user_table;
    
    /**
     * Internal array for storing the target colums for each settings.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $settings_fields;
    
    /**
     * Internal variable for storing the field name that identifies the user record.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected $user_id_field;
    
    /**
     * Internal variable for storing the function for getting the ID value of the current user.
     *
     * @var callable
     *
     * @author Oleg Schildt
     */
    protected $user_id_getter;
    
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
     * @var \SmartFactory\Interfaces\ISettingsValidator
     *
     * @see getValidator()
     * @see setValidator()
     *
     * @author Oleg Schildt
     */
    protected $validator = null;
    
    /**
     * Internal variable for storing the array of changed settings values.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $settings = [];
    
    /**
     * This is internal auxiliary function for checking that the settings
     * manager is intialized correctly.
     *
     * @return boolean
     * It should return true if the settings manager is intialized correctly, otherwise false.
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     *
     * @author Oleg Schildt
     */
    protected function validateParameters()
    {
        if (empty($this->dbworker)) {
            throw new \Exception("The 'dbworker' is not specified!");
        }
        
        if (!$this->dbworker instanceof DBWorker) {
            throw new \Exception(sprintf("The 'dbworker' does not extends the class '%s'!", DBWorker::class));
        }
        
        if (empty($this->user_table)) {
            throw new \Exception("The 'user_table' is not defined!");
        }
        
        if (empty($this->settings_fields)) {
            throw new \Exception("The 'settings_fields' are not defined!");
        }
        
        if (empty($this->user_id_field)) {
            throw new \Exception("The 'user_id_field' is not defined!");
        }
        
        if (!is_array($this->settings_fields)) {
            throw new \Exception("Settings fields 'settings_fields' must be an array - field => type!");
        }
        
        return true;
    } // validateParameters
    
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
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if the query fails or if some object names are invalid.
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see loadSettingsData()
     *
     * @author Oleg Schildt
     */
    protected function saveSettingsData(&$data)
    {
        $this->validateParameters();
        
        $simple_fields = [];
        $multichoice_fields = [];
        
        foreach ($this->settings_fields as $field => $type) {
            if (is_array($type)) {
                $multichoice_fields[$field] = $type;
            } else {
                $simple_fields[$field] = $type;
            }
        }
        
        $update_string = "";
        
        foreach ($simple_fields as $field => $type) {
            if ($field == $this->user_id_field) {
                continue;
            }
            
            $value = $this->dbworker->prepare_for_query(checkempty($data[$field]), $type);
            
            $update_string .= $field . " = " . $value . ",\n";
        }
    
        $this->dbworker->start_transaction();
        
        try {
            // update the main table
            
            $query = "UPDATE " . $this->user_table . " SET\n";
            $query .= trim($update_string, ",\n") . "\n";
            
            $user_id = $this->dbworker->prepare_for_query($this->user_id, checkempty($this->settings_fields[$this->user_id_field]));
            $query .= "WHERE " . $this->user_id_field . " = " . $user_id;
            
            $this->dbworker->execute_query($query);
    
            // update the subtables

            foreach ($multichoice_fields as $table => $tdata) {
                // $tdata[0] - user id field
                // $tdata[1] - value field
                // $tdata[2] - value field type
                
                if (empty($tdata[0]) || empty($tdata[1] || empty($tdata[2]))) {
                    throw new \Exception(sprintf("The multichoice field '%s' is defined incorrectly! It must be an array ['name of user id column', 'name of the value column', type of the value column].", $table));
                }
                
                if (!empty($data[$table])) {
                    $value = $data[$table];
                } else {
                    $value = [];
                }
                
                // insert the values that are in the list but not in the table
                
                $in_list = "";
                foreach ($value as $entry) {
                    if (empty($entry)) {
                        continue;
                    }
                    
                    $entry = $this->dbworker->prepare_for_query($entry, $tdata[2]);
                    
                    $query = "SELECT 1 FROM $table WHERE $tdata[0] = $user_id AND $tdata[1] = $entry";
                    
                    $this->dbworker->execute_query($query);
                    
                    $must_insert = true;
                    if ($this->dbworker->fetch_row()) {
                        $must_insert = false;
                    }
                    
                    $this->dbworker->free_result();
                    
                    if ($must_insert) {
                        $query = "INSERT INTO $table ($tdata[0], $tdata[1]) VALUES ($user_id, $entry)";
                        
                        $this->dbworker->execute_query($query);
                    }
                    
                    $in_list .= $entry . ",\n";
                }
                
                $where = "WHERE " . $tdata[0] . " = " . $user_id;
                
                $in_list = trim($in_list, " ,\n\r");
                
                // delete the values that are no more in the new list but still in the table.
                
                if (empty($in_list)) {
                    $query = "DELETE FROM $table\n" . $where;
                } else {
                    $query = "DELETE FROM $table\n" . $where . " AND " . $tdata[1] . " NOT IN ($in_list)";
                }
                
                $this->dbworker->execute_query($query);
            }
        } catch (\Exception $ex) {
            $this->dbworker->rollback_transaction();
            throw $ex;
        }

        $this->dbworker->commit_transaction();
        
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
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if the query fails or if some object names are invalid.
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see saveSettingsData()
     *
     * @author Oleg Schildt
     */
    protected function loadSettingsData(&$data)
    {
        $this->validateParameters();
        
        $simple_fields = [];
        $multichoice_fields = [];
        
        foreach ($this->settings_fields as $field => $type) {
            if (is_array($type)) {
                $multichoice_fields[$field] = $type;
            } else {
                $simple_fields[$field] = $type;
            }
        }
        
        $query = "SELECT\n";
        
        $query .= implode(", ", array_keys($simple_fields)) . "\n";
        
        $query .= "FROM " . $this->user_table . "\n";
        
        $user_id = $this->dbworker->prepare_for_query($this->user_id, checkempty($this->settings_fields[$this->user_id_field]));
        $query .= "WHERE " . $this->user_id_field . " = " . $user_id;
        
        $this->dbworker->execute_query($query);
        
        if ($this->dbworker->fetch_row()) {
            foreach ($simple_fields as $field => $type) {
                $data[$field] = $this->dbworker->field_by_name($field);
                
                if (($type == DBWorker::DB_DATE || $type == DBWorker::DB_DATETIME) && !empty($data[$field])) {
                    $data[$field] = strtotime($data[$field]);
                }
            }
        }
        
        $this->dbworker->free_result();
        
        foreach ($multichoice_fields as $table => $tdata) {
            // $tdata[0] - user id field
            // $tdata[1] - value field
            // $tdata[2] - value field type
            
            if (empty($tdata[0]) || empty($tdata[1] || empty($tdata[2]))) {
                throw new \Exception(sprintf("The multichoice field '%s' is defined incorrectly! It must be an array ['name of user id column', 'name of the value column', type of the value column].", $table));
            }
            
            $query = "SELECT $tdata[1]\n";
            
            $query .= "FROM " . $table . "\n";
            
            $query .= "WHERE " . $tdata[0] . " = " . $user_id;
            
            $this->dbworker->execute_query($query);
            
            while ($this->dbworker->fetch_row()) {
                $val = $this->dbworker->field_by_name($tdata[1]);
                
                if (($tdata[2] == DBWorker::DB_DATE || $tdata[2] == DBWorker::DB_DATETIME) && !empty($val)) {
                    $val = strtotime($val);
                }
                
                $data[$table][] = $val;
            }
            
            $this->dbworker->free_result();
        }
        
        return true;
    } // loadSettingsData
    
    /**
     * Initializes the settings manager parameters.
     *
     * @param array $parameters
     * Settings for saving and loading as an associative array in the form key => value:
     *
     * - $parameters["dbworker"] - the dbworker to used for loading and storing settings.
     *
     * - $parameters["user_table"] - the name of the user table for the storing of the settings.
     *
     * - $parameters["settings_fields"] - the array of the fields for saving each setting.
     *
     * - $parameters["user_id_field"] - the name of the user ID field for identifzing the user record.
     *
     * Example:
     *
     * ```php
     *   $usmanager->init(["dbworker" => dbworker(),
     *                     "user_table" => "USERS",
     *                     "settings_fields" => [
     *                     "ID" => DBWorker::DB_NUMBER,
     *                     "SIGNATURE" => DBWorker::DB_STRING,
     *                     "STATUS" => DBWorker::DB_STRING,
     *                     "HIDE_PICTURES" => DBWorker::DB_NUMBER,
     *                     "HIDE_SIGNATURES" => DBWorker::DB_NUMBER,
     *                     "LANGUAGE" => DBWorker::DB_STRING,
     *                     "TIME_ZONE" => DBWorker::DB_STRING
     *                    ],
     *                    "user_id_field" => "ID"
     *                   ]);
     * ```
     *
     * @return boolean
     * Returns true upon successful initialization, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function init($parameters)
    {
        if (!empty($parameters["dbworker"])) {
            $this->dbworker = $parameters["dbworker"];
        }
        
        if (!empty($parameters["user_table"])) {
            $this->user_table = $parameters["user_table"];
        }
        if (!empty($parameters["settings_fields"])) {
            $this->settings_fields = $parameters["settings_fields"];
        }
        if (!empty($parameters["user_id_field"])) {
            $this->user_id_field = $parameters["user_id_field"];
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
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see getParameter()
     *
     * @author Oleg Schildt
     */
    public function setParameter($name, $value)
    {
        if (empty($this->settings)) {
            $this->loadSettings();
        }
        
        $this->settings[$name] = $value;
    } // setParameter
    
    /**
     * Returns the value of a settings parameter.
     *
     * @param string $name
     * The name of the settings parameter.
     *
     * @param mixed $default
     * The default value of the settings parameter if it is not set yet.
     * The parameter is a confortable way to pre-set a parameter
     * to a default value if its value is not set yet.
     *
     * @return mixed
     * Returns the value of the settings parameter.
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see setParameter()
     *
     * @author Oleg Schildt
     */
    public function getParameter($name, $default = null)
    {
        if (empty($this->settings)) {
            $this->loadSettings();
        }
        
        if (!isset($this->settings[$name])) {
            return $default;
        }
        
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
     * @see  getValidator()
     * @see  setValidator()
     *
     * @author Oleg Schildt
     */
    public function validateSettings()
    {
        if (empty($this->validator)) {
            return true;
        }
        
        return $this->validator->validate($this, $this->context);
    } // validateSettings
    
    /**
     * Loads the settings from the target user table.
     *
     * The user ID must be set before loading settings, see {@see \SmartFactory\UserSettingsManager::setUserID()}.
     *
     * @return boolean
     * Returns true if the settings have been successfully loaded, otherwise false.
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see saveSettings()
     *
     * @author Oleg Schildt
     */
    public function loadSettings()
    {
        return $this->loadSettingsData($this->settings);
    } // loadSettings
    
    /**
     * Saves the settings from to the target user table.
     *
     * @return boolean
     * Returns true if the settings have been successfully saved, otherwise false.
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see loadSettings()
     *
     * @author Oleg Schildt
     */
    public function saveSettings()
    {
        if (empty($this->settings)) {
            $this->loadSettings();
        }
        
        return $this->saveSettingsData($this->settings);
    } // saveSettings
    
    /**
     * Sets the user id to be used for loading settings.
     *
     * The user ID must be set before loading settings, see {@see \SmartFactory\UserSettingsManager::loadSettings()}.
     *
     * @param string $user_id
     * The user ID.
     *
     * @see loadSettings()
     *
     * @author Oleg Schildt
     */
    public function setUserID($user_id)
    {
        $this->user_id = $user_id;
    } // setUserID
} // UserSettingsManager
