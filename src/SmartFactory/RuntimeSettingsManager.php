<?php
/**
 * This file contains the implementation of the interface ISettingsManager
 * in the class RuntimeSettingsManager for management of the
 * application settings.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use \SmartFactory\Interfaces\ISettingsManager;
use \SmartFactory\DatabaseWorkers\DBWorker;
use SmartFactory\Interfaces\ISettingsValidator;

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
class RuntimeSettingsManager implements ISettingsManager
{
    /**
     * Internal variable for storing the dbworker.
     *
     * @var ?DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    protected ?DatabaseWorkers\DBWorker $dbworker = null;
    
    /**
     * Internal variable for storing the target settings table name.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $settings_table;
    
    /**
     * Internal variable for storing the target column.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $settings_column;
    
    /**
     * Internal variable for storing the current context.
     *
     * @var string
     *
     * @see RuntimeSettingsManager::getContext()
     * @see RuntimeSettingsManager::setContext()
     *
     * @author Oleg Schildt
     */
    protected string $context = "default";
    
    /**
     * Internal variable for storing the validator.
     *
     * @var ?Interfaces\ISettingsValidator
     *
     * @see RuntimeSettingsManager::getValidator()
     * @see RuntimeSettingsManager::setValidator()
     *
     * @author Oleg Schildt
     */
    protected ?Interfaces\ISettingsValidator $validator = null;
    
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
     * manager is initialized correctly.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
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
        
        if (empty($this->settings_table)) {
            throw new \Exception("The 'settings_table' is not specified!");
        }
        
        if (empty($this->settings_column)) {
            throw new \Exception("The 'settings_column' is not specified!");
        }
    } // validateParameters
    
    /**
     * This is internal auxiliary function for converting the settings to JSON and storing it
     * to the target table defined by the initialization.
     *
     * @param array &$data
     * The array with the settings values to be saved.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if the query fails or if some object names are invalid.
     *
     * @see RuntimeSettingsManager::loadJSON()
     *
     * @author Oleg Schildt
     */
    protected function saveJSON(array &$data): void
    {
        $this->validateParameters();
        
        $json = array_to_json($data);
        
        // check existance
        
        $query = "SELECT 1 FROM " . $this->settings_table;
        
        $this->dbworker->execute_query($query);
        
        $must_insert = true;
        
        if ($this->dbworker->fetch_row()) {
            $must_insert = false;
        }
        
        $this->dbworker->free_result();
        
        $json = $this->dbworker->escape($json);
        
        if ($must_insert) {
            $query = "insert into " . $this->settings_table . "(" . $this->settings_column . ")\n";
            $query .= "values ('$json')";
        } else {
            $query = "update " . $this->settings_table . " set " . $this->settings_column . " = '$json'";
        }
        
        $this->dbworker->execute_query($query);
    } // saveJSON
    
    /**
     * This is internal auxiliary function for loading the settings from the target table
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
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if the query fails or if some object names are invalid.
     * - if the json is invalid.
     *
     * @see RuntimeSettingsManager::saveJSON()
     *
     * @author Oleg Schildt
     */
    protected function loadJSON(array &$data): void
    {
        $this->validateParameters();
        
        $query = "select " . $this->settings_column . " from " . $this->settings_table;
        
        $this->dbworker->execute_query($query);
        
        $json = "";
        
        if ($this->dbworker->fetch_row()) {
            $json = $this->dbworker->field_by_name($this->settings_column);
        }
        
        $this->dbworker->free_result();
        
        if (empty($json)) {
            return;
        }
        
        try {
            json_to_array($json, $data);
        } catch (\Throwable $ex) {
            throw new \Exception("JSON parse error: " . $ex->getMessage());
        }
    } // loadJSON
    
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
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void
    {
        if (!empty($parameters["dbworker"])) {
            $this->dbworker = $parameters["dbworker"];
        }
        
        if (!empty($parameters["settings_table"])) {
            $this->settings_table = $parameters["settings_table"];
        }
        
        if (!empty($parameters["settings_column"])) {
            $this->settings_column = $parameters["settings_column"];
        }
        
        $this->validateParameters();
    } // init
    
    /**
     * Sets the validator for the settings.
     *
     * @param ISettingsValidator $validator
     * The settings validator.
     *
     * @return void
     *
     * @see RuntimeSettingsManager::getValidator()
     * @see RuntimeSettingsManager::validateSettings()
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
     * @return ?Interfaces\ISettingsValidator
     * Returns the validator for the settings or null if none is defined.
     *
     * @see RuntimeSettingsManager::setValidator()
     * @see RuntimeSettingsManager::validateSettings()
     *
     * @author Oleg Schildt
     */
    public function getValidator(): ?Interfaces\ISettingsValidator
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
     * @see RuntimeSettingsManager::getContext()
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
     * @see RuntimeSettingsManager::setContext()
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
     * - if the query fails or if some object names are invalid.
     *
     * @see RuntimeSettingsManager::getParameter()
     * @see RuntimeSettingsManager::setParameters()
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
     * @see RuntimeSettingsManager::getParameter()
     * @see RuntimeSettingsManager::setParameter()
     *
     * @author Oleg Schildt
     */
    public function setParameters(array $parameters, bool $force_creation = false): void
    {
        if (empty($this->settings)) {
            $this->loadSettings();
        }
        
        foreach ($parameters as $key => $val) {
            if (!array_key_exists($key, $this->settings) && !$force_creation) {
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
     * - if the query fails or if some object names are invalid.
     *
     * @see RuntimeSettingsManager::setParameter()
     * @see RuntimeSettingsManager::setParameters()
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
     * @uses \SmartFactory\Interfaces\ISettingsValidator
     *
     * @see RuntimeSettingsManager::getValidator()
     * @see RuntimeSettingsManager::setValidator()
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
     * Loads the settings from the target table.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if the query fails or if some object names are invalid.
     *
     * @see RuntimeSettingsManager::saveSettings()
     *
     * @author Oleg Schildt
     */
    public function loadSettings(): void
    {
        $this->loadJSON($this->settings);
    } // loadSettings
    
    /**
     * Saves the settings from to the target table.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if the query fails or if some object names are invalid.
     *
     * @see RuntimeSettingsManager::loadSettings()
     *
     * @author Oleg Schildt
     */
    public function saveSettings(): void
    {
        if (empty($this->settings)) {
            $this->loadSettings();
        }

        $this->saveJSON($this->settings);
    } // saveSettings
} // RuntimeSettingsManager
