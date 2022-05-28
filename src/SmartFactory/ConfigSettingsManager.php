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
 * Class for management of the config settings stored in a config JSON file.
 *
 * This settings manager supports the multistep wizard like processing. That means
 * that you do not have to put all settings in one big form and save them in one action.
 * You can collect them over multiple steps (requests), go forward ad back, validate them
 * on each step and finally save them after the final step.
 *
 * You do not need any preliminary special settings name definitions. When you introduce
 * a new setting, just start saving and getting it.
 *
 * @see RuntimeSettingsManager
 * @see UserSettingsManager
 *
 * @author Oleg Schildt
 */
class ConfigSettingsManager implements ISettingsManager
{
    /**
     * Internal variable that holds the target file path for storing the settings data.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected $save_path = null;
    
    /**
     * Internal variable for storing the state whether the data should be encrypted
     * before saving.
     *
     * @var boolean
     *
     * @author Oleg Schildt
     */
    protected $save_encrypted = false;
    
    /**
     * Internal variable for storing the salt key if the data should be encrypted
     * before saving.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected $salt_key = "default";
    
    /**
     * Internal variable for storing the state whether the config file must exist.
     *
     * @var boolean
     *
     * @author Oleg Schildt
     */
    protected $config_file_must_exist = false;
    
    /**
     * Internal variable for storing the state whether the APCU should be used.
     *
     * @var boolean
     *
     * @author Oleg Schildt
     */
    protected $use_apcu = false;
    
    /**
     * Internal variable for storing the current context.
     *
     * @var string
     *
     * @see ConfigSettingsManager::getContext()
     * @see ConfigSettingsManager::setContext()
     *
     * @author Oleg Schildt
     */
    protected $context = "default";
    
    /**
     * Internal variable for storing the validator.
     *
     * @var \SmartFactory\Interfaces\ISettingsValidator
     *
     * @see ConfigSettingsManager::getValidator()
     * @see ConfigSettingsManager::setValidator()
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
     * This is internal auxiliary function for converting the settings to JSON and storing it
     * to the target file defined by the iniailization.
     *
     * @param array &$data
     * The array with the settings values to be saved.
     *
     * @return boolean
     * Returns true if the data has been successfully saved, otherwise false.
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if the save path is not specified.
     * - if the config file is not writable.
     *
     * @see ConfigSettingsManager::loadJSON()
     *
     * @author Oleg Schildt
     */
    protected function saveJSON(&$data)
    {
        $this->validateParameters();
        
        $json = array_to_json($data);
        
        if (!empty($this->save_encrypted)) {
            $json = aes_256_encrypt($json, $this->salt_key);
        }
        
        if (file_put_contents($this->save_path, $json) === false) {
            throw new \Exception(sprintf("The config file '%s' cannot be written!", $this->save_path));
        }
        
        if ($this->use_apcu) {
            apcu_delete("config_settings");
        }
        
        return true;
    } // saveJSON
    
    /**
     * This is internal auxiliary function for loading the settings from the target file
     * defined by the iniailization.
     *
     * @param array &$data
     * The target array with the settings values to be loaded.
     *
     * @return boolean
     * Returns true if the data has been successfully loaded, otherwise false.
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if the save path is not specified.
     * - if the config file is not writable.
     * - if the config file is not readable.
     * - if the config file is invalid.
     *
     * @see ConfigSettingsManager::saveJSON()
     *
     * @author Oleg Schildt
     */
    protected function loadJSON(&$data)
    {
        if ($this->use_apcu && apcu_exists("config_settings")) {
            $data = apcu_fetch("config_settings");
            if (!empty($data)) {
                return true;
            }
        }
        
        $this->validateParameters();
        
        if (!file_exists($this->save_path) && empty($this->config_file_must_exist)) {
            return true;
        }
        
        if (!file_exists($this->save_path) || !is_readable($this->save_path)) {
            throw new \Exception(sprintf("The config file '%s' cannot be read!", $this->save_path));
        }
        
        $json = file_get_contents($this->save_path);
        if ($json === false) {
            throw new \Exception(sprintf("The config file '%s' cannot be read!", $this->save_path));
        }
        
        if (!empty($this->save_encrypted)) {
            $json = aes_256_decrypt($json, $this->salt_key);
        }
        
        try {
            json_to_array($json, $data);
        } catch (\Throwable $ex) {
            throw new \Exception("JSON parse error: " . $ex->getMessage());
        }
        
        if ($this->use_apcu) {
            apcu_store("config_settings", $data);
        }
        
        return true;
    } // loadJSON
    
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
     * - if the save path is not specified.
     *
     * @author Oleg Schildt
     */
    protected function validateParameters()
    {
        if (empty($this->save_path)) {
            throw new \Exception("The 'save_path' is not specified!");
        }
        
        return true;
    } // validateParameters
    
    /**
     * Initializes the settings manager parameters.
     *
     * @param array $parameters
     * Settings for saving and loading as an associative array in the form key => value:
     *
     * - $parameters["save_path"] - the target file path where the settings data should be stored.
     * - $parameters["save_encrypted"] - if it is true, the data is encrypted before saving.
     * - $parameters["salt_key"] - the salt key if the data should be encrypted before saving.
     * - $parameters["config_file_must_exist"] - if this paremeter is true and the config file does not exist, the loading function will fail.
     *
     * - $parameters["use_apcu"] - if installed, apcu can be used to cache the settings in the memory.
     *
     * @return boolean
     * Returns true upon successful initialization, otherwise false.
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if the save path is not specified.
     *
     * @author Oleg Schildt
     */
    public function init($parameters)
    {
        if (!empty($parameters["save_path"])) {
            $this->save_path = $parameters["save_path"];
        }
        
        if (!empty($parameters["save_encrypted"])) {
            $this->save_encrypted = $parameters["save_encrypted"];
        }
        
        if (!empty($parameters["salt_key"])) {
            $this->salt_key = $parameters["salt_key"];
        }
        
        if (!empty($parameters["config_file_must_exist"])) {
            $this->config_file_must_exist = $parameters["config_file_must_exist"];
        }
        
        if (!empty($parameters["use_apcu"])) {
            $this->use_apcu = $parameters["use_apcu"];
        }
        
        return $this->validateParameters();
    } // init
    
    /**
     * Sets the validator for the settings.
     *
     * @param Interfaces\ISettingsValidator $validator
     * The settings validator.
     *
     * @return void
     *
     * @see ConfigSettingsManager::getValidator()
     * @see ConfigSettingsManager::validateSettings()
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
     * @see ConfigSettingsManager::setValidator()
     * @see ConfigSettingsManager::validateSettings()
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
     * @see ConfigSettingsManager::getContext()
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
     * @see ConfigSettingsManager::setContext()
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
     * - if the query fails or if some object names are invalid.
     * - if the config file is not readable.
     *
     * @see ConfigSettingsManager::getParameter()
     * @see ConfigSettingsManager::setParameters()
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
     * @see ConfigSettingsManager::getParameter()
     * @see ConfigSettingsManager::setParameter()
     *
     * @author Oleg Schildt
     */
    public function setParameters(&$parameters, $force_creation = false)
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
     * - if the save path is not specified.
     * - if the config file is not readable.
     *
     * @see ConfigSettingsManager::setParameter()
     * @see ConfigSettingsManager::setParameters()
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
     * @see  ConfigSettingsManager::getValidator()
     * @see  ConfigSettingsManager::setValidator()
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
     * Loads the settings from the target file.
     *
     * @return boolean
     * Returns true if the settings have been successfully loaded, otherwise false.
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if the save path is not specified.
     * - if the config file is not readable.
     * - if the config file is invalid.
     *
     * @see ConfigSettingsManager::saveSettings()
     *
     * @author Oleg Schildt
     */
    public function loadSettings()
    {
        return $this->loadJSON($this->settings);
    } // loadSettings
    
    /**
     * Saves the settings from to the target file.
     *
     * @return boolean
     * Returns true if the settings have been successfully saved, otherwise false.
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if the save path is not specified.
     * - if the config file is not readable.
     * - if the config file is not writable.
     *
     * @see ConfigSettingsManager::loadSettings()
     *
     * @author Oleg Schildt
     */
    public function saveSettings()
    {
        if (empty($this->settings)) {
            $this->loadSettings();
        }
        
        return $this->saveJSON($this->settings);
    } // saveSettings
} // ConfigSettingsManager
