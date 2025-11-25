<?php
/**
 * This file contains the implementation of the interface ISettingsManager
 * in the class UserSettingsManager for management of the user settings.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use \SmartFactory\Interfaces\ISettingsManager;
use \SmartFactory\Interfaces\IErrorHandler;
use \SmartFactory\DatabaseWorkers\DBWorker;
use SmartFactory\Interfaces\ISettingsValidator;

/**
 * Class for management of the user settings.
 *
 * When you introduce a new user setting. You have to create a column for it in the corresponding
 * table add it to the initialization and specify the data type.
 *
 * The user settings are loaded only once by the start of the session and are kept until the session
 * is valid. Saving of the user settings updates both the session and the database. When a settings are
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
    protected string $user_id = "";

    /**
     * Internal variable for storing the dbworker.
     *
     * @var ?DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    protected ?DatabaseWorkers\DBWorker $dbworker;

    /**
     * Internal variable for storing the state whether the translation should be started or commited or
     * no transaction should be used because the saving process is a part of other saving process.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $no_own_transaction = true;

    /**
     * Internal array for storing the settings tables.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected array $settings_tables = [];

    /**
     * Internal array for storing the auxiliary tables for storing the multichoce values.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected array $multichoice_tables = [];

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
    protected string $context = "default";

    /**
     * Internal variable for storing the validator.
     *
     * @var ?\SmartFactory\Interfaces\ISettingsValidator
     *
     * @see UserSettingsManager::getValidator()
     * @see UserSettingsManager::setValidator()
     *
     * @author Oleg Schildt
     */
    protected ?\SmartFactory\Interfaces\ISettingsValidator $validator = null;

    /**
     * Internal variable for storing the array of the settings values.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected array $settings = [];

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
    protected function validateParameters(): void
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
     * to the target user table defined by the initialization.
     *
     * @param array $data
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
    protected function saveSettingsData(array $data): void
    {
        $this->validateParameters();

        $this->dbworker->connect();

        if (empty($this->no_own_transaction)) {
            $this->dbworker->start_transaction();
        }

        try {
            foreach ($this->settings_tables as $table => $fields) {
                // First field is the ID field
                $uid_field = "";
                $uid_field_type = "";

                $update_string = "";

                $insert_fields_string = "";
                $insert_values_string = "";

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

                    if (isset($data[$field])) {
                        $value = $data[$field];
                    }

                    $value = $this->dbworker->prepare_for_query($value, $field_type);

                    $update_string .= $field . " = " . $value . ",\n";

                    $insert_fields_string .= $field . ", ";
                    $insert_values_string .= $value . ", ";
                }

                $user_id = $this->dbworker->prepare_for_query($this->user_id, $uid_field_type);

                $insert_fields_string .= $uid_field . ", ";
                $insert_values_string .= $user_id . ", ";

                $query = "select\n";
                $query .= "1\n";
                $query .= "from " . $table . "\n";
                $query .= "where " . $uid_field . " = " . $user_id;

                $this->dbworker->execute_query($query);

                $must_insert = false;
                if (!$this->dbworker->fetch_row()) {
                    $must_insert = true;
                }

                $this->dbworker->free_result();

                if ($must_insert) {
                    $query = "insert into " . $table . "\n";
                    $query .= "(" . trim($insert_fields_string, ", ") . ")\n";
                    $query .= "values\n";
                    $query .= "(" . trim($insert_values_string, ", ") . ")\n";
                } else {
                    $query = "update " . $table . " set\n";
                    $query .= trim($update_string, ",\n") . "\n";
                    $query .= "where " . $uid_field . " = " . $user_id;
                }

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
            if (empty($this->no_own_transaction)) {
                $this->dbworker->rollback_transaction();
            }

            singleton(IErrorHandler::class)->handleException($ex, E_USER_ERROR);

            throw $ex;
        }

        if (empty($this->no_own_transaction)) {
            $this->dbworker->commit_transaction();
        }
    } // saveSettingsData

    /**
     * This is internal auxiliary function for loading the settings from the target user table
     * defined by the initialization.
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
    protected function loadSettingsData(array &$data): void
    {
        $this->validateParameters();

        $this->dbworker->connect();

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
     * - $parameters["dbworker"] - the dbworker to use for loading and storing settings.
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
    public function init(array $parameters): void
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
     * Sets the dbworker to be used for working with the database.
     *
     * @param DatabaseWorkers\DBWorker $dbworker
     * The dbworker to be used for working with the database.
     *
     * @return void
     *
     * @see RecordsetManager::getDBWorker()
     *
     * @author Oleg Schildt
     */

    /**
     * Sets the dbworker to use for loading and storing settings.
     *
     * @param DatabaseWorkers\DBWorker $dbworker
     * The dbworker to be used for loading and storing settings.
     *
     * @param bool $no_own_transcation
     * The state whether the translation should be started or commited or
     * no transaction should be used because the saving process is a part of other saving process.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     * @see RecordsetManager::getDBWorker()
     *
     */
    public function setDBWorker(DatabaseWorkers\DBWorker $dbworker, bool $no_own_transcation = false): void
    {
        $this->dbworker = $dbworker;
        $this->no_own_transaction = $no_own_transcation;

        if (empty($this->dbworker)) {
            throw new \Exception("The 'dbworker' is not specified!");
        }

        if (!$this->dbworker instanceof DBWorker) {
            throw new \Exception(sprintf("The 'dbworker' does not extends the class '%s'!", DBWorker::class));
        }
    } // setDBWorker

    /**
     * Sets the validator for the settings.
     *
     * @param ISettingsValidator $validator
     * The settings validator.
     *
     * @return void
     *
     * @see UserSettingsManager::getValidator()
     * @see UserSettingsManager::validateSettings()
     *
     * @author Oleg Schildt
     */
    public function setValidator(ISettingsValidator $validator): void
    {
        $this->validator = $validator;
    } // setValidator

    /**
     * Returns the validator for the settings.
     *
     * @return ?\SmartFactory\Interfaces\ISettingsValidator
     * Returns the validator for the settings or null if none is defined.
     *
     * @see UserSettingsManager::setValidator()
     * @see UserSettingsManager::validateSettings()
     *
     * @author Oleg Schildt
     */
    public function getValidator(): ?\SmartFactory\Interfaces\ISettingsValidator
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
    public function setContext(string $context = "default"): void
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
    public function getContext(): string
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
    public function setParameter(string $name, mixed $value): void
    {
        if (empty($this->settings)) {
            $this->loadSettings();
        }

        $this->settings[$name] = $value;
    } // setParameter

    /**
     * Sets settings parameters from an array.
     *
     * @param array $parameters
     * Array of parameters in the form key => value.
     *
     * @param bool $force_creation
     * Flag which defines whether the parameter should be created
     * if not exists. If false, only existing parameters are updated.
     * In the UserSettingsManager, this flag is ignored, because
     * the parameters are mapped to the database fields and cannot be created
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
    public function setParameters(array $parameters, bool $force_creation = false): void
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
     * @param mixed|null $default
     * The default value of the settings parameter if it is not set yet.
     * The parameter is a conformable way to pre-set a parameter
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
    public function getParameter(string $name, mixed $default = null): mixed
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
     * @return void
     *
     * @throws \Exception
     * It might throw exceptions in the case of any errors.
     *
     * @uses Interfaces\ISettingsValidator
     *
     * @see  UserSettingsManager::getValidator()
     * @see  UserSettingsManager::setValidator()
     *
     * @author Oleg Schildt
     */
    public function validateSettings(): void
    {
        if (empty($this->validator)) {
            return;
        }

        $this->validator->validate($this, $this->context);
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
    public function loadSettings(): void
    {
        $this->loadSettingsData($this->settings);
    } // loadSettings

    /**
     * Saves the settings from to the target user table.
     *
     * @return void
     *
     * @throws \Exception|\Throwable
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
    public function saveSettings(): void
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
    public function setUserID(string $user_id): void
    {
        $this->user_id = $user_id;
    } // setUserID

    /**
     * Gets the user id to be used for loading settings.
     *
     * @return int|string|null
     * Returns the user id or null if not set.
     *
     * @see UserSettingsManager::setUserID()
     *
     * @author Oleg Schildt
     */
    public function getUserID(): int|string|null
    {
        return $this->user_id;
    } // getUserID
} // UserSettingsManager
