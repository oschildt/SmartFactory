<?php
/**
 * Declares the common settings access, validation and persistence contract.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\Interfaces;

/**
 * Provides settings access with explicit validation and persistence operations.
 *
 * Context selects validation rules for separate forms or wizard steps. Persistence
 * between requests, including wizard drafts, must be provided by the application.
 *
 * @author Oleg Schildt
 */
interface ISettingsManager extends IInitable
{
    /**
     * Initializes the settings manager's storage options.
     *
     * @param array<string, mixed> $parameters
     * Storage-specific options documented by the concrete manager.
     *
     * @return void
     * Reinitialization discards cached values and unsaved edits.
     *
     * @throws \Throwable
     * If the storage options are invalid or required configuration is missing.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void;

    /**
     * Assigns the validator without validating values immediately.
     *
     * @param ISettingsValidator $validator
     * The validator to call from validateSettings().
     *
     * @return void
     * Leaves the current settings and context unchanged.
     *
     * @see ISettingsManager::getValidator()
     * @see ISettingsManager::validateSettings()
     *
     * @author Oleg Schildt
     */
    public function setValidator(ISettingsValidator $validator): void;

    /**
     * Returns the currently assigned validator.
     *
     * @return ISettingsValidator|null
     * Returns NULL if no validator is configured.
     *
     * @see ISettingsManager::setValidator()
     *
     * @author Oleg Schildt
     */
    public function getValidator(): ?ISettingsValidator;

    /**
     * Assigns one parameter in the current snapshot without persisting it.
     *
     * @param string $name
     * The parameter name or a supported unambiguous alias.
     *
     * @param mixed $value
     * The new value, including an explicitly stored NULL.
     *
     * @return void
     * Loads settings on first access and tracks the assignment for the next save.
     *
     * @throws \Throwable
     * If loading fails or the concrete manager rejects the name or value.
     *
     * @see ISettingsManager::getParameter()
     * @see ISettingsManager::setParameters()
     *
     * @author Oleg Schildt
     */
    public function setParameter(string $name, mixed $value): void;

    /**
     * Assigns a supplied subset of parameters without persisting them.
     *
     * @param array<string, mixed> $parameters
     * Parameter names mapped to their new values.
     *
     * @param bool $force_creation
     * Allows new names in schema-free managers. With false, only defined parameters
     * are assigned. Fixed-schema managers accept configured fields even if their optional
     * rows are absent and never create unconfigured database columns.
     *
     * @return void
     * Leaves parameters outside the supplied subset unchanged.
     *
     * @throws \Throwable
     * If loading fails or a parameter name, value or attempted creation is invalid.
     *
     * @see ISettingsManager::setParameter()
     *
     * @author Oleg Schildt
     */
    public function setParameters(array $parameters, bool $force_creation = false): void;

    /**
     * Returns a parameter's current value, including unsaved assignments.
     *
     * @param string $name
     * The parameter name or a supported unambiguous alias.
     *
     * @param mixed $default
     * The value used only when the parameter is absent; it does not create a parameter.
     *
     * @return mixed
     * Returns the stored value, including NULL, false and zero, or the supplied default
     * for an absent parameter. An explicitly assigned value always takes precedence.
     *
     * @throws \Throwable
     * If loading fails or the parameter name is invalid or ambiguous.
     *
     * @see ISettingsManager::setParameter()
     *
     * @author Oleg Schildt
     */
    public function getParameter(string $name, mixed $default = null): mixed;

    /**
     * Selects the validation context for a form or wizard step.
     *
     * @param string $context
     * A context such as step1, server_settings or database_settings.
     *
     * @return void
     * Does not change storage, save drafts or limit persistence to that context.
     *
     * @see ISettingsManager::getContext()
     *
     * @author Oleg Schildt
     */
    public function setContext(string $context = "default"): void;

    /**
     * Returns the selected validation context.
     *
     * @return string
     * Returns the context forwarded to the optional validator.
     *
     * @see ISettingsManager::setContext()
     *
     * @author Oleg Schildt
     */
    public function getContext(): string;

    /**
     * Validates the current settings explicitly, normally before saving.
     *
     * @return void
     * Does nothing when no validator is configured.
     *
     * @throws \Throwable
     * If the validator rejects a setting or reading values during validation fails.
     *
     * @see ISettingsManager::getValidator()
     * @see ISettingsManager::setValidator()
     *
     * @author Oleg Schildt
     */
    public function validateSettings(): void;

    /**
     * Reloads the complete persisted snapshot, discarding local edits after success.
     *
     * @return void
     * Replaces cached values, including when the persisted source is empty.
     *
     * @throws \Throwable
     * If the source cannot be accessed or decoded; the current snapshot is retained.
     *
     * @see ISettingsManager::saveSettings()
     *
     * @author Oleg Schildt
     */
    public function loadSettings(): void;

    /**
     * Persists assigned parameters without implicitly validating them.
     *
     * @return void
     * Clears tracked edits after success. Database managers join an existing READ COMMITTED
     * transaction or create one for the whole save. After an external rollback, reload the snapshot.
     *
     * @throws \Throwable
     * If loading, encoding, locking or writing fails; edits remain available for retry.
     * The caller must roll back an external transaction after any save failure.
     *
     * @see ISettingsManager::loadSettings()
     *
     * @author Oleg Schildt
     */
    public function saveSettings(): void;
} // ISettingsManager
