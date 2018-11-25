<?php
/**
 * This file contains the declaration of the interface ISettingsValidator for settings validation.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\Interfaces;

/**
 * Interface for settings validation.
 *
 * @author Oleg Schildt
 */
interface ISettingsValidator
{
    /**
     * Validates the settings of a settings manager.
     *
     * @param ISettingsManager $settingsmanager
     * The settings manager whose settings should be validated.
     *
     * @param string $context
     * The settings context.
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
     * @return boolean
     * The method should return true upon successful validation, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function validate($settingsmanager, $context);
} // ISettingsValidator
