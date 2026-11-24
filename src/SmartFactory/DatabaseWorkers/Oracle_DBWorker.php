<?php

/**
 * This file contains the implementation of the class Oracle_DBWorker.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\DatabaseWorkers;

/**
 * Database worker for Oracle Database 12c and later using the OCI8 extension.
 *
 * Uses AL32UTF8 connections, native named binds and explicit temporary BLOB/CLOB descriptors.
 * Automatic identity retrieval discovers the actual identity column and appends RETURNING
 * only for eligible INSERT VALUES statements. Shared transaction state determines whether
 * execution may commit automatically.
 *
 * A database service is selected when connecting; use_database() changes CURRENT_SCHEMA.
 * LOB fields remain descriptors until read or copied to a stream. Result rows are read
 * sequentially without buffering the complete result set.
 *
 * @see DBWorker
 *
 * @author Oleg Schildt
 */
class Oracle_DBWorker extends DBWorker
{
    /**
     * Indicates whether an internal RETURNING bind was appended to the Oracle statement.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    private bool $return_identity = false;

    /**
     * Provides stable storage for the identifier returned by an Oracle output bind.
     *
     * @var int|string|null
     *
     * @author Oleg Schildt
     */
    private mixed $returned_id = null;

    /**
     * Keeps native bind variables alive until statement execution has completed.
     *
     * @var array<int, int|string|null>
     *
     * @author Oleg Schildt
     */
    private array $bound_values = [];

    /**
     * Keeps Oracle input LOB descriptors alive while statements or results may reference them.
     *
     * @var array<int, \OCILob>
     *
     * @author Oleg Schildt
     */
    private array $lob_descriptors = [];

    /**
     * Returns the SQL dialect used by the placeholder compiler.
     *
     * @return string
     * Returns "oracle".
     *
     * @see PreparedQuery::compile()
     *
     * @author Oleg Schildt
     */
    protected function dialect(): string
    {
        // Keep marker translation independent of the human-readable database name.
        return 'oracle';
    } // dialect

    /**
     * Returns the name of the required PHP extension - "oci8".
     *
     * @return string
     * Returns "oci8".
     *
     * @see DBWorker::is_extension_installed()
     *
     * @author Oleg Schildt
     */
    public function get_extension_name(): string
    {
        // Expose the extension identifier used by extension_loaded() and the factory.
        return 'oci8';
    } // get_extension_name

    /**
     * Returns the name of the supported database - "Oracle Database".
     *
     * @return string
     * Returns "Oracle Database".
     *
     * @author Oleg Schildt
     */
    public function get_rdbms_name(): string
    {
        // Expose the database name for diagnostics and application messages.
        return 'Oracle Database';
    } // get_rdbms_name

    /**
     * Creates a clone of the DBWorker that uses the same open connection.
     *
     * This is useful for additional queries while iterating over an active result set.
     * Closing one worker does not close the shared connection while another worker owns it.
     * Native driver restrictions on concurrent results still apply.
     *
     * @return Oracle_DBWorker
     * Returns a worker sharing the connection and transaction state, with independent statements and results.
     *
     * @author Oleg Schildt
     */
    public function create_clone(): Oracle_DBWorker
    {
        // Use the base clone hook to separate per-worker handles while retaining shared connection ownership.
        return clone $this;
    } // create_clone

    /**
     * Returns the state of this worker's native connection.
     *
     * @return bool
     * Returns true if an open OCI8 connection resource is attached; otherwise returns false.
     *
     * @see DBWorker::check_connection()
     * @see Oracle_DBWorker::connect()
     * @see DBWorker::close_connection()
     *
     * @author Oleg Schildt
     */
    public function is_connected(): bool
    {
        // Inspect the attached native handle without opening another connection.
        return is_resource($this->connection_state?->handle);
    } // is_connected

    /**
     * Resets driver-specific state after cloning or statement cleanup.
     *
     * Detaches copied input descriptors and bind/output state without freeing the original
     * worker's resources or changing the shared transaction.
     *
     * @return void
     * Does not return a value.
     *
     * @author Oleg Schildt
     */
    protected function reset_driver_state(): void
    {
        // Reset only worker-owned native state; shared connection attributes remain intact.
        $this->return_identity = false;
        $this->returned_id = null;
        $this->bound_values = $this->lob_descriptors = [];
    } // reset_driver_state

    /**
     * Returns the most recent OCI8 error for a specific handle or the global connection operation.
     *
     * @param resource|null $handle
     * An OCI8 statement/connection resource, or NULL to inspect the global OCI8 error.
     *
     * @return string
     * Returns the OCI8 message, or a fallback message if no native error is available.
     *
     * @author Oleg Schildt
     */
    private function errors(mixed $handle = null): string
    {
        // Read native diagnostics before another driver call can replace them.
        $error = $handle === null ? oci_error() : oci_error($handle);

        return $error['message'] ?? 'Oracle operation failed.';
    } // errors

    /**
     * Establishes the database connection using the initialized settings.
     *
     * Uses AL32UTF8 and deterministic NLS date/time and numeric formats.
     * A complete connection_string is used as supplied. Otherwise db_server may contain an
     * Easy Connect descriptor, or a host combined with the db_name service. An optional
     * db_schema changes CURRENT_SCHEMA. A read-only worker starts a managed read-only transaction.
     * An already connected worker keeps its existing connection.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If connection settings are incomplete, connection or session initialization fails, the requested
     * call timeout cannot be configured, or a read-only transaction cannot be started.
     *
     * @see DBWorker::init()
     * @see Oracle_DBWorker::is_connected()
     * @see DBWorker::close_connection()
     *
     * @author Oleg Schildt
     */
    public function connect(): void
    {
        // Reuse an attached connection and validate the settings before creating a new native handle.
        if ($this->is_connected()) {
            return;
        }

        $dsn = (string) ($this->options['connection_string'] ?? $this->db_server);

        if ($dsn === '') {
            $dsn = $this->db_name;
        } elseif (
            !isset($this->options['connection_string'])
            && !str_contains($dsn, '/')
            && !str_contains($dsn, '(')
            && $this->db_name !== ''
        ) {
            $dsn .= ($this->db_port === '' ? '' : ':' . (int) $this->db_port) . '/' . $this->db_name;
        }

        if ($dsn === '' || $this->db_user === '') {
            throw new DBWorkerException('Connection data is incomplete.', self::ERR_CONNECTION_DATA_INCOMPLETE);
        }

        $db = @oci_new_connect($this->db_user, $this->db_password, $dsn, 'AL32UTF8');

        if ($db === false) {
            throw new DBWorkerException($this->errors(), self::ERR_CONNECTION_FAILED);
        }

        // Share ownership and transaction state, rather than independent auto-commit flags in each clone.
        $this->connection_state = new DBConnectionState($db, static fn ($db) => oci_close($db));

        try {
            $this->control("ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");

            $this->control("ALTER SESSION SET NLS_TIMESTAMP_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");

            $this->control("ALTER SESSION SET NLS_TIMESTAMP_TZ_FORMAT = 'YYYY-MM-DD HH24:MI:SS TZH:TZM'");

            $this->control("ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '.,'");

            $level = strtoupper((string) ($this->options['isolation_level'] ?? 'READ COMMITTED'));

            if (!in_array($level, ['READ COMMITTED', 'SERIALIZABLE'], true)) {
                throw new DBWorkerException('Invalid Oracle isolation level.', self::ERR_WRONG_DATA_FORMAT);
            }

            $this->control('ALTER SESSION SET ISOLATION_LEVEL = ' . $level);

            $this->connection_state->attributes['isolation_level'] = $level;

            if (isset($this->options['db_schema'])) {
                $this->use_database((string) $this->options['db_schema']);
            }

            if (isset($this->options['query_timeout'])) {
                if (
                    !function_exists('oci_set_call_timeout')
                    || !@oci_set_call_timeout($db, (int) $this->options['query_timeout'] * 1000)
                ) {
                    throw $this->query_error('This Oracle client cannot configure the requested call timeout.');
                }
            }

            if ($this->read_only) {
                $this->start_transaction();
            }
        } catch (\Throwable $error) {
            $this->connection_state = null;

            throw $error;
        }
    } // connect

    /**
     * Executes an internal Oracle session or transaction command without replacing the public result.
     *
     * The temporary OCI8 statement is always released.
     *
     * @param string $sql
     * The internal SQL command.
     *
     * @param int $mode
     * The OCI8 execution mode. The default commits on success; transaction commands can disable automatic commit.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If parsing or executing the command fails.
     *
     * @author Oleg Schildt
     */
    private function control(string $sql, int $mode = OCI_COMMIT_ON_SUCCESS): void
    {
        // Use a temporary native handle so an internal command cannot replace the caller's active result.
        $stmt = @oci_parse($this->connection_state->handle, $sql);

        if ($stmt === false) {
            throw $this->query_error($this->errors($this->connection_state->handle));
        }

        try {
            if (!@oci_execute($stmt, $mode)) {
                throw $this->query_error($this->errors($stmt));
            }
        } finally {
            oci_free_statement($stmt);
        }
    } // control

    /**
     * Returns the Oracle session's selected schema.
     *
     * @return string
     * Returns the shared CURRENT_SCHEMA value, or the configured/default schema before switching.
     *
     * @see Oracle_DBWorker::use_database()
     *
     * @author Oleg Schildt
     */
    public function get_schema(): string
    {
        // Prefer the schema recorded on the shared connection so clones observe the same session setting.
        return (string) ($this->connection_state?->attributes['schema'] ?? parent::get_schema());
    } // get_schema

    /**
     * Changes CURRENT_SCHEMA for the Oracle session.
     *
     * This operation does not switch the database service. The selected schema is shared
     * by cloned workers, and the shared identity-column cache is invalidated.
     *
     * @param string $db_name
     * The schema name. Unquoted names are converted to uppercase; quoted names preserve their case.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If no connection is open or Oracle cannot parse or execute the schema change.
     *
     * @see Oracle_DBWorker::get_schema()
     *
     * @author Oleg Schildt
     */
    public function use_database(string $db_name): void
    {
        // Change the shared session schema without switching the Oracle database service.
        $this->check_connection();

        $name = str_starts_with($db_name, '"') && str_ends_with($db_name, '"')
            ? str_replace('""', '"', substr($db_name, 1, -1)) : strtoupper($db_name);

        $this->control(
            'ALTER SESSION SET CURRENT_SCHEMA = "' . str_replace('"', '""', $name) . '"',
            OCI_NO_AUTO_COMMIT
        );

        $this->connection_state->attributes['schema'] = $name;
        $this->connection_state->attributes['identity_columns'] = [];
    } // use_database

    /**
     * Normalizes Oracle statement terminators while preserving PL/SQL blocks.
     *
     * @param string $sql
     * The application SQL or PL/SQL text.
     *
     * @return string
     * Returns SQL without a trailing statement terminator, or PL/SQL with its required semicolons preserved.
     *
     * @author Oleg Schildt
     */
    private function normalize_sql(string $sql): string
    {
        // PL/SQL needs its terminating semicolons; ordinary OCI SQL must omit the client delimiter.
        if (preg_match(
                '/\A(?:BEGIN|DECLARE|CREATE(?:\s+OR\s+REPLACE)?\s+(?:PROCEDURE|FUNCTION|PACKAGE|TRIGGER|TYPE))\b/i',
                $this->sql_body($sql)
            )) {
            return rtrim($sql);
        }

        return rtrim($sql, " \t\r\n;");
    } // normalize_sql

    /**
     * Adds RETURNING for the discovered identity column of an eligible Oracle INSERT VALUES statement.
     *
     * Respects quoted and schema-qualified table names. Discovery uses ALL_TAB_COLUMNS for
     * the actual owner and table. Existing RETURNING clauses and auto_returning => false
     * disable automatic extension; no column named id is assumed.
     *
     * @param string $sql
     * The normalized SQL statement to inspect.
     *
     * @return string
     * Returns SQL extended with an output bind, or the original SQL when automatic handling does not apply.
     *
     * @throws DBWorkerException
     * If identity-column metadata cannot be read.
     *
     * @author Oleg Schildt
     */
    private function with_identity(string $sql): string
    {
        // Capture an identifier from the INSERT itself instead of consulting unrelated session sequence state.
        $this->return_identity = false;
        $table = $this->insert_table($sql);

        if (
            $table === null || ($this->options['auto_returning'] ?? true) === false
            || preg_match('/\bRETURNING\b/i', $sql) || !preg_match('/\bVALUES\s*\(/i', $sql)
        ) {
            return $sql;
        }

        preg_match_all('/"((?:[^"]|"")+)"|([a-zA-Z_][a-zA-Z0-9_$#]*)/', $table, $matches, PREG_SET_ORDER);

        $parts = array_map(
            static fn (array $part): string => $part[1] !== ''
                    ? str_replace('""', '"', $part[1]) : strtoupper($part[2]),
            $matches
        );
        $name = array_pop($parts);
        $owner = $parts[0] ?? $this->get_schema();
        $cache = &$this->connection_state->attributes['identity_columns'];
        $key = $owner . '.' . $name;

        if (!array_key_exists($key, $cache ?? [])) {
            $stmt = @oci_parse(
                $this->connection_state->handle,
                "SELECT column_name FROM all_tab_columns WHERE owner = :owner AND table_name = :table_name AND identity_column = 'YES' ORDER BY column_id"
            );

            if ($stmt === false) {
                throw $this->query_error($this->errors($this->connection_state->handle));
            }

            try {
                oci_bind_by_name($stmt, ':owner', $owner);

                oci_bind_by_name($stmt, ':table_name', $name);

                if (!@oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
                    throw $this->query_error($this->errors($stmt));
                }

                $row = oci_fetch_row($stmt);
                $cache[$key] = $row === false ? null : $row[0];
            } finally {
                oci_free_statement($stmt);
            }
        }

        if ($cache[$key] !== null) {
            $this->return_identity = true;
            $sql = rtrim($sql, " \t\r\n;") . ' RETURNING "'
                . str_replace('"', '""', $cache[$key]) . '" INTO :sf_generated_id';
        }

        return $sql;
    } // with_identity

    /**
     * Chooses the Oracle execution mode from the shared transaction state.
     *
     * Read-only workers automatically start another read-only transaction after completion of the previous one.
     *
     * @return int
     * Returns OCI_NO_AUTO_COMMIT for an active transaction, otherwise OCI_COMMIT_ON_SUCCESS.
     *
     * @throws DBWorkerException
     * If a required read-only transaction cannot be started.
     *
     * @author Oleg Schildt
     */
    private function execution_mode(): int
    {
        // Read the shared transaction flag so a clone cannot accidentally commit its owner's transaction.
        if ($this->read_only && !$this->connection_state->transaction) {
            $this->start_transaction();
        }

        return $this->connection_state->transaction ? OCI_NO_AUTO_COMMIT : OCI_COMMIT_ON_SUCCESS;
    } // execution_mode

    /**
     * Executes SQL through the native driver and records the resulting metadata.
     *
     * @param string $sql
     * The SQL query to execute. Driver-specific identity handling may extend it.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If native query execution fails.
     *
     * @see DBWorker::execute_query()
     *
     * @author Oleg Schildt
     */
    protected function native_query(string $sql): void
    {
        // Execute the native statement, then expose its result through the shared result contract.
        $sql = $this->with_identity($this->normalize_sql($sql));
        $stmt = @oci_parse($this->connection_state->handle, $sql);

        if ($stmt === false) {
            throw $this->query_error($this->errors($this->connection_state->handle));
        }

        try {
            $this->bind_identity($stmt);

            if (!@oci_execute($stmt, $this->execution_mode())) {
                throw $this->query_error($this->errors($stmt));
            }

            $this->result = $stmt;

            $this->accept_result($stmt);
        } catch (\Throwable $error) {
            oci_free_statement($stmt);

            $this->result = null;

            throw $error;
        }
    } // native_query

    /**
     * Creates a native statement from SQL whose bind markers have already been compiled.
     *
     * @param string $sql
     * The SQL query containing native bind markers.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If the native statement cannot be prepared.
     *
     * @see DBWorker::prepare_query()
     *
     * @author Oleg Schildt
     */
    protected function native_prepare(string $sql): void
    {
        // Keep native statement preparation separate from public placeholder compilation.
        $sql = $this->with_identity($this->normalize_sql($sql));
        $stmt = @oci_parse($this->connection_state->handle, $sql);

        if ($stmt === false) {
            throw $this->query_error($this->errors($this->connection_state->handle));
        }

        $this->statement = $stmt;
    } // native_prepare

    /**
     * Binds validated values and executes the native prepared statement.
     *
     * Binds every OCI8 descriptor through its own persistent array entry. Explicit LOB
     * wrappers and plain strings longer than 4000 bytes use temporary LOB descriptors.
     * Input locators remain alive while the statement or result can reference them.
     * Execution mode is chosen from the connection-wide transaction flag.
     *
     * @param list<scalar|null|BlobValue|ClobValue> $values
     * The normalized values in SQL occurrence order, including any explicit LOB wrappers.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If binding, LOB processing or native execution fails.
     *
     * @see DBWorker::execute_prepared_query()
     *
     * @author Oleg Schildt
     */
    protected function native_execute(array $values): void
    {
        // Bind stable per-position values so repeated application names cannot share a loop variable by accident.
        // Retire previous input locators only after the old result has been released.
        $this->free_lobs();

        $this->bound_values = [];

        try {
            foreach ($values as $index => $value) {
                $name = ':p' . ($index + 1);

                if (
                    $value instanceof ClobValue
                    || $value instanceof BlobValue
                    || (is_string($value) && strlen($value) > 4000)
                ) {
                    $binary = $value instanceof BlobValue;
                    $source = is_string($value) ? $value : $value->value;
                    $lob = oci_new_descriptor($this->connection_state->handle, OCI_D_LOB);

                    if ($lob === false) {
                        throw $this->stream_error('Cannot allocate an Oracle LOB descriptor.');
                    }

                    $this->lob_descriptors[$index] = $lob;

                    if (!$lob->writeTemporary('', $binary ? OCI_TEMP_BLOB : OCI_TEMP_CLOB)) {
                        throw $this->stream_error('Cannot create a temporary Oracle LOB.');
                    }

                    $this->write_chunks(
                        $source,
                        function (string $chunk) use ($index, $binary): void {
                            if (!$binary) {
                                $written = $this->lob_descriptors[$index]->write($chunk);

                                // OCI reports UCS-2 units for CLOBs, including surrogate pairs,
                                // whereas the PHP input string is measured in UTF-8 bytes.
                                $units = strlen(mb_convert_encoding($chunk, 'UTF-16LE', 'UTF-8')) / 2;

                                if ($written === false || $written !== (int) $units) {
                                    throw $this->stream_error('Incomplete Oracle CLOB write.');
                                }

                                return;
                            }

                            $offset = 0;

                            while ($offset < strlen($chunk)) {
                                $written = $this->lob_descriptors[$index]->write(substr($chunk, $offset));

                                if ($written === false || $written === 0) {
                                    throw $this->stream_error('Cannot write Oracle LOB.');
                                }

                                $offset += $written;
                            }
                        },
                        !$binary
                    );

                    // Bind each descriptor's own stable array location, never the loop variable.
                    $ok = @oci_bind_by_name(
                        $this->statement,
                        $name,
                        $this->lob_descriptors[$index],
                        -1,
                        $binary ? OCI_B_BLOB : OCI_B_CLOB
                    );
                } else {
                    $this->bound_values[$index] = is_bool($value) ? (int) $value
                        : (is_float($value) ? json_encode($value, JSON_PRESERVE_ZERO_FRACTION) : $value);
                    $ok = @oci_bind_by_name(
                        $this->statement,
                        $name,
                        $this->bound_values[$index],
                        -1,
                        is_int($this->bound_values[$index]) ? SQLT_INT : SQLT_CHR
                    );
                }

                if (!$ok) {
                    throw $this->query_error($this->errors($this->statement));
                }
            }

            // Keep the optional output bind separate from user-supplied input positions.
            $this->bind_identity($this->statement);

            if (!@oci_execute($this->statement, $this->execution_mode())) {
                throw $this->query_error($this->errors($this->statement));
            }

            $this->result = $this->statement;

            $this->accept_result($this->statement);
        } catch (\Throwable $error) {
            $this->free_lobs();

            throw $error;
        }
    } // native_execute

    /**
     * Binds the output variable used by an automatically appended Oracle RETURNING clause.
     *
     * The output value is stored in an object property so its storage remains valid through execution.
     *
     * @param resource $statement
     * The parsed OCI8 statement to receive the generated identifier.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If the native bind operation fails.
     *
     * @author Oleg Schildt
     */
    private function bind_identity($statement): void
    {
        // Use object-owned output storage that remains alive until OCI execution has completed.
        $this->returned_id = null;

        if (
            $this->return_identity
            && !oci_bind_by_name($statement, ':sf_generated_id', $this->returned_id, 128, SQLT_CHR)
        ) {
            throw $this->query_error($this->errors($statement));
        }
    } // bind_identity

    /**
     * Captures Oracle column metadata, DML counts and an automatically returned identity.
     *
     * Ordinary uppercase Oracle column names are exposed in lowercase. Case-distinct quoted
     * aliases remain distinct. LOB contents are not loaded while inspecting metadata.
     *
     * @param resource $statement
     * The successfully executed OCI8 statement resource.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If the returned identity exceeds the PHP integer range.
     *
     * @author Oleg Schildt
     */
    private function accept_result($statement): void
    {
        // Record metadata without advancing the application row cursor.
        $names = [];

        for ($i = 1; $i <= oci_num_fields($statement); $i++) {
            $names[] = oci_field_name($statement, $i);
        }

        $counts = array_count_values(array_map(strtolower(...), $names));
        $metadata = [];

        foreach ($names as $index => $name) {
            $num = $index + 1;
            $type = strtolower(oci_field_type($statement, $num));
            $numeric = in_array(
                $type,
                ['number', 'float', 'binary_float', 'binary_double', 'integer', 'smallint'],
                true
            );
            $binary = in_array($type, ['raw', 'long raw', 'blob', 'bfile'], true);
            $datetime = $type === 'date' || str_starts_with($type, 'timestamp');

            // Keep distinct quoted aliases; normal Oracle identifiers use lower-case API keys.
            $api_name = $counts[strtolower($name)] === 1 && strtoupper($name) === $name ? strtolower($name) : $name;
            $metadata[] = [
                'name' => $api_name,
                'type' => $type,
                'size' => oci_field_size($statement, $num),
                'prec' => oci_field_precision($statement, $num),
                'scale' => oci_field_scale($statement, $num),
                'numeric' => (int) $numeric,
                'binary' => (int) $binary,
                'datetime' => (int) $datetime,
                'string' => (int) in_array(
                    $type,
                    ['varchar2', 'nvarchar2', 'char', 'nchar', 'clob', 'nclob', 'long'],
                    true
                )
            ];
        }

        $this->set_result($metadata, oci_num_rows($statement));

        if ($this->return_identity && $this->returned_id !== null) {
            if (filter_var($this->returned_id, FILTER_VALIDATE_INT) === false) {
                throw new DBWorkerException(
                    'Generated identifier exceeds the PHP integer range.',
                    self::ERR_WRONG_DATA_FORMAT
                );
            }

            $this->last_insert_id = (int) $this->returned_id;
        }
    } // accept_result

    /**
     * Fetches the next row through the native driver and releases the cursor at EOF.
     *
     * Repeated calls after EOF return NULL without fetching from an exhausted OCI8 statement.
     *
     * @return list<mixed>|null
     * Returns a numerically indexed row, or NULL at the end of the result set.
     *
     * @throws DBWorkerException
     * If the native fetch operation fails.
     *
     * @see DBWorker::fetch_row()
     *
     * @author Oleg Schildt
     */
    protected function native_fetch(): ?array
    {
        if ($this->result === null) {
            return null;
        }

        // Preserve numeric field positions and distinguish end-of-result from a native error.
        $row = @oci_fetch_array($this->result, OCI_NUM | OCI_RETURN_NULLS);

        if ($row === false && oci_error($this->result) !== false) {
            throw $this->query_error($this->errors($this->result));
        }

        if ($row === false) {
            $this->native_free_result();

            return null;
        }

        return $row;
    } // native_fetch

    /**
     * Releases native result resources without discarding a reusable prepared statement.
     *
     * Implementations must distinguish a result handle from a statement handle when both refer to the same resource.
     *
     * @return void
     * Does not return a value.
     *
     * @see DBWorker::free_result()
     *
     * @author Oleg Schildt
     */
    protected function native_free_result(): void
    {
        // Release result ownership while leaving a reusable prepared statement available.
        if (is_resource($this->result)) {
            // Cancel the cursor without freeing a statement that must remain reusable.
            if ($this->result === $this->statement) {
                oci_cancel($this->result);
            } else {
                oci_free_statement($this->result);
            }
        }

        $this->result = null;
    } // native_free_result

    /**
     * Frees temporary Oracle LOB descriptors retained by the prepared statement.
     *
     * Input descriptors are kept alive while the statement and its result may still reference them.
     *
     * @return void
     * Does not return a value.
     *
     * @author Oleg Schildt
     */
    private function free_lobs(): void
    {
        // Release each retained descriptor once the statement no longer needs its input locators.
        foreach ($this->lob_descriptors as $lob) {
            $lob->free();
        }

        $this->lob_descriptors = [];
    } // free_lobs

    /**
     * Releases the native prepared statement and its driver-specific bind state.
     *
     * @return void
     * Does not return a value.
     *
     * @see DBWorker::free_prepared_query()
     *
     * @author Oleg Schildt
     */
    protected function native_free_statement(): void
    {
        // Release native statement ownership and clear driver-specific bind state.
        if (is_resource($this->statement)) {
            oci_free_statement($this->statement);
        }

        $this->statement = null;

        $this->free_lobs();

        $this->bound_values = [];
        $this->return_identity = false;
    } // native_free_statement

    /**
     * Performs a native transaction operation on the shared connection.
     *
     * The base class updates the shared transaction flag only after this operation succeeds.
     *
     * @param string $action
     * The operation: begin, commit or rollback.
     *
     * @param string $isolation_level
     * READ COMMITTED or SERIALIZABLE for begin; REPEATABLE READ is rejected.
     * Ignored for commit and rollback. Read-only mode retains Oracle's snapshot-read semantics.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If REPEATABLE READ is requested or the native transaction operation fails.
     *
     * @author Oleg Schildt
     */
    protected function native_transaction(string $action, string $isolation_level = self::ISOLATION_READ_COMMITTED): void
    {
        // Perform the native operation; the base worker updates the shared flag after success.
        $db = $this->connection_state->handle;

        if ($action === 'begin') {
            if ($isolation_level === self::ISOLATION_REPEATABLE_READ) {
                throw new DBWorkerException('Oracle does not support REPEATABLE READ isolation.', self::ERR_WRONG_DATA_FORMAT);
            }

            if ($this->read_only) {
                $this->control('SET TRANSACTION READ ONLY', OCI_NO_AUTO_COMMIT);
            } else {
                // SET TRANSACTION must precede all reads and writes in the new transaction.
                $this->control('SET TRANSACTION ISOLATION LEVEL ' . $isolation_level, OCI_NO_AUTO_COMMIT);
            }

            return;
        }

        $ok = $action === 'commit' ? oci_commit($db) : oci_rollback($db);

        if (!$ok) {
            throw $this->query_error($this->errors($db));
        }
    } // native_transaction

    /**
     * Converts a native field value into a value exposed by the public field accessors.
     *
     * Date conversion is performed by field_by_num() after native value decoding.
     *
     * @param int $index
     * The zero-based index of a field in the current row.
     *
     * @param int $type
     * The requested DBWorker conversion type.
     *
     * @return mixed
     * Returns the native field value, or the complete LOB contents as a string.
     *
     * @throws DBWorkerException
     * If a stream is invalid or a read/write operation fails.
     *
     * @author Oleg Schildt
     */
    protected function decode_value(int $index, int $type): mixed
    {
        // Read a complete LOB only for value-returning accessors; streaming callers use the locator directly.
        if (!$this->row[$index] instanceof \OCILob) {
            return $this->row[$index];
        }

        $stream = fopen('php://temp/maxmemory:1048576', 'w+b');

        try {
            $this->copy_value_to_stream($index, $stream);

            rewind($stream);

            $value = stream_get_contents($stream);

            if ($value === false) {
                throw $this->stream_error('Cannot read LOB contents.');
            }

            return $value;
        } finally {
            fclose($stream);
        }
    } // decode_value

    /**
     * Copies one native field value to the destination stream.
     *
     * Drivers override this hook to read native LOB objects without first materializing their contents.
     *
     * @param int $index
     * The zero-based index of the field in the current row.
     *
     * @param resource $stream
     * The caller-owned writable destination stream.
     *
     * @return int
     * Returns the number of bytes written; NULL fields produce zero bytes.
     *
     * @throws DBWorkerException
     * If a stream is invalid or a read/write operation fails.
     *
     * @author Oleg Schildt
     */
    protected function copy_value_to_stream(int $index, $stream): int
    {
        // Read the native locator incrementally and count bytes actually written to the destination.
        $lob = $this->row[$index];

        if (!$lob instanceof \OCILob) {
            return parent::copy_value_to_stream($index, $stream);
        }

        if (!$lob->rewind()) {
            throw $this->stream_error('Cannot rewind Oracle LOB.');
        }

        $count = 0;

        while (!$lob->eof()) {
            $chunk = $lob->read(65536);

            if ($chunk === false || ($chunk === '' && !$lob->eof())) {
                throw $this->stream_error('Cannot read Oracle LOB.');
            }

            $count += $this->write_all($stream, $chunk);
        }

        return $count;
    } // copy_value_to_stream

    /**
     * Escapes a string for inclusion in an SQL string literal without adding quotation marks.
     *
     * The result is intended for string literals, not SQL identifiers.
     * Prefer bound parameters when supplying application values to a query.
     * This operation does not require an open database connection.
     *
     * @param string $str
     * The string to escape.
     *
     * @return string
     * Returns the escaped string.
     *
     * @see DBWorker::quotes_or_null()
     * @see DBWorker::prepare_query()
     *
     * @author Oleg Schildt
     */
    public function escape(string $str): string
    {
        // Oracle string literals represent an apostrophe by doubling it.
        return str_replace("'", "''", $str);
    } // escape

    /**
     * Formats a date as an SQL expression for the selected database.
     *
     * @param int|\DateTimeInterface|null $date
     * A Unix timestamp, a date/time object, or NULL. Timestamp zero is a valid date.
     * Objects retain their timezone; integer timestamps use the configured PHP timezone.
     *
     * @return string
     * Returns the quoted date expression, or the SQL literal null.
     *
     * @see DBWorker::format_date_bind()
     *
     * @author Oleg Schildt
     */
    public function format_date(int|\DateTimeInterface|null $date): string
    {
        // Format the value separately from the SQL expression that quotes or converts it.
        $value = $this->format_date_bind($date);

        return $value === null ? 'null' : "TO_DATE('" . $value . "', 'YYYY-MM-DD')";
    } // format_date

    /**
     * Formats a date/time as an SQL expression for the selected database.
     *
     * @param int|\DateTimeInterface|null $datetime
     * A Unix timestamp, a date/time object, or NULL. Timestamp zero is a valid date.
     * Objects retain their timezone; integer timestamps use the configured PHP timezone.
     *
     * @return string
     * Returns the quoted date expression, or the SQL literal null.
     *
     * @see DBWorker::format_datetime_bind()
     *
     * @author Oleg Schildt
     */
    public function format_datetime(int|\DateTimeInterface|null $datetime): string
    {
        // Format the value separately from the SQL expression that quotes or converts it.
        $value = $this->format_datetime_bind($datetime);

        return $value === null ? 'null' : "TO_TIMESTAMP('" . $value . "', 'YYYY-MM-DD HH24:MI:SS')";
    } // format_datetime
    /**
     * Resolves Oracle table names, native comparison keys and identity sequence metadata.
     *
     * @param string $table
     * The unquoted table name, optionally schema-qualified.
     *
     * @return array
     * Returns canonical table names and native column metadata.
     *
     * @throws DBWorkerException
     * If the target cannot be resolved or its column metadata cannot be read.
     *
     * @author Oleg Schildt
     */
    protected function native_save_table(string $table): array
    {
        $parts = explode('.', strtoupper($table));

        if (count($parts) > 2) {
            throw new DBWorkerException('Oracle table names support schema.table.', self::ERR_WRONG_DATA_FORMAT);
        }

        $name = array_pop($parts);
        $owner = $parts[0] ?? $this->get_schema();
        $rows = $this->save_sql("SELECT SYS_CONTEXT('USERENV', 'DB_UNIQUE_NAME'), owner, table_name FROM all_tables WHERE owner = ? AND table_name = ?", [$owner, $name]);

        if (count($rows['rows']) !== 1) {
            throw $this->query_error('The save_records table was not found.');
        }

        [$database, $owner, $name] = $rows['rows'][0];
        $quote = static fn (string $name): string => '"' . str_replace('"', '""', $name) . '"';
        $session = $this->save_sql("SELECT parameter, value FROM nls_session_parameters WHERE parameter IN ('NLS_COMP', 'NLS_SORT')");
        $nls = array_column($session['rows'], 1, 0);
        $rows = $this->save_sql('SELECT c.column_name, c.data_type, c.char_length, c.data_precision, c.data_scale, c.collation, '
            . 'i.sequence_name, i.generation_type FROM all_tab_columns c LEFT JOIN all_tab_identity_cols i '
            . 'ON i.owner = c.owner AND i.table_name = c.table_name AND i.column_name = c.column_name '
            . 'WHERE c.owner = ? AND c.table_name = ?', [$owner, $name]);
        $context = ['table' => $quote($owner) . '.' . $quote($name), 'lock_table' => $quote($this->get_schema()) . '."DBWORKER_LOCKS"',
            'resource' => [$database, $owner, $name], 'columns' => []];

        foreach ($rows['rows'] as [$field, $type, $length, $precision, $scale, $collation, $sequence, $generation]) {
            $native = match ($type) {
                'VARCHAR2', 'CHAR' => $type . '(' . $length . ' CHAR)',
                'NVARCHAR2', 'NCHAR' => $type . '(' . $length . ')',
                'NUMBER' => $precision === null ? 'NUMBER' : 'NUMBER(' . $precision . ',' . (int) $scale . ')',
                default => $type
            };
            $cast = 'CAST(? AS ' . $native . ')';
            $fingerprint = null;

            if (in_array($type, ['VARCHAR2', 'CHAR', 'NVARCHAR2', 'NCHAR'], true)) {
                $sort = $collation === 'USING_NLS_COMP' || $collation === null
                    ? (($nls['NLS_COMP'] ?? 'BINARY') === 'BINARY' ? 'BINARY' : $nls['NLS_SORT']) : $collation;

                if (!preg_match('/\A[A-Z0-9_]+\z/', $sort)) {
                    throw $this->query_error('Unsupported Oracle record-key collation.');
                }

                $fingerprint = "RAWTOHEX(NLSSORT(" . $cast . ", 'NLS_SORT=" . $sort . "'))";
            } elseif ($type === 'NUMBER' || $type === 'FLOAT' || $type === 'BINARY_FLOAT' || $type === 'BINARY_DOUBLE'
                || $type === 'DATE' || str_starts_with($type, 'TIMESTAMP')) {
                $fingerprint = 'DUMP(' . $cast . ', 1016)';
            }

            $context['columns'][strtolower($field)] = ['name' => $field, 'cast' => $cast, 'fingerprint' => $fingerprint,
                'identity' => $sequence !== null, 'sequence' => $sequence === null ? null : $quote($owner) . '.' . $quote($sequence),
                'always' => $generation === 'ALWAYS'];
        }

        return $context;
    } // native_save_table

    /**
     * Normalizes Oracle keys using native numeric representations and linguistic sort keys.
     *
     * @param array $context
     * The resolved table, column metadata and service-table location.
     *
     * @param array<string, scalar|null> $keys
     * The supplied key values, ordered by canonical column name.
     *
     * @return array
     * Returns the equality-compatible key fingerprints.
     *
     * @throws DBWorkerException
     * If a key type is unsupported or converting a supplied key fails.
     *
     * @author Oleg Schildt
     */
    protected function native_save_key(array $context, array $keys): array
    {
        $expressions = [];

        foreach ($keys as $field => $value) {
            $column = $this->save_column($context, $field);

            if ($column['fingerprint'] === null) {
                throw new DBWorkerException('Unsupported Oracle record-key type: ' . $field, self::ERR_WRONG_DATA_FORMAT);
            }

            $expressions[] = $column['fingerprint'];
        }

        $result = $this->save_sql('SELECT ' . implode(', ', $expressions) . ' FROM dual', array_values($keys));

        return $result['rows'][0];
    } // native_save_key

    /**
     * Creates or locks an Oracle service row while handling only its expected primary-key duplicate.
     *
     * @param array $context
     * The resolved table, column metadata and service-table location.
     *
     * @param string $key
     * The SHA-256 resource key.
     *
     * @param string $name
     * The diagnostic description, or allocator watermark for an identity resource.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If service-row creation or lock acquisition fails; expected primary-key duplicates are handled internally.
     *
     * @author Oleg Schildt
     */
    protected function native_save_lock(array $context, string $key, string $name): void
    {
        // This table has only the resource PK; its duplicate is the expected concurrent-creation case.
        $this->save_sql('BEGIN BEGIN INSERT INTO ' . $context['lock_table']
            . ' (resource_key, resource_name) VALUES (?, ?); EXCEPTION WHEN DUP_VAL_ON_INDEX THEN NULL; END; END;', [$key, $name]);

        $this->save_sql('SELECT resource_key FROM ' . $context['lock_table'] . ' WHERE resource_key = ? FOR UPDATE', [$key]);
    } // native_save_lock

    /**
     * Requires fresh Oracle statement snapshots after waiting for a service-row lock.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If the caller transaction uses a fixed-snapshot isolation level.
     *
     * @author Oleg Schildt
     */
    protected function native_save_check(): void
    {
        parent::native_save_check();

        // The per-transaction choice overrides the session default recorded by connect().
        $level = $this->connection_state->transaction
            ? $this->connection_state->attributes['transaction_isolation_level'] : self::ISOLATION_READ_COMMITTED;

        if ($level !== 'READ COMMITTED') {
            throw new DBWorkerException('save_records() requires READ COMMITTED in Oracle.', self::ERR_WRONG_DATA_FORMAT);
        }
    } // native_save_check

    /**
     * Obtains an Oracle identity sequence value without altering or committing the target table.
     *
     * @param string $sequence
     * The metadata-resolved quoted sequence name.
     *
     * @return int
     * Returns the allocated native sequence value.
     *
     * @throws DBWorkerException
     * If querying the sequence fails or its value exceeds the PHP integer range.
     *
     * @author Oleg Schildt
     */
    protected function native_save_next_identity(string $sequence): int
    {
        $result = $this->save_sql('SELECT ' . $sequence . '.NEXTVAL FROM dual');

        return $this->save_identity_value($result['rows'][0][0]);
    } // native_save_next_identity

    /**
     * Preserves BY DEFAULT Oracle identities and allocates new IDs without transactional DDL.
     *
     * @param array $context
     * The resolved table, column metadata and service-table location.
     *
     * @param array $values
     * The field values.
     *
     * @param array $expressions
     * The positional SQL expressions.
     *
     * @param string $identity_field
     * The identity field, or an empty string.
     *
     * @return int|null
     * Returns the stored identity, or NULL when none is configured.
     *
     * @throws DBWorkerException
     * If identity handling or inserting the record fails.
     *
     * @author Oleg Schildt
     */
    protected function native_save_insert(array $context, array $values, array $expressions, string $identity_field): ?int
    {
        if ($identity_field === '') {
            return parent::native_save_insert($context, $values, $expressions, $identity_field);
        }

        $column = $this->save_column($context, $identity_field);

        if (($column['always'] ?? false) && !isset($values[$identity_field])) {
            return parent::native_save_insert($context, $values, $expressions, $identity_field);
        }

        $explicit = $values[$identity_field] ?? null;

        do {
            $identity = $this->save_allocate_identity($context, $identity_field, $explicit);
            [$key, $name] = $this->save_resource($context, [strtolower($identity_field) => $identity]);

            $this->native_save_lock($context, $key, $name);

            // Reallocate if an explicit-ID writer committed this candidate before we acquired its lock.
            $occupied = $explicit === null
                && $this->native_save_exists($context, $identity_field . ' = ' . $column['cast'], [$identity]);
        } while ($occupied);

        $values[$identity_field] = $identity;
        $expressions[$identity_field] = '?';

        parent::native_save_insert($context, $values, $expressions, $identity_field);

        return $identity;
    } // native_save_insert


} // Oracle_DBWorker
