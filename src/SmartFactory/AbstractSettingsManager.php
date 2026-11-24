<?php

namespace SmartFactory;

use SmartFactory\Interfaces\ISettingsManager;
use SmartFactory\Interfaces\ISettingsValidator;

/**
 * Provides lazy loading, parameter access, change tracking and contextual validation.
 *
 * Values are cached in the object, including empty settings and explicit NULL values.
 * Context selects validation rules; it does not persist wizard drafts between requests.
 * Call loadSettings() explicitly to discard local edits and reload persisted values.
 *
 * @author Oleg Schildt
 */
abstract class AbstractSettingsManager implements ISettingsManager
{
    /**
     * The context passed to the optional settings validator.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $context = "default";

    /**
     * The validator used by validateSettings().
     *
     * @var ISettingsValidator|null
     *
     * @author Oleg Schildt
     */
    protected ?ISettingsValidator $validator = null;

    /**
     * Settings indexed by their canonical parameter names.
     *
     * @var array<string, mixed>
     *
     * @author Oleg Schildt
     */
    protected array $settings = [];

    /**
     * Whether loading has completed successfully, even when no settings exist.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $loaded = false;

    /**
     * Names explicitly assigned since the last successful load or save.
     *
     * @var array<string, true>
     *
     * @author Oleg Schildt
     */
    protected array $changed = [];

    /**
     * Sets the validator without changing the current values or context.
     *
     * @param ISettingsValidator $validator
     * The settings validator.
     *
     * @return void
     * Does not validate the settings immediately.
     *
     * @author Oleg Schildt
     */
    public function setValidator(ISettingsValidator $validator): void
    {
        $this->validator = $validator;
    } // setValidator

    /**
     * Returns the optional settings validator.
     *
     * @return ISettingsValidator|null
     * Returns NULL when no validator has been assigned.
     *
     * @author Oleg Schildt
     */
    public function getValidator(): ?ISettingsValidator
    {
        return $this->validator;
    } // getValidator

    /**
     * Selects the validation context, such as a form name or wizard step.
     *
     * @param string $context
     * The context forwarded unchanged to the validator.
     *
     * @return void
     * Does not reload settings or save drafts between requests.
     *
     * @author Oleg Schildt
     */
    public function setContext(string $context = "default"): void
    {
        $this->context = $context;
    } // setContext

    /**
     * Returns the selected validation context.
     *
     * @return string
     * Returns the current context, initially "default".
     *
     * @author Oleg Schildt
     */
    public function getContext(): string
    {
        return $this->context;
    } // getContext

    /**
     * Loads settings if necessary and assigns one parameter.
     *
     * @param string $name
     * The parameter name, resolved by the concrete manager.
     *
     * @param mixed $value
     * The new value; NULL is a stored value, not an absent parameter.
     *
     * @return void
     * Marks the parameter as changed even when its value equals the loaded value.
     *
     * @throws \Throwable
     * If loading fails or the concrete manager rejects the name or value.
     *
     * @author Oleg Schildt
     */
    public function setParameter(string $name, mixed $value): void
    {
        $name = $this->normalizeParameterName($name);

        $this->validateParameterValue($name, $value);

        $this->ensureLoaded();

        $this->settings[$name] = $value;
        $this->changed[$name] = true;
    } // setParameter

    /**
     * Assigns parameters in bulk, optionally introducing new names.
     *
     * @param array<string, mixed> $parameters
     * Parameter names and their new values.
     *
     * @param bool $force_creation
     * Whether names absent from the current settings may be introduced. Managers
     * with a fixed schema accept configured parameters even when their rows are absent.
     *
     * @return void
     * Leaves parameters outside the supplied array unchanged.
     *
     * @throws \Throwable
     * If loading fails or a supplied parameter name or value is invalid.
     *
     * @author Oleg Schildt
     */
    public function setParameters(array $parameters, bool $force_creation = false): void
    {
        $this->ensureLoaded();

        foreach ($parameters as $name => $value) {
            $canonical = $this->normalizeParameterName((string) $name);

            if (!$force_creation && !$this->isParameterDefined($canonical)) {
                continue;
            }

            $this->setParameter($canonical, $value);
        }
    } // setParameters

    /**
     * Returns a parameter, loading the settings once on first access.
     *
     * @param string $name
     * The parameter name, resolved by the concrete manager.
     *
     * @param mixed $default
     * The value returned only when the parameter is absent.
     *
     * @return mixed
     * Returns the current value, including NULL, or the supplied default for an absent name.
     *
     * @throws \Throwable
     * If loading fails or the parameter name is invalid or ambiguous.
     *
     * @author Oleg Schildt
     */
    public function getParameter(string $name, mixed $default = null): mixed
    {
        $name = $this->normalizeParameterName($name);

        $this->ensureLoaded();

        return array_key_exists($name, $this->settings) ? $this->settings[$name] : $default;
    } // getParameter

    /**
     * Validates current values using the optional validator and selected context.
     *
     * @return void
     * Does nothing when no validator is configured. Saving does not implicitly validate.
     *
     * @throws \Throwable
     * If the validator rejects a setting or accessing settings during validation fails.
     *
     * @author Oleg Schildt
     */
    public function validateSettings(): void
    {
        if ($this->validator !== null) {
            $this->validator->validate($this, $this->context);
        }
    } // validateSettings

    /**
     * Reloads persisted values and discards local edits only after loading succeeds.
     *
     * @return void
     * Replaces the entire cached snapshot, including when the source is empty.
     *
     * @throws \Throwable
     * If configuration, storage access or decoding fails; the current snapshot is retained.
     *
     * @author Oleg Schildt
     */
    public function loadSettings(): void
    {
        $data = [];

        $this->loadSettingsData($data);

        $this->settings = $data;
        $this->loaded = true;
        $this->changed = [];
    } // loadSettings

    /**
     * Persists local changes and accepts the resulting snapshot after a successful save.
     *
     * @return void
     * Clears change tracking after success. With an external database transaction,
     * the caller must commit or roll back and reload settings after a rollback.
     *
     * @throws \Throwable
     * If loading, encoding or writing fails; local edits remain available for retry.
     *
     * @author Oleg Schildt
     */
    public function saveSettings(): void
    {
        $this->ensureLoaded();

        $data = $this->settings;

        $this->saveSettingsData($data);

        $this->settings = $data;
        $this->changed = [];
    } // saveSettings

    /**
     * Loads settings on demand, including a legitimately empty source.
     *
     * @return void
     * Leaves an already loaded snapshot unchanged.
     *
     * @throws \Throwable
     * If the concrete storage cannot be loaded.
     *
     * @author Oleg Schildt
     */
    protected function ensureLoaded(): void
    {
        if (!$this->loaded) {
            $this->loadSettings();
        }
    } // ensureLoaded

    /**
     * Invalidates the current snapshot after changing its storage or owner.
     *
     * @return void
     * Discards loaded values and unsaved changes.
     *
     * @author Oleg Schildt
     */
    protected function resetSettings(): void
    {
        $this->settings = [];
        $this->changed = [];
        $this->loaded = false;
    } // resetSettings

    /**
     * Resolves a public name to its internal representation.
     *
     * @param string $name
     * The requested parameter name.
     *
     * @return string
     * Returns the name unchanged unless a subclass implements aliases.
     *
     * @author Oleg Schildt
     */
    protected function normalizeParameterName(string $name): string
    {
        return $name;
    } // normalizeParameterName

    /**
     * Checks whether a parameter may be assigned without forcing its creation.
     *
     * @param string $name
     * The canonical parameter name.
     *
     * @return bool
     * Returns whether the current snapshot contains the parameter, including NULL values.
     *
     * @author Oleg Schildt
     */
    protected function isParameterDefined(string $name): bool
    {
        return array_key_exists($name, $this->settings);
    } // isParameterDefined

    /**
     * Allows subclasses to reject assignments before mutating the snapshot.
     *
     * @param string $name
     * The canonical parameter name.
     *
     * @param mixed $value
     * The proposed value.
     *
     * @return void
     * The base manager accepts all values; storage encoding is checked during saving.
     *
     * @author Oleg Schildt
     */
    protected function validateParameterValue(string $name, mixed $value): void
    {
    } // validateParameterValue

    /**
     * Reads settings into a temporary snapshot.
     *
     * @param array<string, mixed> &$data
     * Receives the complete settings snapshot.
     *
     * @return void
     * Does not modify the manager's currently loaded values.
     *
     * @throws \Throwable
     * If the source cannot be read or decoded.
     *
     * @author Oleg Schildt
     */
    abstract protected function loadSettingsData(array &$data): void;

    /**
     * Writes local edits, optionally merging them with the latest persisted snapshot.
     *
     * @param array<string, mixed> &$data
     * The local snapshot, replaced with the saved snapshot only after success.
     *
     * @return void
     * Does not clear change tracking; the public save method does so after success.
     *
     * @throws \Throwable
     * If encoding, locking or writing fails.
     *
     * @author Oleg Schildt
     */
    abstract protected function saveSettingsData(array &$data): void;
} // AbstractSettingsManager
