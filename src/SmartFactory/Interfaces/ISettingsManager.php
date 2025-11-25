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
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any system errors.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void;
    
    /**
     * Sets the validator for the settings.
     *
     * @param ISettingsValidator $validator
     * The settings validator.
     *
     * @return void
     *
     * @see ISettingsManager::getValidator()
     * @see ISettingsManager::validateSettings()
     *
     * @author Oleg Schildt
     */
    public function setValidator(ISettingsValidator $validator): void;
    
    /**
     * Returns the validator for the settings.
     *
     * @return ?ISettingsValidator
     * Returns the validator for the settings or null if none is defined.
     *
     * @see ISettingsManager::setValidator()
     * @see ISettingsManager::validateSettings()
     *
     * @author Oleg Schildt
     */
    public function getValidator(): ?ISettingsValidator;
    
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
     * @see ISettingsManager::getParameter()
     * @see ISettingsManager::setParameters()
     *
     * @author Oleg Schildt
     */
    public function setParameter(string $name, mixed $value): void;
    
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
     * @see ISettingsManager::getParameter()
     * @see ISettingsManager::setParameter()
     *
     * @author Oleg Schildt
     */
    public function setParameters(array $parameters, bool $force_creation = false): void;

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
     * However, if the status of the data is dirty and the unsaved
     * last entered value is requested, then always the actual
     * last entered value is returned and this parameter is ignored.
     *
     * @return mixed
     * Returns the value of the settings parameter.
     *
     * @see ISettingsManager::setParameter()
     * @see ISettingsManager::setParameters()
     *
     * @author Oleg Schildt
     */
    public function getParameter(string $name, mixed $default = null): mixed;
    
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
     * @see ISettingsManager::getContext()
     *
     * @author Oleg Schildt
     */
    public function setContext(string $context = "default"): void;
    
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
     * @see ISettingsManager::setContext()
     *
     * @author Oleg Schildt
     */
    public function getContext(): string;
    
    /**
     * Validates the current settings values.
     *
     * It should be called after settings the new values of the parameters
     * and before their saving.
     *
     * @return void
     *
     * @throws \SmartFactory\SmartException|\SmartFactory\SmartExceptionCollection
     * It might throw an exception if the content type oк JSON data is invalid.
     *
     * @uses ISettingsValidator
     *
     * @see ISettingsManager::getValidator()
     * @see ISettingsManager::setValidator()
     *
     * @author Oleg Schildt
     */
    public function validateSettings(): void;
    
    /**
     * Loads the settings from the persistence source.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @see ISettingsManager::saveSettings()
     *
     * @author Oleg Schildt
     */
    public function loadSettings(): void;
    
    /**
     * Saves the settings from to the persistence target.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @see ISettingsManager::loadSettings()
     *
     * @author Oleg Schildt
     */
    public function saveSettings(): void;
} // ISettingsManager
