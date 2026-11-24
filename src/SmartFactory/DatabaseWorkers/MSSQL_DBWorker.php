<?php

/**
 * This file contains the implementation of the class MSSQL_DBWorker.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\DatabaseWorkers;

/**
 * Database worker for Microsoft SQL Server using the sqlsrv extension.
 *
 * Uses UTF-8 connections with Multiple Active Result Sets disabled.
 * All queries use forward-only cursors without buffering the complete result set.
 * An unread forward-only result must be exhausted or freed before another request uses the connection.
 * Large result fields are spooled to temporary files; Unicode streams are converted
 * from UTF-16LE without splitting surrogate pairs.
 *
 * SQL Server has no native read-only transaction mode, so the read_only option is rejected.
 *
 * @see DBWorker
 *
 * @link markdown:MSSQLNotes.md Important notes
 *
 * @author Oleg Schildt
 */
class MSSQL_DBWorker extends DBWorker
{
    /**
     * Keeps native bind variables alive until statement execution has completed.
     *
     * @var array<int, int|float|string|resource|null>
     *
     * @author Oleg Schildt
     */
    private array $bound_values = [];

    /**
     * Stores sqlsrv parameter descriptors referencing the persistent bound_values entries.
     *
     * @var list<list<mixed>>
     *
     * @author Oleg Schildt
     */
    private array $native_parameters = [];

    /**
     * Stores the serialized sqlsrv parameter types used to decide whether rebinding requires preparation.
     *
     * @var string|null
     *
     * @author Oleg Schildt
     */
    private ?string $parameter_signature = null;

    /**
     * Stores native SQL, including any appended identity query, for sqlsrv re-preparation.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    private string $prepared_sql = '';

    /**
     * Indicates whether the SQL Server INSERT batch includes an internal SCOPE_IDENTITY result.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    private bool $captures_identity = false;

    /**
     * Indicates whether a sqlsrv statement has executed and may need its active results canceled.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    private bool $executed = false;

    /**
     * Returns the SQL dialect used by the placeholder compiler.
     *
     * @return string
     * Returns "mssql".
     *
     * @see PreparedQuery::compile()
     *
     * @author Oleg Schildt
     */
    protected function dialect(): string
    {
        // Keep marker translation independent of the human-readable database name.
        return 'mssql';
    } // dialect

    /**
     * Returns the name of the required PHP extension - "sqlsrv".
     *
     * @return string
     * Returns "sqlsrv".
     *
     * @see DBWorker::is_extension_installed()
     *
     * @author Oleg Schildt
     */
    public function get_extension_name(): string
    {
        // Expose the extension identifier used by extension_loaded() and the factory.
        return 'sqlsrv';
    } // get_extension_name

    /**
     * Returns the name of the supported database - "Microsoft SQL Server".
     *
     * @return string
     * Returns "Microsoft SQL Server".
     *
     * @author Oleg Schildt
     */
    public function get_rdbms_name(): string
    {
        // Expose the database name for diagnostics and application messages.
        return 'Microsoft SQL Server';
    } // get_rdbms_name

    /**
     * Creates a clone of the DBWorker that uses the same open connection.
     *
     * Exhaust or free an active result before issuing another query through a clone.
     * Closing one worker does not close the shared connection while another worker owns it.
     * A clone shares the same SQL Server session and cannot bypass an unread forward-only result;
     * consume that result to EOF or call free_result() before sending another request.
     *
     * @return MSSQL_DBWorker
     * Returns a worker sharing the connection and transaction state, with independent statements and results.
     *
     * @author Oleg Schildt
     */
    public function create_clone(): MSSQL_DBWorker
    {
        // Use the base clone hook to separate per-worker handles while retaining shared connection ownership.
        return clone $this;
    } // create_clone

    /**
     * Returns the state of this worker's native connection.
     *
     * @return bool
     * Returns true if an open native connection resource is attached; otherwise returns false.
     *
     * @see DBWorker::check_connection()
     * @see MSSQL_DBWorker::connect()
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
     * Clears bind arrays, cached parameter types, statement SQL and execution flags.
     * The shared native connection is retained.
     *
     * @return void
     * Does not return a value.
     *
     * @author Oleg Schildt
     */
    protected function reset_driver_state(): void
    {
        // Reset only worker-owned native state; shared connection attributes remain intact.
        $this->bound_values = $this->native_parameters = [];
        $this->parameter_signature = null;
        $this->prepared_sql = '';
        $this->captures_identity = $this->executed = false;
    } // reset_driver_state

    /**
     * Returns the most recent native driver error message.
     *
     * @return string
     * Returns the native error text, or a fallback message when no details are available.
     *
     * @author Oleg Schildt
     */
    private function errors(): string
    {
        // Read native diagnostics before another driver call can replace them.
        $messages = array_column(sqlsrv_errors(SQLSRV_ERR_ERRORS) ?? [], 'message');

        return implode(PHP_EOL, array_unique($messages)) ?: 'SQL Server operation failed.';
    } // errors

    /**
     * Establishes the database connection using the initialized settings.
     *
     * Uses UTF-8 and string date values with Multiple Active Result Sets disabled.
     * The read_only option is rejected because SQL Server cannot enforce a read-only transaction.
     * An already connected worker keeps its existing connection.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If connection settings are incomplete, read_only is enabled, the isolation level is invalid,
     * or connection or session initialization fails.
     *
     * @see DBWorker::init()
     * @see MSSQL_DBWorker::is_connected()
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

        if ($this->db_server === '' || $this->db_user === '') {
            throw new DBWorkerException('Connection data is incomplete.', self::ERR_CONNECTION_DATA_INCOMPLETE);
        }

        if ($this->read_only) {
            throw new DBWorkerException(
                'SQL Server has no read-only transaction mode; enforce read-only access using database permissions.',
                self::ERR_WRONG_DATA_FORMAT
            );
        }

        $server = $this->db_server . ($this->db_port === '' ? '' : ',' . (int) $this->db_port);
        $config = [
            'UID' => $this->db_user,
            'PWD' => $this->db_password,
            'CharacterSet' => 'UTF-8',
            'ReturnDatesAsStrings' => true,
            'MultipleActiveResultSets' => false,
            'LoginTimeout' => (int) ($this->options['connect_timeout'] ?? 20)
        ];

        if ($this->db_name !== '') {
            $config['Database'] = $this->db_name;
        }

        if (array_key_exists('encrypt', $this->options)) {
            $config['Encrypt'] = (bool) $this->options['encrypt'];
        }

        if (array_key_exists('trust_server_certificate', $this->options)) {
            $config['TrustServerCertificate'] = (bool) $this->options['trust_server_certificate'];
        }

        $db = @sqlsrv_connect($server, $config);

        if ($db === false) {
            throw new DBWorkerException($this->errors(), self::ERR_CONNECTION_FAILED);
        }

        $this->connection_state = new DBConnectionState($db, static fn ($db) => sqlsrv_close($db));

        if (isset($this->options['isolation_level'])) {
            $level = strtoupper((string) $this->options['isolation_level']);

            if (!in_array(
                    $level,
                    ['READ UNCOMMITTED', 'READ COMMITTED', 'REPEATABLE READ', 'SNAPSHOT', 'SERIALIZABLE'],
                    true
                )) {
                $this->connection_state = null;

                throw new DBWorkerException('Invalid transaction isolation level.', self::ERR_WRONG_DATA_FORMAT);
            }

            $result = @sqlsrv_query($db, 'SET TRANSACTION ISOLATION LEVEL ' . $level);

            if ($result === false) {
                $message = $this->errors();
                $this->connection_state = null;

                throw $this->query_error($message);
            }

            sqlsrv_free_stmt($result);
        }
    } // connect

    /**
     * Selects another SQL Server database on the current connection.
     *
     * @param string $db_name
     * The database name, quoted as an identifier in the USE statement.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If no connection is open or SQL Server rejects the USE statement.
     *
     * @author Oleg Schildt
     */
    public function use_database(string $db_name): void
    {
        // Quote the database as an identifier; string-literal escaping is not appropriate for USE.
        $this->execute_query('USE [' . str_replace(']', ']]', $db_name) . ']');

        $this->db_name = $db_name;
    } // use_database

    /**
     * Builds SQL Server statement options for sequential result reading.
     *
     * Unread results keep the shared connection busy until EOF or explicit cancellation.
     *
     * @return array{QueryTimeout: int, Scrollable: string}
     * Returns the query timeout and the forward-only cursor setting.
     *
     * @author Oleg Schildt
     */
    private function statement_options(): array
    {
        return [
            'QueryTimeout' => (int) ($this->options['query_timeout'] ?? 20),
            'Scrollable' => SQLSRV_CURSOR_FORWARD
        ];
    } // statement_options

    /**
     * Appends an identity query to an eligible SQL Server INSERT batch.
     *
     * The internal result is identified by a reserved column alias and consumed by accept_statement().
     *
     * @param string $sql
     * The SQL statement to inspect.
     *
     * @return string
     * Returns SQL with SCOPE_IDENTITY() in the same batch, or the original SQL when OUTPUT is already present.
     *
     * @author Oleg Schildt
     */
    private function with_identity(string $sql): string
    {
        // Capture an identifier from the INSERT itself instead of consulting unrelated session sequence state.
        $this->captures_identity = $this->insert_table($sql) !== null && !preg_match('/\bOUTPUT\b/i', $sql);

        return $this->captures_identity ? rtrim($sql, " \t\r\n;")
            . '; SELECT CAST(SCOPE_IDENTITY() AS bigint) AS smartfactory_insert_id' : $sql;
    } // with_identity

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
     * If native execution fails, including when another worker has a pending forward-only result,
     * or processing the returned results reports a database error.
     *
     * @see DBWorker::execute_query()
     *
     * @author Oleg Schildt
     */
    protected function native_query(string $sql): void
    {
        // Execute the native statement, then expose its result through the shared result contract.
        $sql = $this->with_identity($sql);
        $options = $this->statement_options();
        $stmt = @sqlsrv_query($this->connection_state->handle, $sql, [], $options);

        if ($stmt === false) {
            throw $this->query_error($this->errors());
        }

        $this->statement = $stmt;
        $this->executed = true;

        try {
            $this->accept_statement();
        } catch (\Throwable $error) {
            $this->free_result();

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
        $this->prepared_sql = $this->with_identity($sql);
        $this->bound_values = array_fill(0, count($this->compiled_query->names), null);
        $this->native_parameters = [];

        foreach ($this->bound_values as $index => &$value) {
            $this->native_parameters[$index] = [&$value, SQLSRV_PARAM_IN];
        }

        unset($value);

        $options = $this->statement_options();
        $stmt = @sqlsrv_prepare(
            $this->connection_state->handle,
            $this->prepared_sql,
            $this->native_parameters,
            $options
        );

        if ($stmt === false) {
            throw $this->query_error($this->errors());
        }

        $this->statement = $stmt;
        $this->parameter_signature = null;
    } // native_prepare

    /**
     * Binds validated values and executes the native prepared statement.
     *
     * Uses explicit PHP/SQL parameter types and re-prepares the native statement when its
     * type signature changes. Caller-owned stream parameters are copied into temporary
     * seekable streams, sent before result processing, and released in the cleanup path.
     *
     * @param list<scalar|null|BlobValue|ClobValue> $values
     * The normalized values in SQL occurrence order, including any explicit LOB wrappers.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If binding, LOB processing, execution or result completion fails, including when
     * another worker has a pending forward-only result on the shared connection.
     *
     * @see DBWorker::execute_prepared_query()
     *
     * @author Oleg Schildt
     */
    protected function native_execute(array $values): void
    {
        // Bind stable per-position values so repeated application names cannot share a loop variable by accident.
        $temporary = [];
        $converted = [];
        $types = [];

        try {
            foreach ($values as $index => $value) {
                if ($value instanceof BlobValue || $value instanceof ClobValue) {
                    $binary = $value instanceof BlobValue;

                    if (is_resource($value->value)) {
                        $stream = tmpfile();

                        if ($stream === false) {
                            throw $this->stream_error('Cannot spool a SQL Server stream parameter.');
                        }

                        $temporary[] = $stream;

                        $this->write_chunks(
                            $value->value,
                            fn (string $chunk) => $this->write_all($stream, $chunk),
                            !$binary
                        );

                        rewind($stream);

                        $converted[$index] = $stream;
                        $php = SQLSRV_PHPTYPE_STREAM($binary ? SQLSRV_ENC_BINARY : 'UTF-8');
                    } else {
                        $converted[$index] = $value->value;
                        $php = SQLSRV_PHPTYPE_STRING($binary ? SQLSRV_ENC_BINARY : 'UTF-8');
                    }

                    $types[$index] = [$php, $binary ? SQLSRV_SQLTYPE_VARBINARY('max') : SQLSRV_SQLTYPE_NVARCHAR('max')];
                } elseif (is_int($value) || is_bool($value)) {
                    $converted[$index] = (int) $value;
                    $types[$index] = [SQLSRV_PHPTYPE_INT, SQLSRV_SQLTYPE_BIGINT];
                } elseif (is_float($value)) {
                    $converted[$index] = $value;
                    $types[$index] = [SQLSRV_PHPTYPE_FLOAT, SQLSRV_SQLTYPE_FLOAT];
                } else {
                    $converted[$index] = $value;
                    $types[$index] = [
                        $value === null ? SQLSRV_PHPTYPE_NULL : SQLSRV_PHPTYPE_STRING('UTF-8'),
                        SQLSRV_SQLTYPE_NVARCHAR('max')
                    ];
                }
            }

            // Re-prepare only when native parameter types change; otherwise update the existing referenced values.
            $signature = serialize($types);

            if ($signature !== $this->parameter_signature) {
                if (is_resource($this->statement)) {
                    sqlsrv_free_stmt($this->statement);
                }

                $this->statement = null;
                $this->parameter_signature = null;
                $this->bound_values = $converted;
                $this->native_parameters = [];

                foreach ($types as $index => [$php, $sql]) {
                    $this->native_parameters[$index] = [&$this->bound_values[$index], SQLSRV_PARAM_IN, $php, $sql];
                }

                $options = $this->statement_options();
                $options['SendStreamParamsAtExec'] = false;
                $stmt = @sqlsrv_prepare(
                    $this->connection_state->handle,
                    $this->prepared_sql,
                    $this->native_parameters,
                    $options
                );

                if ($stmt === false) {
                    throw $this->query_error($this->errors());
                }

                $this->statement = $stmt;
                $this->parameter_signature = $signature;
                    } else {
                foreach ($converted as $index => $value) {
                    $this->bound_values[$index] = $value;
                }
            }

            if (!@sqlsrv_execute($this->statement)) {
                throw $this->query_error($this->errors());
            }

            $this->executed = true;

            // Send all stream parameters before reading row counts or returned identity values.
            if ($temporary) {
                while (@sqlsrv_send_stream_data($this->statement)) {
                }

                if (sqlsrv_errors(SQLSRV_ERR_ERRORS)) {
                    throw $this->stream_error($this->errors());
                }
            }

            $this->accept_statement();
        } catch (\Throwable $error) {
            $this->free_result();

            throw $error;
        } finally {
            foreach ($temporary as $stream) {
                fclose($stream);
            }
        }
    } // native_execute

    /**
     * Captures SQL Server row counts, identity values and result metadata.
     *
     * Consumes leading row-count results and complete result-free batches. An appended
     * SCOPE_IDENTITY result is consumed internally. The first ordinary row result remains
     * available for public field access; its cursor is released when reading reaches EOF.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If native result processing fails.
     *
     * @author Oleg Schildt
     */
    private function accept_statement(): void
    {
        if (($this->options['save_records_explicit_identity'] ?? false) && !$this->captures_identity) {
            // Execute every part of an explicit-ID batch, including its cleanup and deferred error results.
            $affected = 0;

            do {
                $count = sqlsrv_rows_affected($this->statement);

                if ($count !== false && $count >= 0) {
                    $affected = $count;
                }

                $next = sqlsrv_next_result($this->statement);

                if ($next === false) {
                    throw $this->query_error($this->errors());
                }
            } while ($next === true);

            $this->executed = false;

            $this->set_result([], $affected);

            return;
        }

        // Treat DML counts separately from SELECT results and internal identity result sets.
        $affected = 0;

        if ($this->captures_identity) {
            // SCOPE_IDENTITY must execute in the same batch as INSERT.
            $identity_result = false;

            do {
                if (sqlsrv_num_fields($this->statement) === 0) {
                    $count = sqlsrv_rows_affected($this->statement);

                    if ($count !== false && $count >= 0) {
                        $affected = $count;
                    }
                }

                $fields = sqlsrv_field_metadata($this->statement);

                if ($fields && $fields[0]['Name'] === 'smartfactory_insert_id') {
                    $identity_result = true;
                    $status = sqlsrv_fetch($this->statement);

                    if ($status === false) {
                        throw $this->query_error($this->errors());
                    }

                    if ($status === true) {
                        $id = sqlsrv_get_field($this->statement, 0);

                        if ($id !== null && filter_var($id, FILTER_VALIDATE_INT) !== false) {
                            $this->last_insert_id = (int) $id;
                        }
                    }

                    break;
                }

                $next = sqlsrv_next_result($this->statement);

                if ($next === false) {
                    throw $this->query_error($this->errors());
                }
            } while ($next === true);

            // Fetching the identity value alone leaves its result pending when MARS is disabled.
            if ($identity_result) {
                $this->finish_results();
            } else {
                $this->executed = false;
            }

            $this->set_result([], $affected);

            return;
        }

        while (true) {
            $field_count = sqlsrv_num_fields($this->statement);

            if ($field_count === false) {
                throw $this->query_error($this->errors());
            }

            if ($field_count > 0) {
                break;
            }

            $count = sqlsrv_rows_affected($this->statement);

            if ($count === false) {
                throw $this->query_error($this->errors());
            }

            if ($count >= 0) {
                $affected = $count;
            }

            $next = sqlsrv_next_result($this->statement);

            if ($next === false) {
                throw $this->query_error($this->errors());
            }

            if ($next === null) {
                $this->executed = false;

                $this->set_result([], $affected);

                return;
            }
        }

        $fields = sqlsrv_field_metadata($this->statement);

        if ($fields === false && sqlsrv_errors(SQLSRV_ERR_ERRORS)) {
            throw $this->query_error($this->errors());
        }

        $types = [
            -5 => 'bigint',
            -2 => 'binary',
            -7 => 'bit',
            1 => 'char',
            91 => 'date',
            93 => 'datetime',
            -155 => 'datetimeoffset',
            3 => 'decimal',
            6 => 'float',
            -4 => 'image',
            4 => 'int',
            -8 => 'nchar',
            -10 => 'ntext',
            2 => 'numeric',
            -9 => 'nvarchar',
            7 => 'real',
            5 => 'smallint',
            -1 => 'text',
            -154 => 'time',
            -6 => 'tinyint',
            -151 => 'udt',
            -11 => 'uniqueidentifier',
            -3 => 'varbinary',
            12 => 'varchar',
            -152 => 'xml'
        ];
        $metadata = [];

        foreach ($fields ?: [] as $field) {
            $type = $field['Type'];
            $binary = in_array($type, [-2, -3, -4], true);
            $numeric = in_array($type, [-5, -7, 3, 6, 4, 2, 7, 5, -6], true);
            $datetime = in_array($type, [91, 93, -155, -154], true);
            $metadata[] = [
                'name' => $field['Name'],
                'type' => $types[$type] ?? 'undefined',
                'size' => $field['Size'] ?? 0,
                'prec' => $field['Precision'] ?? null,
                'scale' => $field['Scale'] ?? null,
                'numeric' => (int) $numeric,
                'binary' => (int) $binary,
                'datetime' => (int) $datetime,
                'string' => (int) in_array($type, [1, -8, -10, -9, -1, 12, -152], true)
            ];
        }

        $this->set_result($metadata, $affected);
    } // accept_statement

    /**
     * Completes the remaining results after the public row result or internal identity is consumed.
     *
     * Additional row results are discarded because DBWorker exposes only the first public result.
     * Advancing through the batch executes trailing commands and reports deferred SQL errors.
     *
     * @return void
     * Releases pending protocol results without freeing a reusable prepared statement.
     *
     * @throws DBWorkerException
     * If advancing to a later result reports a native SQL error.
     *
     * @author Oleg Schildt
     */
    private function finish_results(): void
    {
        do {
            $next = sqlsrv_next_result($this->statement);

            if ($next === false) {
                throw $this->query_error($this->errors());
            }
        } while ($next === true);

        $this->executed = false;
    } // finish_results

    /**
     * Consumes procedure results that the public execute_procedure() convenience method discards.
     *
     * @return void
     * Completes trailing commands and releases pending results before the procedure statement is freed.
     *
     * @throws DBWorkerException
     * If advancing through the remaining results reports a deferred SQL error.
     *
     * @author Oleg Schildt
     */
    protected function native_finish_procedure(): void
    {
        if ($this->executed) {
            $this->finish_results();
        }
    } // native_finish_procedure

    /**
     * Fetches the next row through the native driver.
     *
     * @return list<mixed>|null
     * Returns a numerically indexed row, or NULL at the end of the result set.
     *
     * @throws DBWorkerException
     * If fetching or spooling a field fails, or a trailing batch result reports a deferred SQL error.
     *
     * @see DBWorker::fetch_row()
     *
     * @author Oleg Schildt
     */
    protected function native_fetch(): ?array
    {
        if (!$this->executed) {
            return null;
        }

        // Preserve numeric field positions and distinguish end-of-result from a native error.
        $status = sqlsrv_fetch($this->statement);

        if ($status === false) {
            $error = $this->query_error($this->errors());

            $this->free_result();

            throw $error;
        }

        if ($status === null) {
            try {
                $this->finish_results();
            } catch (\Throwable $error) {
                $this->free_result();

                throw $error;
            }

            return null;
        }

        $row = [];

        try {
            foreach ($this->metadata as $index => $field) {
                $large = ($field['binary'] || $field['string']) && ($field['size'] <= 0 || $field['size'] > 8192);

                if ($large) {
                    $unicode = in_array($field['type'], ['nvarchar', 'nchar', 'ntext'], true);
                    $value = sqlsrv_get_field(
                        $this->statement,
                        $index,
                        SQLSRV_PHPTYPE_STREAM($field['binary'] || $unicode ? SQLSRV_ENC_BINARY : 'UTF-8')
                    );

                    if ($value === false) {
                        throw $this->query_error($this->errors());
                    }

                    if ($value !== null) {
                        $spool = tmpfile();

                        if ($spool === false) {
                            fclose($value);

                            throw $this->stream_error('Cannot spool a SQL Server result field.');
                        }

                        // Own the spool before copying so the failure path can always close it.
                        $row[$index] = $spool;

                        try {
                            // The sqlsrv UTF-8 stream converter can split a UTF-16 surrogate
                            // pair at its internal buffer boundary. Read Unicode fields as
                            // raw UTF-16LE and convert complete code points ourselves.
                            $carry = '';

                            $this->write_chunks(
                                $value,
                                function (string $chunk) use ($spool, $unicode, &$carry): void {
                                    if ($unicode) {
                                        $chunk = $carry . $chunk;
                                        $even = strlen($chunk) - strlen($chunk) % 2;
                                        $carry = substr($chunk, $even);
                                        $chunk = substr($chunk, 0, $even);

                                        if ($chunk !== '') {
                                            $last = unpack('v', substr($chunk, -2))[1];

                                            if ($last >= 0xD800 && $last <= 0xDBFF) {
                                                $carry = substr($chunk, -2) . $carry;
                                                $chunk = substr($chunk, 0, -2);
                                            }
                                        }

                                        if (!mb_check_encoding($chunk, 'UTF-16LE')) {
                                            throw $this->stream_error('Invalid UTF-16 result data.');
                                        }

                                        $chunk = mb_convert_encoding($chunk, 'UTF-8', 'UTF-16LE');
                                    }

                                    $this->write_all($spool, $chunk);
                                }
                            );

                            if ($carry !== '') {
                                throw $this->stream_error('Incomplete UTF-16 result data.');
                            }
                        } finally {
                            fclose($value);
                        }

                        rewind($spool);

                        continue;
                    }
                } else {
                    $value = sqlsrv_get_field($this->statement, $index);

                    if ($value === false && sqlsrv_errors(SQLSRV_ERR_ERRORS)) {
                        throw $this->query_error($this->errors());
                    }
                }

                $row[$index] = $value;
            }

            return $row;
        } catch (\Throwable $error) {
            // Release any already spooled fields if a later field cannot be read.
            foreach ($row as $value) {
                if (is_resource($value)) {
                    fclose($value);
                }
            }

            $this->free_result();

            throw $error;
        }
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
        if (is_resource($this->statement) && $this->executed) {
            sqlsrv_cancel($this->statement);
        }

        $this->executed = false;
        $this->result = null;
    } // native_free_result

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
            sqlsrv_free_stmt($this->statement);
        }

        $this->statement = null;

        $this->reset_driver_state();
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
     * The validated isolation level for begin; ignored for commit and rollback.
     * The previous session level is restored after the transaction ends.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If reading or applying session isolation, starting, committing or rolling back fails.
     *
     * @author Oleg Schildt
     */
    protected function native_transaction(string $action, string $isolation_level = self::ISOLATION_READ_COMMITTED): void
    {
        // Perform the native operation; the base worker updates the shared flag after success.
        if ($action === 'begin') {
            $previous = $this->session_transaction_isolation();

            $this->session_transaction_isolation($isolation_level);

            if (!sqlsrv_begin_transaction($this->connection_state->handle)) {
                $error = $this->query_error($this->errors());

                $this->session_transaction_isolation($previous);

                throw $error;
            }

            $this->connection_state->attributes['previous_transaction_isolation'] = $previous;

            return;
        }

        $success = match ($action) {
            'commit' => sqlsrv_commit($this->connection_state->handle),
            'rollback' => sqlsrv_rollback($this->connection_state->handle)
        };

        if (!$success) {
            throw $this->query_error($this->errors());
        }
    } // native_transaction

    /**
     * Reads or sets SQL Server session isolation without replacing the worker's public statement.
     *
     * @param string|null $isolation_level
     * A validated native isolation name to apply, or NULL to read the current level.
     *
     * @return string
     * Returns the applied or current native isolation level.
     *
     * @throws DBWorkerException
     * If SQL Server cannot execute the session command or return its isolation setting.
     *
     * @author Oleg Schildt
     */
    private function session_transaction_isolation(?string $isolation_level = null): string
    {
        // Execute SET directly: a setting made inside a prepared RPC can end with that RPC's scope.
        $sql = $isolation_level === null
            ? 'SELECT transaction_isolation_level FROM sys.dm_exec_sessions WHERE session_id = @@SPID'
            : 'SET TRANSACTION ISOLATION LEVEL ' . $isolation_level;
        $statement = @sqlsrv_query($this->connection_state->handle, $sql);

        if ($statement === false) {
            throw $this->query_error($this->errors());
        }

        try {
            if ($isolation_level === null) {
                $row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_NUMERIC);

                if (!is_array($row)) {
                    throw $this->query_error('SQL Server did not return the session isolation level.');
                }

                // Restore legacy session defaults as well, even though start_transaction() accepts only three levels.
                $levels = [1 => 'READ UNCOMMITTED', 2 => self::ISOLATION_READ_COMMITTED,
                    3 => self::ISOLATION_REPEATABLE_READ, 4 => self::ISOLATION_SERIALIZABLE, 5 => 'SNAPSHOT'];
                $isolation_level = $levels[(int) $row[0]] ?? self::ISOLATION_READ_COMMITTED;
            }

            return $isolation_level;
        } finally {
            sqlsrv_free_stmt($statement);
        }
    } // session_transaction_isolation

    /**
     * Restores the SQL Server session default after a successful commit or rollback.
     *
     * @return void
     * Restores the level recorded when the shared transaction began, including completion through a clone.
     *
     * @throws DBWorkerException
     * If the session setting cannot be restored. The completed transaction remains marked as ended.
     *
     * @author Oleg Schildt
     */
    protected function native_restore_transaction_isolation(): void
    {
        $previous = $this->connection_state->attributes['previous_transaction_isolation'] ?? null;

        if ($previous === null) {
            return;
        }

        $this->session_transaction_isolation($previous);

        unset($this->connection_state->attributes['previous_transaction_isolation']);
    } // native_restore_transaction_isolation

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
        // Escape only SQL string-literal quotes; identifiers use a separate quoting path.
        return str_replace("'", "''", $str);
    } // escape

    /**
     * Escapes and quotes a string, or prepares an SQL NULL literal.
     *
     * @param string|null $str
     * The value to quote. NULL and the empty string are represented as SQL NULL.
     *
     * @return string
     * Returns a quoted SQL string literal, or the unquoted literal null.
     *
     * @see DBWorker::escape()
     * @see DBWorker::number_or_null()
     *
     * @author Oleg Schildt
     */
    public function quotes_or_null(?string $str): string
    {
        // Preserve the helper's convention that NULL and an empty string both mean SQL NULL.
        return $str === null || $str === '' ? 'null' : "N'" . $this->escape($str) . "'";
    } // quotes_or_null

    /**
     * Formats a date as a native bind value.
     *
     * @param int|\DateTimeInterface|null $date
     * A Unix timestamp, a date/time object, or NULL. Timestamp zero is a valid date.
     * Objects retain their timezone; integer timestamps use the configured PHP timezone.
     *
     * @return string|null
     * Returns an unquoted driver-compatible string, or NULL.
     *
     * @see DBWorker::format_date()
     *
     * @author Oleg Schildt
     */
    public function format_date_bind(int|\DateTimeInterface|null $date): ?string
    {
        // Use an unambiguous SQL Server date format independent of the session language.
        return $date === null ? null : ($date instanceof \DateTimeInterface
            ? $date->format('Ymd') : date('Ymd', $date));
    } // format_date_bind

    /**
     * Formats a date/time as a native bind value.
     *
     * @param int|\DateTimeInterface|null $datetime
     * A Unix timestamp, a date/time object, or NULL. Timestamp zero is a valid date.
     * Objects retain their timezone; integer timestamps use the configured PHP timezone.
     *
     * @return string|null
     * Returns an unquoted driver-compatible string, or NULL.
     *
     * @see DBWorker::format_datetime()
     *
     * @author Oleg Schildt
     */
    public function format_datetime_bind(int|\DateTimeInterface|null $datetime): ?string
    {
        // Use ISO 8601 with a literal T so SQL Server does not reinterpret month and day positions.
        return $datetime === null ? null : ($datetime instanceof \DateTimeInterface
            ? $datetime->format('Y-m-d\TH:i:s') : date('Y-m-d\TH:i:s', $datetime));
    } // format_datetime_bind
    /**
     * Resolves SQL Server table identity, column types and collation-aware comparison fingerprints.
     *
     * @param string $table
     * The unquoted table, schema.table or database.schema.table name.
     *
     * @return array
     * Returns canonical table names and native key metadata.
     *
     * @throws DBWorkerException
     * If the target cannot be resolved or its column metadata cannot be read.
     *
     * @author Oleg Schildt
     */
    protected function native_save_table(string $table): array
    {
        $parts = explode('.', $table);
        $name = array_pop($parts);
        $schema = array_pop($parts);
        $database = array_pop($parts) ?? $this->save_sql('SELECT DB_NAME()')['rows'][0][0];
        $database = $this->save_sql('SELECT DB_NAME(DB_ID(?))', [$database])['rows'][0][0];
        $quote = static fn (string $name): string => '[' . str_replace(']', ']]', $name) . ']';
        $prefix = $quote($database);
        $lookup = $prefix . '.' . ($schema === null ? '' : $quote($schema)) . '.' . $quote($name);
        $result = $this->save_sql('SELECT s.name, t.name FROM ' . $prefix . '.sys.tables t JOIN ' . $prefix
            . '.sys.schemas s ON s.schema_id = t.schema_id WHERE t.object_id = OBJECT_ID(?)', [$lookup]);

        if (count($result['rows']) !== 1) {
            throw $this->query_error('The save_records table was not found.');
        }

        [$schema, $name] = $result['rows'][0];
        $qualified = $prefix . '.' . $quote($schema) . '.' . $quote($name);
        $rows = $this->save_sql('SELECT c.name, t.name, c.max_length, c.precision, c.scale, c.collation_name, c.is_identity '
            . 'FROM ' . $prefix . '.sys.columns c JOIN ' . $prefix . '.sys.types t ON t.user_type_id = c.system_type_id '
            . 'WHERE c.object_id = OBJECT_ID(?)', [$qualified]);
        $context = ['table' => $qualified, 'lock_table' => $prefix . '.[dbo].[dbworker_locks]',
            'resource' => [$database, $schema, $name], 'columns' => []];

        foreach ($rows['rows'] as [$field, $type, $length, $precision, $scale, $collation, $identity]) {
            $native = match ($type) {
                'varchar', 'char', 'binary', 'varbinary' => $type . '(' . ($length < 0 ? 'max' : $length) . ')',
                'nvarchar', 'nchar' => $type . '(' . ($length < 0 ? 'max' : (int) $length / 2) . ')',
                'decimal', 'numeric' => $type . '(' . $precision . ',' . $scale . ')',
                'datetime2', 'datetimeoffset', 'time' => $type . '(' . $scale . ')',
                default => $type
            };
            $cast = 'CAST(? AS ' . $native . ')' . ($collation === null ? '' : ' COLLATE ' . $collation);
            $supported = in_array($type, ['varchar', 'char', 'nvarchar', 'nchar', 'binary', 'varbinary', 'decimal', 'numeric',
                'bigint', 'int', 'smallint', 'tinyint', 'bit', 'float', 'real', 'money', 'smallmoney', 'date', 'datetime',
                'smalldatetime', 'datetime2', 'datetimeoffset', 'time', 'uniqueidentifier'], true);
            // Preserve full numeric entropy; use CHECKSUM only where SQL collation/equality requires it.
            $fingerprint = 'CHECKSUM(' . $cast . ')';

            if (in_array($type, ['bigint', 'int', 'smallint', 'tinyint', 'bit', 'decimal', 'numeric'], true)) {
                $fingerprint = 'CONVERT(varchar(100), ' . $cast . ')';
            } elseif ($collation !== null && preg_match('/_BIN2?\z/i', $collation)) {
                $fingerprint = 'CONVERT(varchar(max), CONVERT(varbinary(max), RTRIM(' . $cast . ')), 2)';
            }

            $context['columns'][strtolower($field)] = ['name' => $field, 'cast' => $cast,
                'fingerprint' => $supported ? $fingerprint : null, 'identity' => (bool) $identity];
        }

        return $context;
    } // native_save_table

    /**
     * Hashes typed SQL Server key expressions according to their native equality semantics.
     *
     * @param array $context
     * The resolved table, column metadata and service-table location.
     *
     * @param array<string, scalar|null> $keys
     * The supplied key values, ordered by canonical column name.
     *
     * @return array
     * Returns equality-compatible fingerprints; hash collisions share a lock but never a row predicate.
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
                throw new DBWorkerException('Unsupported SQL Server record-key type: ' . $field, self::ERR_WRONG_DATA_FORMAT);
            }

            $expressions[] = $column['fingerprint'];
        }

        $result = $this->save_sql('SELECT ' . implode(', ', $expressions), array_values($keys));

        return array_map(static fn (mixed $value): ?string => $value === null ? null : (string) $value, $result['rows'][0]);
    } // native_save_key

    /**
     * Creates or exclusively locks a SQL Server service row under serializable key-range protection.
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
        // Insert first: range-locking an absent hash could unnecessarily block unrelated resource keys.
        // XACT_ABORT OFF is scoped to this prepared batch so an expected duplicate remains recoverable.
        $sql = 'SET XACT_ABORT OFF; BEGIN TRY INSERT INTO ' . $context['lock_table']
            . ' (resource_key, resource_name) VALUES (?, ?); END TRY BEGIN CATCH '
            . 'IF ERROR_NUMBER() NOT IN (2601, 2627) THROW; END CATCH; '
            . 'SELECT resource_key FROM ' . $context['lock_table'] . ' WITH (UPDLOCK, ROWLOCK) WHERE resource_key = ?;';

        $this->save_sql($sql, [$key, $name, $key], false);
    } // native_save_lock

    /**
     * Rejects an aborted, externally ended or snapshot-isolated managed SQL Server transaction.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If SQL Server reports an uncommittable or unexpectedly absent transaction, or SNAPSHOT isolation.
     *
     * @author Oleg Schildt
     */
    protected function native_save_check(): void
    {
        parent::native_save_check();

        $status = $this->save_sql('SELECT XACT_STATE(), transaction_isolation_level '
            . 'FROM sys.dm_exec_sessions WHERE session_id = @@SPID')['rows'][0];
        $state = (int) $status[0];

        if ($this->connection_state->transaction && (int) $status[1] === 5) {
            throw new DBWorkerException('save_records() does not support SQL Server SNAPSHOT isolation.', self::ERR_WRONG_DATA_FORMAT);
        }

        if ($state < 0 || ($this->connection_state->transaction && $state === 0)) {
            if ($state === 0) {
                $this->connection_state->transaction = false;
            }

            throw $this->query_error('The SQL Server transaction cannot be used for save_records().');
        }
    } // native_save_check

    /**
     * Temporarily enables explicit SQL Server identity insertion and restores the session setting.
     *
     * @param array $context
     * The resolved table, column metadata and service-table location.
     *
     * @param array $values
     * The prepared field values.
     *
     * @param array $expressions
     * The positional SQL expressions.
     *
     * @param string $identity_field
     * The identity field, or an empty string.
     *
     * @return int|null
     * Returns the inserted identity, or NULL when none is configured.
     *
     * @throws DBWorkerException
     * If identity handling or inserting the record fails.
     *
     * @author Oleg Schildt
     */
    protected function native_save_insert(array $context, array $values, array $expressions, string $identity_field): ?int
    {
        $explicit = $identity_field !== '' && isset($values[$identity_field]);

        if (!$explicit) {
            return parent::native_save_insert($context, $values, $expressions, $identity_field);
        }

        // Keep the setting and INSERT in the same prepared batch; RPC-scoped SET alone is insufficient.
        $insert = 'INSERT INTO ' . $context['table'] . ' (' . implode(', ', array_keys($values))
            . ') VALUES (' . implode(', ', $expressions) . ');';
        $off = 'SET IDENTITY_INSERT ' . $context['table'] . ' OFF;';
        $sql = 'SET IDENTITY_INSERT ' . $context['table'] . ' ON; BEGIN TRY ' . $insert . ' ' . $off
            . ' END TRY BEGIN CATCH ' . $off . ' THROW; END CATCH;';

        $result = $this->save_sql($sql, array_values($values), false);
        $this->affected_rows = $result['affected'];

        return $values[$identity_field];
    } // native_save_insert


} // MSSQL_DBWorker
