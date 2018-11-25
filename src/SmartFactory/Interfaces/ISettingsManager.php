<?php
/**
 * This file contains the declaration of the interface ISettingsManager for working with settings.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\Interfaces;

/**
 * Interface for working with settings.
 *
 * @author Oleg Schildt
 */
interface ISettingsManager extends IInitable
{
    /**
     * Initializes the settings manager with parameters.
     *
     * @param array $parameters
     * The parameters may vary for each settings manager.
     *
     * @return boolean
     * The method should return true upon successful initialization, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function init($parameters);
    
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
    public function isDirty($global = false);
    
    /**
     * Sets the validator for the settings.
     *
     * @param ISettingsValidator $validator
     * The settings validator.
     *
     * @return void
     *
     * @see getValidator()
     * @see validateSettings()
     *
     * @author Oleg Schildt
     */
    public function setValidator($validator);
    
    /**
     * Returns the validator for the settings.
     *
     * @return ISettingsValidator|null
     * Returns the validator for the settings or null if none is defined.
     *
     * @see setValidator()
     * @see validateSettings()
     *
     * @author Oleg Schildt
     */
    public function getValidator();
    
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
    public function setParameter($name, $value);
    
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
    public function getParameter($name, $get_dirty = false, $default = null);
    
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
    public function setContext($context = "default");
    
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
    public function getContext();
    
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
     * @uses ISettingsValidator
     *
     * @see  getValidator()
     * @see  setValidator()
     *
     * @author Oleg Schildt
     */
    public function validateSettings();
    
    /**
     * Loads the settings from the persitence source.
     *
     * @return boolean
     * Returns true if the settings have been successfully loaded, otherwise false.
     *
     * @see saveSettings()
     *
     * @author Oleg Schildt
     */
    public function loadSettings();
    
    /**
     * Saves the settings from to the persitence target.
     *
     * @return boolean
     * Returns true if the settings have been successfully saved, otherwise false.
     *
     * @see loadSettings()
     *
     * @author Oleg Schildt
     */
    public function saveSettings();
} // ISettingsManager
