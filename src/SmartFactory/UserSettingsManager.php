<?php
/**
 * Implements database storage for per-user settings.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\DatabaseWorkers\DBWorker;

/**
 * Manages user settings mapped to database columns and multichoice tables.
 *
 * New scalar settings require a column and a field/type definition. Values are cached
 * in this object, not in the PHP session. Switching users invalidates the snapshot.
 * Canonical names are table.column; unambiguous short column names remain supported.
 * Saves write only explicitly assigned parameters in one transaction. The first settings
 * table identifies the existing owner and provides the aggregate lock for all user settings.
 *
 * @see ConfigSettingsManager
 * @see ApplicationSettingsManager
 *
 * @author Oleg Schildt
 */
class UserSettingsManager extends AbstractDatabaseSettingsManager
{
    /**
     * The current user identifier; NULL means no user has been selected.
     *
     * @var int|null
     *
     * @author Oleg Schildt
     */
    protected ?int $user_id = null;

    /**
     * Scalar settings tables; the first field of each definition is the user key.
     *
     * @var array<string, array<string, int>>
     *
     * @author Oleg Schildt
     */
    protected array $settings_tables = [];

    /**
     * Multichoice tables; each definition contains the user key followed by the value field.
     *
     * @var array<string, array<string, int>>
     *
     * @author Oleg Schildt
     */
    protected array $multichoice_tables = [];

    /**
     * Generated key columns requiring identity handling by save_records().
     *
     * @var array<string, string>
     *
     * @author Oleg Schildt
     */
    protected array $identity_fields = [];

    /**
     * Public names mapped to canonical names; NULL marks an ambiguous short alias.
     *
     * @var array<string, string|null>
     *
     * @author Oleg Schildt
     */
    protected array $parameter_names = [];

    /**
     * User key parameters that may be read but never assigned as settings.
     *
     * @var array<string, true>
     *
     * @author Oleg Schildt
     */
    protected array $read_only_parameters = [];

    /**
     * Configures scalar and multichoice tables without loading a user's settings.
     *
     * @param array{dbworker: DBWorker, settings_tables: array<string, array<string, int>>, multichoice_tables?: array<string, array<string, int>>, identity_fields?: array<string, string>, no_own_transaction?: bool} $parameters
     * Maps tables to ordered field/type definitions. The first field is the user key.
     * Scalar tables need at least one setting field; multichoice tables need exactly two
     * fields. The first scalar table must contain the existing owner row. identity_fields
     * identifies generated keys, for example ["users" => "id"]. Multichoice tables support
     * scalar, numeric and date values; each parameter is addressed by its table name.
     * Set no_own_transaction to require an existing caller-managed transaction.
     *
     * Example:
     *
     * ```php
     * $instance->init([
     *     "dbworker" => app_dbworker(),
     *     "identity_fields" => ["users" => "id"],
     *     "settings_tables" => [
     *         "users" => [
     *             "id" => DBWorker::DB_NUMBER,
     *             "language" => DBWorker::DB_STRING,
     *             "time_zone" => DBWorker::DB_STRING
     *         ],
     *         "user_forum_settings" => [
     *             "user_id" => DBWorker::DB_NUMBER,
     *             "signature" => DBWorker::DB_STRING,
     *             "status" => DBWorker::DB_STRING,
     *             "hide_pictures" => DBWorker::DB_NUMBER,
     *             "hide_signatures" => DBWorker::DB_NUMBER
     *         ]
     *     ],
     *     "multichoice_tables" => [
     *         "user_colors" => [
     *             "user_id" => DBWorker::DB_NUMBER,
     *             "color" => DBWorker::DB_STRING
     *         ]
     *     ]
     * ]);
     * ```
     *
     * Example:
     *
     * ```php
     * $instance->init([
     *     "dbworker" => app_dbworker(),
     *     "identity_fields" => ["users" => "id"],
     *     "settings_tables" => [
     *         "users" => [
     *             "id" => DBWorker::DB_NUMBER,
     *             "language" => DBWorker::DB_STRING,
     *             "time_zone" => DBWorker::DB_STRING
     *         ],
     *         "user_forum_settings" => [
     *             "user_id" => DBWorker::DB_NUMBER,
     *             "signature" => DBWorker::DB_STRING,
     *             "status" => DBWorker::DB_STRING,
     *             "hide_pictures" => DBWorker::DB_NUMBER,
     *             "hide_signatures" => DBWorker::DB_NUMBER
     *         ]
     *     ],
     *     "multichoice_tables" => [
     *         "user_colors" => [
     *             "user_id" => DBWorker::DB_NUMBER,
     *             "color" => DBWorker::DB_STRING
     *         ]
     *     ]
     * ]);
     * ```
     *
     * @return void
     * Replaces definitions and discards cached values and unsaved edits.
     *
     * @throws \InvalidArgumentException
     * If the worker, table definitions, field types, identifiers or identity mapping are invalid.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void
    {
        $worker = $parameters['dbworker'] ?? null;
        $tables = $parameters['settings_tables'] ?? null;
        $choices = $parameters['multichoice_tables'] ?? [];
        $identities = $parameters['identity_fields'] ?? [];

        if (!$worker instanceof DBWorker || !is_array($tables) || $tables === []
            || !is_array($choices) || !is_array($identities) || array_intersect_key($tables, $choices) !== []) {
            throw new \InvalidArgumentException("Invalid user settings configuration.");
        }

        $this->validateTableDefinitions($tables, false);

        $this->validateTableDefinitions($choices, true);

        foreach ($identities as $table => $field) {
            if (!isset($tables[$table]) || !is_string($field) || array_key_first($tables[$table]) !== $field) {
                throw new \InvalidArgumentException("An identity field must be the configured user key of a scalar settings table.");
            }
        }

        $names = [];
        $read_only = [];

        foreach ($tables as $table => $fields) {
            foreach ($fields as $field => $_) {
                $canonical = $table . "." . $field;
                $names[$canonical] = $canonical;
                $names[$field] = array_key_exists($field, $names) ? null : $canonical;
            }

            $read_only[$table . "." . array_key_first($fields)] = true;
        }

        foreach ($choices as $table => $_) {
            if (array_key_exists($table, $names)) {
                throw new \InvalidArgumentException("A multichoice table name conflicts with a scalar parameter: " . $table);
            }

            $names[$table] = $table;
        }

        $this->settings_tables = $tables;
        $this->multichoice_tables = $choices;
        $this->identity_fields = $identities;
        $this->parameter_names = $names;
        $this->read_only_parameters = $read_only;

        $this->setDBWorker($worker, (bool) ($parameters['no_own_transaction'] ?? false));
    } // init

    /**
     * Validates ordered table definitions before using their identifiers in SQL.
     *
     * @param array<string, array<string, int>> $tables
     * The scalar or multichoice table definitions.
     *
     * @param bool $multichoice
     * Whether each definition must have exactly one user key and one scalar value field.
     *
     * @return void
     * Leaves the manager's current configuration unchanged.
     *
     * @throws \InvalidArgumentException
     * If a table has invalid identifiers, an invalid field count or unsupported field types.
     *
     * @author Oleg Schildt
     */
    protected function validateTableDefinitions(array $tables, bool $multichoice): void
    {
        $types = [DBWorker::DB_AS_IS, DBWorker::DB_NUMBER, DBWorker::DB_STRING,
            DBWorker::DB_DATE, DBWorker::DB_DATETIME, DBWorker::DB_GEOMETRY,
            DBWorker::DB_GEOMETRY_4326, DBWorker::DB_LARGE_OBJECT_STREAM];

        foreach ($tables as $table => $fields) {
            if (!is_string($table) || !is_array($fields) || count($fields) < 2
                || ($multichoice && count($fields) !== 2)) {
                throw new \InvalidArgumentException("Invalid user settings table definition.");
            }

            $this->validateIdentifier($table, true);

            foreach ($fields as $field => $type) {
                if (!is_string($field) || !in_array($type, $types, true)
                    || ($multichoice && !in_array($type, [DBWorker::DB_AS_IS, DBWorker::DB_NUMBER,
                        DBWorker::DB_STRING, DBWorker::DB_DATE, DBWorker::DB_DATETIME], true))) {
                    throw new \InvalidArgumentException("Invalid user settings field definition.");
                }

                $this->validateIdentifier($field);
            }
        }
    } // validateTableDefinitions

    /**
     * Checks that table definitions, a worker and a user have been selected.
     *
     * @return void
     * Accepts user identifier zero and rejects only an unset identifier.
     *
     * @throws \LogicException
     * If the manager is uninitialized or setUserID() has not selected a user.
     *
     * @author Oleg Schildt
     */
    protected function validateParameters(): void
    {
        if ($this->dbworker === null || $this->settings_tables === []) {
            throw new \LogicException("The user settings manager is not initialized.");
        }

        if ($this->user_id === null) {
            throw new \LogicException("A user ID must be selected before accessing user settings.");
        }
    } // validateParameters

    /**
     * Resolves short names without creating duplicate copies of column values.
     *
     * @param string $name
     * A qualified column name, unambiguous short name or multichoice table name.
     *
     * @return string
     * Returns the canonical name. Unknown names remain absent when reading.
     *
     * @throws \InvalidArgumentException
     * If a short name identifies columns in more than one configured table.
     *
     * @author Oleg Schildt
     */
    protected function normalizeParameterName(string $name): string
    {
        if (array_key_exists($name, $this->parameter_names) && $this->parameter_names[$name] === null) {
            throw new \InvalidArgumentException("Ambiguous settings name; use table.column: " . $name);
        }

        return $this->parameter_names[$name] ?? $name;
    } // normalizeParameterName

    /**
     * Recognizes configured fields even when their optional database row is absent.
     *
     * @param string $name
     * The canonical parameter name.
     *
     * @return bool
     * Returns whether the parameter belongs to the configured schema.
     *
     * @author Oleg Schildt
     */
    protected function isParameterDefined(string $name): bool
    {
        return isset($this->parameter_names[$name]);
    } // isParameterDefined

    /**
     * Rejects unknown fields, user key assignments and invalid multichoice structures.
     *
     * @param string $name
     * The canonical parameter name.
     *
     * @param mixed $value
     * A scalar setting value or an array of scalar/date multichoice values.
     *
     * @return void
     * Does not change the supplied value or the current settings snapshot.
     *
     * @throws \InvalidArgumentException
     * If the name is unknown or read-only, a multichoice value is not an array,
     * or a multichoice entry is neither scalar, NULL nor an accepted date object.
     *
     * @author Oleg Schildt
     */
    protected function validateParameterValue(string $name, mixed $value): void
    {
        if (!$this->isParameterDefined($name) || isset($this->read_only_parameters[$name])) {
            throw new \InvalidArgumentException("Unknown or read-only user setting: " . $name);
        }

        if (!isset($this->multichoice_tables[$name])) {
            return;
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException("Multichoice settings must be arrays: " . $name);
        }

        $type = array_values($this->multichoice_tables[$name])[1];

        foreach ($value as $entry) {
            if (!is_scalar($entry) && $entry !== null
                && !(in_array($type, [DBWorker::DB_DATE, DBWorker::DB_DATETIME], true)
                    && $entry instanceof \DateTimeInterface)) {
                throw new \InvalidArgumentException("Invalid multichoice entry: " . $name);
            }
        }
    } // validateParameterValue

    /**
     * Reads all configured tables for the selected user using bound queries.
     *
     * @param DBWorker $worker
     * A connected worker with isolated statement state.
     *
     * @return array<string, mixed>
     * Returns canonical scalar parameters and arrays indexed by multichoice table names.
     * Missing scalar rows contribute no parameters; empty multichoice tables contribute [].
     *
     * @throws \UnexpectedValueException
     * If a scalar settings table contains multiple rows for the same user.
     *
     * @throws \Throwable
     * If preparing, executing, fetching or converting a stored value fails.
     *
     * @author Oleg Schildt
     */
    protected function readSettingsData(DBWorker $worker): array
    {
        $data = [];

        foreach ($this->settings_tables as $table => $fields) {
            $key = array_key_first($fields);

            try {
                $worker->prepare_query('SELECT ' . implode(', ', array_keys($fields))
                    . ' FROM ' . $table . ' WHERE ' . $key . ' = ?');

                $worker->execute_prepared_query($this->user_id);

                if ($worker->fetch_row()) {
                    foreach ($fields as $field => $type) {
                        $data[$table . "." . $field] = $worker->field_by_name($field, $type);
                    }

                    if ($worker->fetch_row()) {
                        throw new \UnexpectedValueException("Multiple settings rows for one user in " . $table);
                    }
                }
            } finally {
                $worker->free_prepared_query();
            }
        }

        foreach ($this->multichoice_tables as $table => $fields) {
            [$key, $value_field] = array_keys($fields);
            $data[$table] = [];

            try {
                $worker->prepare_query('SELECT ' . $value_field . ' FROM ' . $table . ' WHERE ' . $key . ' = ?');

                $worker->execute_prepared_query($this->user_id);

                while ($worker->fetch_row()) {
                    $data[$table][] = $worker->field_by_name($value_field, $fields[$value_field]);
                }
            } finally {
                $worker->free_prepared_query();
            }
        }

        return $data;
    } // readSettingsData

    /**
     * Loads settings into a temporary canonical snapshot.
     *
     * @param array<string, mixed> &$data
     * Receives the user's complete settings after successful reading.
     *
     * @return void
     * Does not change the currently loaded values on failure.
     *
     * @throws \Throwable
     * If a user or configuration is missing, connecting fails or a query/conversion fails.
     *
     * @author Oleg Schildt
     */
    protected function loadSettingsData(array &$data): void
    {
        $this->validateParameters();

        $data = $this->readSettingsData($this->databaseWorker());
    } // loadSettingsData

    /**
     * Converts a multichoice value to the same unquoted scalar used for bound comparisons.
     *
     * @param DBWorker $worker
     * The worker providing database-specific date formatting.
     *
     * @param mixed $value
     * A scalar, NULL or date/time object accepted by the configured field.
     *
     * @param int $type
     * The configured DBWorker field type.
     *
     * @return scalar|null
     * Returns NULL for empty strings and NULL, formatted dates, or the original scalar.
     *
     * @throws \InvalidArgumentException
     * If a date is not an integer timestamp, integer timestamp string or date/time object.
     *
     * @author Oleg Schildt
     */
    protected function comparisonValue(DBWorker $worker, mixed $value, int $type): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if ($type === DBWorker::DB_DATE || $type === DBWorker::DB_DATETIME) {
            if (is_string($value) && preg_match('/\\A-?\\d+\\z/', $value)) {
                $value = filter_var($value, FILTER_VALIDATE_INT);
            }

            if (!is_int($value) && !$value instanceof \DateTimeInterface) {
                throw new \InvalidArgumentException("A settings date must be a Unix timestamp or date/time object.");
            }

            return $type === DBWorker::DB_DATE
                ? $worker->format_date_bind($value) : $worker->format_datetime_bind($value);
        }

        return $type === DBWorker::DB_NUMBER && is_bool($value) ? (int) $value : $value;
    } // comparisonValue

    /**
     * Upserts selected choices and deletes removed choices through a bound query.
     *
     * @param DBWorker $worker
     * The worker inside the aggregate's transaction and user lock.
     *
     * @param string $table
     * The configured multichoice table name.
     *
     * @param array<array-key, scalar|\DateTimeInterface|null> $values
     * The complete desired choice list, including valid zero and NULL values.
     *
     * @return void
     * Leaves other users and unchanged choice rows intact. An empty list clears this user's choices.
     *
     * @throws \Throwable
     * If converting, saving or deleting a choice fails; the enclosing transaction must roll back.
     *
     * @author Oleg Schildt
     */
    protected function saveChoices(DBWorker $worker, string $table, array $values): void
    {
        $fields = $this->multichoice_tables[$table];
        [$key, $value_field] = array_keys($fields);
        $type = $fields[$value_field];
        $bindings = [];
        $keep_null = false;

        foreach ($values as $value) {
            $normalized = $this->comparisonValue($worker, $value, $type);
            $record = [$key => $this->user_id,
                $value_field => $type === DBWorker::DB_NUMBER && is_bool($value) ? (int) $value : $value];

            $worker->save_records($record, [
                'table' => $table,
                'fields' => $fields,
                'array_dimension_keys' => [],
                'record_key_fields' => [$key, $value_field]
            ]);

            if ($normalized === null) {
                $keep_null = true;
            } else {
                $bindings[] = $normalized;
            }
        }

        $predicate = '';

        if ($bindings !== []) {
            $not_in = $value_field . ' NOT IN (' . implode(', ', array_fill(0, count($bindings), '?')) . ')';
            $predicate = $keep_null ? ' AND ' . $not_in : ' AND (' . $value_field . ' IS NULL OR ' . $not_in . ')';
        } elseif ($keep_null) {
            $predicate = ' AND ' . $value_field . ' IS NOT NULL';
        }

        try {
            $worker->prepare_query('DELETE FROM ' . $table . ' WHERE ' . $key . ' = ?' . $predicate);

            $worker->execute_prepared_query([$this->user_id, ...$bindings]);
        } finally {
            $worker->free_prepared_query();
        }
    } // saveChoices

    /**
     * Saves only assigned parameters and choices in one transaction per user.
     *
     * @param array<string, mixed> &$data
     * The local snapshot, replaced with freshly read database values after success.
     *
     * @return void
     * Does nothing without edits. Missing optional settings rows may be inserted, but
     * the owner row in the first settings table must already exist.
     *
     * @throws \OutOfBoundsException
     * If the first settings table has no row for the selected user.
     *
     * @throws \Throwable
     * If configuration, locking, saving, deleting, refreshing or committing fails.
     * External transactions must be rolled back by their caller after a failure.
     *
     * @author Oleg Schildt
     */
    protected function saveSettingsData(array &$data): void
    {
        $this->validateParameters();

        if ($this->changed === []) {
            return;
        }

        $data = $this->withTransaction(function (DBWorker $worker) use ($data): array {
            $owner_table = array_key_first($this->settings_tables);
            $owner_key = array_key_first($this->settings_tables[$owner_table]);

            // Serialize the whole user aggregate, including requests that only change choice lists.
            if (!$worker->lock_record($owner_table, [$owner_key => $this->user_id])) {
                throw new \OutOfBoundsException("The selected user has no owner row in " . $owner_table);
            }

            foreach ($this->settings_tables as $table => $fields) {
                $key = array_key_first($fields);
                $record = [$key => $this->user_id];

                foreach ($fields as $field => $type) {
                    $name = $table . "." . $field;

                    if ($field !== $key && isset($this->changed[$name])) {
                        $value = $data[$name];
                        $record[$field] = $type === DBWorker::DB_NUMBER && is_bool($value) ? (int) $value : $value;
                    }
                }

                if (count($record) === 1) {
                    continue;
                }

                $worker->save_records($record, [
                    'table' => $table,
                    'fields' => $fields,
                    'array_dimension_keys' => [],
                    'record_key_fields' => [$key],
                    'identity_field' => $this->identity_fields[$table] ?? ''
                ]);
            }

            foreach ($this->multichoice_tables as $table => $_) {
                if (isset($this->changed[$table])) {
                    $this->saveChoices($worker, $table, $data[$table]);
                }
            }

            return $this->readSettingsData($worker);
        });
    } // saveSettingsData

    /**
     * Selects a user and invalidates settings when the identifier changes.
     *
     * @param int|null $user_id
     * The user identifier, including zero, or NULL to clear the selection.
     *
     * @return void
     * Discards the previous user's loaded values and unsaved edits when switching.
     * Selecting the same user preserves current edits.
     *
     * @author Oleg Schildt
     */
    public function setUserID(?int $user_id): void
    {
        if ($this->user_id !== $user_id) {
            $this->user_id = $user_id;

            $this->resetSettings();
        }
    } // setUserID

    /**
     * Returns the selected user's identifier without accessing storage.
     *
     * @return int|null
     * Returns NULL when no user has been selected.
     *
     * @author Oleg Schildt
     */
    public function getUserID(): ?int
    {
        return $this->user_id;
    } // getUserID
} // UserSettingsManager
