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
     * @see UserSettingsManager::setUserID()
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
     * Internal array for storing the settings tables.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $settings_tables;

    /**
     * Internal array for storing the auxiliary tables for storing the multichoce values.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $multichoice_tables;

    /**
     * Internal variable for storing the current context.
     *
     * @var string
     *
     * @see UserSettingsManager::getContext()
     * @see UserSettingsManager::setContext()
     *
     * @author Oleg Schildt
     */
    protected $context = "default";

    /**
     * Internal variable for storing the validator.
     *
     * @var \SmartFactory\Interfaces\ISettingsValidator
     *
     * @see UserSettingsManager::getValidator()
     * @see UserSettingsManager::setValidator()
     *
     * @author Oleg Schildt
     */
    protected $validator = null;

    /**
     * Internal variable for storing the array of the settings values.
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
     * @return void
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

        if (empty($this->settings_tables)) {
            throw new \Exception("The 'settings_tables' are not defined!");
        }

        if (!is_array($this->settings_tables)) {
            throw new \Exception("Settings tables 'settings_tables' must be an array!");
        }

        if (!empty($this->multichoice_tables) && !is_array($this->multichoice_tables)) {
            throw new \Exception("Multichoice tables 'multichoice_tables' must be an array!");
        }
    } // validateParameters

    /**
     * This is internal auxiliary function for storing the settings
     * to the target user table defined by the iniailization.
     *
     * @param array &$data
     * The array with the settings values to be saved.
     *
     * @return void
     *
     * @throws \Throwable
     * It might throw an exception in the case of any errors:
     *
     * - if the query fails or if some object names are invalid.
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see UserSettingsManager::loadSettingsData()
     *
     * @author Oleg Schildt
     */
    protected function saveSettingsData(&$data)
    {
        $this->validateParameters();

        $this->dbworker->start_transaction();

        try {
            foreach ($this->settings_tables as $table => $fields) {
                // First field is the ID field
                $uid_field = "";
                $uid_field_type = "";

                $update_string = "";

                $c = 0;
                foreach ($fields as $field => $field_type) {
                    $c++;

                    if ($c == 1) {
                        $uid_field = $field;
                        $uid_field_type = $field_type;
                        continue;
                    }

                    $value = "";

                    if (!empty($data[$table . "." . $field])) {
                        $value = $data[$table . "." . $field];
                    }

                    if (!empty($data[$field])) {
                        $value = $data[$field];
                    }

                    $value = $this->dbworker->prepare_for_query($value, $field_type);

                    $update_string .= $field . " = " . $value . ",\n";
                }

                $query = "update " . $table . " set\n";
                $query .= trim($update_string, ",\n") . "\n";

                $user_id = $this->dbworker->prepare_for_query($this->user_id, $uid_field_type);
                $query .= "where " . $uid_field . " = " . $user_id;

                $this->dbworker->execute_query($query);
            }

            // update the subtables

            foreach ($this->multichoice_tables as $table => $fields) {
                // First field is the ID field
                $uid_field = "";
                $uid_field_type = "";

                // Second field is the value field
                $value_field = "";
                $value_field_type = "";

                $c = 1;
                foreach ($fields as $field => $field_type) {
                    if ($c == 1) {
                        $uid_field = $field;
                        $uid_field_type = $field_type;
                    } elseif ($c == 2) {
                        $value_field = $field;
                        $value_field_type = $field_type;
                    } else {
                        break;
                    }

                    $c++;
                }

                if (!empty($data[$table])) {
                    $value = $data[$table];
                } else {
                    $value = [];
                }

                // insert the values that are in the list but not in the table

                $in_list = "";
                $user_id = $this->dbworker->prepare_for_query($this->user_id, $uid_field_type);

                foreach ($value as $entry) {
                    if (empty($entry)) {
                        continue;
                    }

                    $entry = $this->dbworker->prepare_for_query($entry, $value_field_type);

                    $query = "select 1 from $table where $uid_field = $user_id";

                    if ($value == "NULL") {
                        $query .= " and $value_field is NULL";
                    } else {
                        $query .= " and $value_field = $entry";
                    }

                    $this->dbworker->execute_query($query);

                    $must_insert = true;
                    if ($this->dbworker->fetch_row()) {
                        $must_insert = false;
                    }

                    $this->dbworker->free_result();

                    if ($must_insert) {
                        $query = "insert into $table ($uid_field, $value_field) values ($user_id, $entry)";

                        $this->dbworker->execute_query($query);
                    }

                    $in_list .= $entry . ",\n";
                }

                $in_list = trim($in_list, " ,\n\r");

                $where = "where " . $uid_field . " = " . $user_id;

                // delete the values that are no more in the new list but still in the table.

                if (empty($in_list)) {
                    $query = "delete from $table\n" . $where;
                } else {
                    $query = "delete from $table\n" . $where . " and " . $value_field . " not in ($in_list)";
                }

                $this->dbworker->execute_query($query);
            }
        } catch (\Throwable $ex) {
            $this->dbworker->rollback_transaction();
            throw $ex;
        }

        $this->dbworker->commit_transaction();
    } // saveSettingsData

    /**
     * This is internal auxiliary function for loading the settings from the target user table
     * defined by the iniailization.
     *
     * @param array &$data
     * The target array with the settings values to be loaded.
     *
     * @return void
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
     * @see UserSettingsManager::saveSettingsData()
     *
     * @author Oleg Schildt
     */
    protected function loadSettingsData(&$data)
    {
        $this->validateParameters();

        foreach ($this->settings_tables as $table => $fields) {
            // First field is the ID field
            $uid_field = "";
            $uid_field_type = "";

            foreach ($fields as $field => $field_type) {
                $uid_field = $field;
                $uid_field_type = $field_type;
                break;
            }

            $query = "select\n";

            $query .= implode(", ", array_keys($fields)) . "\n";

            $query .= "from " . $table . "\n";

            $user_id = $this->dbworker->prepare_for_query($this->user_id, $uid_field_type);
            $query .= "where " . $uid_field . " = " . $user_id;

            $this->dbworker->execute_query($query);

            if ($this->dbworker->fetch_row()) {
                foreach ($fields as $field => $type) {
                    $data[$field] = $this->dbworker->field_by_name($field, $type); // short name (only field name)
                    $data[$table . "." . $field] = $data[$field]; // full name (table name and field name)

                }
            }

            $this->dbworker->free_result();
        }

        if (empty($this->multichoice_tables)) {
            return;
        }

        foreach ($this->multichoice_tables as $table => $fields) {
            // First field is the ID field
            $uid_field = "";
            $uid_field_type = "";

            // Second field is the value field
            $value_field = "";
            $value_field_type = "";

            $c = 1;
            foreach ($fields as $field => $field_type) {
                if ($c == 1) {
                    $uid_field = $field;
                    $uid_field_type = $field_type;
                } elseif ($c == 2) {
                    $value_field = $field;
                    $value_field_type = $field_type;
                } else {
                    break;
                }

                $c++;
            }

            $query = "select " . $value_field . "\n";

            $query .= "from " . $table . "\n";

            $user_id = $this->dbworker->prepare_for_query($this->user_id, $uid_field_type);
            $query .= "where " . $uid_field . " = " . $user_id;

            $this->dbworker->execute_query($query);

            $data[$table] = [];

            while ($this->dbworker->fetch_row()) {
                $data[$table][] = $this->dbworker->field_by_name($value_field, $value_field_type);
            }

            $this->dbworker->free_result();
        }
    } // loadSettingsData

    /**
     * Initializes the settings manager parameters.
     *
     * @param array $parameters
     * Settings for saving and loading as an associative array in the form key => value:
     *
     * - $parameters["dbworker"] - the dbworker to used for loading and storing settings.
     * - $parameters["settings_tables"] - the definitions of the settings tables.
     * - $parameters["multichoice_tables"] - the definitions of the auxiliary tables for the multichoice values.
     *
     * Example:
     *
     * ```php
     *   $instance->init([
     *           "dbworker" => app_dbworker(),
     *
     *           "settings_tables" => [
     *                "users" => [
     *                    "id" => DBWorker::DB_NUMBER,
     *                    "language" => DBWorker::DB_STRING,
     *                    "time_zone" => DBWorker::DB_STRING
     *                ],
     *                "user_forum_settings" => [
     *                    "user_id" => DBWorker::DB_NUMBER,
     *                    "signature" => DBWorker::DB_STRING,
     *                    "status" => DBWorker::DB_STRING,
     *                    "hide_pictures" => DBWorker::DB_NUMBER,
     *                    "hide_signatures" => DBWorker::DB_NUMBER
     *                ]
     *           ],
     *
     *           "multichoice_tables" => [
     *                "user_colors" => [
     *                    "user_id" => DBWorker::DB_NUMBER,
     *                    "color" => DBWorker::DB_STRING
     *                ]
     *           ]
     *   ]);
     * ```
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function init($parameters)
    {
        if (!empty($parameters["dbworker"])) {
            $this->dbworker = $parameters["dbworker"];
        }

        if (!empty($parameters["settings_tables"])) {
            $this->settings_tables = $parameters["settings_tables"];
        }
        if (!empty($parameters["multichoice_tables"])) {
            $this->multichoice_tables = $parameters["multichoice_tables"];
        }

        $this->validateParameters();
    } // init

    /**
     * Sets the validator for the settings.
     *
     * @param \SmartFactory\Interfaces\ISettingsValidator $validator
     * The settings validator.
     *
     * @return void
     *
     * @see UserSettingsManager::getValidator()
     * @see UserSettingsManager::validateSettings()
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
     * @see UserSettingsManager::setValidator()
     * @see UserSettingsManager::validateSettings()
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
     * @see UserSettingsManager::getContext()
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
     * @see UserSettingsManager::setContext()
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
     * @see UserSettingsManager::getParameter()
     * @see UserSettingsManager::setParameters()
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
     * Sets settings parameters from an array.
     *
     * @param array &$parameters
     * Array of parameters in the form key => value.
     *
     * @param boolean $force_creation
     * Flag which defines whether the parameter should be created
     * if not exists. If false, only existing parameters are updated.
     * In the UserSettingsManager, this pflag is ignored, because
     * the paramters are mapped to the database fields and cannot be created
     * on the fly.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if the query fails or if some object names are invalid.
     * - if the config file is not readable.
     *
     * @see UserSettingsManager::getParameter()
     * @see UserSettingsManager::setParameter()
     *
     * @author Oleg Schildt
     */
    public function setParameters(&$parameters, $force_creation = false)
    {
        if (empty($this->settings)) {
            $this->loadSettings();
        }

        foreach ($parameters as $key => $val) {
            if (!array_key_exists($key, $this->settings)) {
                continue;
            }

            $this->settings[$key] = $val;
        }
    } // setParameters

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
     * @see UserSettingsManager::setParameter()
     * @see UserSettingsManager::setParameters()
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
     * @see  UserSettingsManager::getValidator()
     * @see  UserSettingsManager::setValidator()
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
     * @see UserSettingsManager::saveSettings()
     *
     * @author Oleg Schildt
     */
    public function loadSettings()
    {
        $this->loadSettingsData($this->settings);
    } // loadSettings

    /**
     * Saves the settings from to the target user table.
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
     * @see UserSettingsManager::loadSettings()
     *
     * @author Oleg Schildt
     */
    public function saveSettings()
    {
        if (empty($this->settings)) {
            $this->loadSettings();
        }

        $this->saveSettingsData($this->settings);
    } // saveSettings

    /**
     * Sets the user id to be used for loading settings.
     *
     * The user ID must be set before loading settings, see {@see \SmartFactory\UserSettingsManager::loadSettings()}.
     *
     * @param string $user_id
     * The user ID.
     *
     * @return void
     *
     * @see UserSettingsManager::loadSettings()
     *
     * @see UserSettingsManager::getUserID()
     * @author Oleg Schildt
     */
    public function setUserID($user_id)
    {
        $this->user_id = $user_id;
    } // setUserID

    /**
     * Gets the user id to be used for loading settings.
     *
     * @return int|null
     * Returns the user id or null if not set.
     *
     * @see UserSettingsManager::setUserID()
     *
     * @author Oleg Schildt
     */
    public function getUserID()
    {
        return $this->user_id;
    } // getUserID
} // UserSettingsManager
