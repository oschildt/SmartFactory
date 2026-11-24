<?php

/**
 * This file contains the implementation of the class MySQL_DBWorker.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\DatabaseWorkers;

/**
 * Database worker for MySQL using the mysqli extension.
 *
 * Implements native query execution, prepared binding and chunked LOB uploads.
 * Results are read sequentially without buffering the complete result set. An unread result
 * must be exhausted or freed before another worker uses the shared connection.
 * The connection uses utf8mb4. SQL literal escaping follows the current SQL mode.
 * mysqli materializes a complete BLOB field when fetching a row; copying that field
 * to a stream does not remove the native row-memory requirement.
 *
 * @see DBWorker
 *
 * @author Oleg Schildt
 */
class MySQL_DBWorker extends DBWorker
{
    /**
     * Stores this worker's native mysqli prepared statement.
     *
     * A failed preparation briefly assigns false before native_prepare() resets it to null.
     * The native mixed declaration preserves compatibility with the base property;
     * the PHPDoc type distinguishes mysqli statements from other drivers' handles.
     *
     * @var \mysqli_stmt|false|null
     *
     * @author Oleg Schildt
     */
    protected mixed $statement = null;

    /**
     * Keeps stable variable references for mysqli_stmt::bind_result() until the next execution.
     *
     * @var list<mixed>
     *
     * @author Oleg Schildt
     */
    private array $bound_row = [];

    /**
     * Returns the SQL dialect used by the placeholder compiler.
     *
     * @return string
     * Returns "mysql".
     *
     * @see PreparedQuery::compile()
     *
     * @author Oleg Schildt
     */
    protected function dialect(): string
    {
        // Keep marker translation independent of the human-readable database name.
        return 'mysql';
    } // dialect

    /**
     * Returns the name of the required PHP extension - "mysqli".
     *
     * @return string
     * Returns "mysqli".
     *
     * @see DBWorker::is_extension_installed()
     *
     * @author Oleg Schildt
     */
    public function get_extension_name(): string
    {
        // Expose the extension identifier used by extension_loaded() and the factory.
        return 'mysqli';
    } // get_extension_name

    /**
     * Returns the name of the supported database - "MySQL Server".
     *
     * @return string
     * Returns "MySQL Server".
     *
     * @author Oleg Schildt
     */
    public function get_rdbms_name(): string
    {
        // Expose the database name for diagnostics and application messages.
        return 'MySQL Server';
    } // get_rdbms_name

    /**
     * Creates a clone of the DBWorker that uses the same open connection.
     *
     * The clone shares session and transaction state. Exhaust or free the active result
     * before issuing another query on that connection. Closing one worker does not close
     * the shared connection while another worker owns it.
     *
     * @return MySQL_DBWorker
     * Returns a worker sharing the connection and transaction state, with independent statements and results.
     *
     * @author Oleg Schildt
     */
    public function create_clone(): MySQL_DBWorker
    {
        // Use the base clone hook to separate per-worker handles while retaining shared connection ownership.
        return clone $this;
    } // create_clone

    /**
     * Returns the state of this worker's native connection.
     *
     * @return bool
     * Returns true if a mysqli connection object is attached; otherwise returns false.
     *
     * @see DBWorker::check_connection()
     * @see MySQL_DBWorker::connect()
     * @see DBWorker::close_connection()
     *
     * @author Oleg Schildt
     */
    public function is_connected(): bool
    {
        // Inspect the attached native handle without opening another connection.
        return $this->connection_state?->handle instanceof \mysqli;
    } // is_connected

    /**
     * Resets driver-specific state after cloning or statement cleanup.
     *
     * Clears bound-row references without touching the native handles owned by the original worker.
     *
     * @return void
     * Does not return a value.
     *
     * @author Oleg Schildt
     */
    protected function reset_driver_state(): void
    {
        // Reset only worker-owned native state; shared connection attributes remain intact.
        $this->bound_row = [];
    } // reset_driver_state

    /**
     * Establishes the database connection using the initialized settings.
     *
     * Uses utf8mb4 and caches the current NO_BACKSLASH_ESCAPES mode.
     * An optional isolation_level or read_only setting is applied to the session.
     * An already connected worker keeps its existing connection.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If connection settings are incomplete, the isolation level is invalid, or connection or session
     * initialization fails.
     *
     * @see DBWorker::init()
     * @see MySQL_DBWorker::is_connected()
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

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $connection = mysqli_init();

        try {
            $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, (int) ($this->options['connect_timeout'] ?? 20));

            $connection->options(MYSQLI_OPT_READ_TIMEOUT, (int) ($this->options['read_timeout'] ?? 20));

            $connection->real_connect(
                $this->db_server,
                $this->db_user,
                $this->db_password,
                $this->db_name === '' ? null : $this->db_name,
                $this->db_port === '' ? null : (int) $this->db_port
            );

            // Configure the native connection encoding before any escaping or application query.
            $connection->set_charset('utf8mb4');

            $this->connection_state = new DBConnectionState($connection, static fn (\mysqli $db) => $db->close());

            $this->refresh_sql_mode();

            if (isset($this->options['isolation_level'])) {
                $level = strtoupper((string) $this->options['isolation_level']);

                if (
                    !in_array($level, ['READ UNCOMMITTED', 'READ COMMITTED', 'REPEATABLE READ', 'SERIALIZABLE'], true)
                ) {
                    throw new DBWorkerException('Invalid transaction isolation level.', self::ERR_WRONG_DATA_FORMAT);
                }

                $connection->query('SET SESSION TRANSACTION ISOLATION LEVEL ' . $level);
            }

            if ($this->read_only) {
                $connection->query('SET SESSION TRANSACTION READ ONLY');
            }
        } catch (\Throwable $error) {
            $this->connection_state = null;

            try {
                $connection->close();
            } catch (\Throwable) {
            }

            if ($error instanceof DBWorkerException) {
                throw $error;
            }

            $code = match ($error->getCode()) {
                2002, 2003 => self::ERR_HOST_UNREACHABLE,
                1045 => self::ERR_WRONG_USER_CREDENTIALS,
                1049 => self::ERR_DATABASE_NOT_FOUND,
                default => self::ERR_CONNECTION_FAILED
            };

            throw new DBWorkerException($error->getMessage(), $code);
        }
    } // connect

    /**
     * Selects another MySQL database on the current connection.
     *
     * The configured database name is updated only after native selection succeeds.
     *
     * @param string $db_name
     * The database name passed to mysqli::select_db().
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If no connection is open or mysqli cannot select the requested database.
     *
     * @author Oleg Schildt
     */
    public function use_database(string $db_name): void
    {
        // Let mysqli select the database directly instead of constructing a quoted USE statement.
        $this->check_connection();

        try {
            $this->connection_state->handle->select_db($db_name);
        } catch (\mysqli_sql_exception $error) {
            throw new DBWorkerException($error->getMessage(), self::ERR_DATABASE_NOT_FOUND);
        }

        $this->db_name = $db_name;
    } // use_database

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
        $result = $this->connection_state->handle->query($sql, MYSQLI_USE_RESULT);

        if ($result === false) {
            throw $this->query_error($this->connection_state->handle->error);
        }

        $this->result = $result instanceof \mysqli_result ? $result : null;

        $this->set_result(
            $this->result ? $this->describe($this->result) : [],
            $this->connection_state->handle->affected_rows
        );

        if (!($this->options['save_records_explicit_identity'] ?? false)) {
            $this->save_insert_id($this->connection_state->handle->insert_id);
        }

        if (preg_match('/\ASET\b[\s\S]*\bsql_mode\b/i', $this->sql_body($sql))) {
            $this->refresh_sql_mode();
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
        $this->statement = $this->connection_state->handle->prepare($sql);

        if ($this->statement === false) {
            $this->statement = null;

            throw $this->query_error($this->connection_state->handle->error);
        }
    } // native_prepare

    /**
     * Binds validated values and executes the native prepared statement.
     *
     * Scalar PHP types determine mysqli bind types. LOBs are sent in chunks through
     * send_long_data(); empty LOBs remain distinct from NULL. Result rows are read
     * without buffering the result set, and bound-row references remain stable.
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
        $types = '';
        $bound = [];
        $lobs = [];

        foreach ($values as $index => $value) {
            if ($value instanceof BlobValue || $value instanceof ClobValue) {
                $types .= 'b';
                $bound[$index] = null;
                $lobs[$index] = $value;
            } elseif (is_int($value) || is_bool($value)) {
                $types .= 'i';
                $bound[$index] = (int) $value;
            } elseif (is_float($value)) {
                if (!is_finite($value)) {
                    throw $this->query_error('Non-finite numeric parameter.');
                }

                $types .= 'd';
                $bound[$index] = $value;
            } else {
                $types .= 's';
                $bound[$index] = $value;
            }
        }

        if ($values && !$this->statement->bind_param($types, ...$bound)) {
            throw $this->query_error($this->statement->error);
        }

        // Send each LOB to its own native position, preserving repeated named bindings.
        foreach ($lobs as $index => $lob) {
            $sent = false;

            $this->write_chunks(
                $lob->value,
                function (string $chunk) use ($index, &$sent): void {
                    if (!$this->statement->send_long_data($index, $chunk)) {
                        throw $this->query_error($this->statement->error);
                    }

                    $sent = true;
                },
                $lob instanceof ClobValue
            );

            // An empty LOB is an empty value, not SQL NULL.
            if (!$sent) {
                $this->statement->send_long_data($index, '');
            }
        }

        if (!$this->statement->execute()) {
            throw $this->query_error($this->statement->error);
        }

        $metadata = [];

        // Bind result columns without buffering rows; successful DML has no result metadata.
        if ($this->statement->field_count > 0) {
            $result = $this->statement->result_metadata();
            $metadata = $this->describe($result);

            $result->free();

            $this->bound_row = array_fill(0, count($metadata), null);

            // bind_result() needs stable references, not a temporary unpacked copy of row values.
            $references = [];

            foreach ($this->bound_row as &$value) {
                $references[] = &$value;
            }

            unset($value);

            if (!$this->statement->bind_result(...$references)) {
                throw $this->query_error($this->statement->error);
            }
        }

        $this->set_result($metadata, $this->statement->affected_rows);

        if (!($this->options['save_records_explicit_identity'] ?? false)) {
            $this->save_insert_id($this->statement->insert_id);
        }

        if (preg_match('/\ASET\b[\s\S]*\bsql_mode\b/i', $this->sql_body($this->compiled_query->original))) {
            $this->refresh_sql_mode();
        }
    } // native_execute

    /**
     * Refreshes the shared MySQL backslash-escaping mode from the current session.
     *
     * Placeholder scanning and literal escaping must agree with NO_BACKSLASH_ESCAPES.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If the session setting cannot be queried.
     *
     * @author Oleg Schildt
     */
    private function refresh_sql_mode(): void
    {
        // Read the session setting so scanning and escaping use the same backslash rules as MySQL.
        $result = $this->connection_state->handle->query('select @@session.sql_mode', MYSQLI_USE_RESULT);

        if ($result === false) {
            throw $this->query_error($this->connection_state->handle->error);
        }

        $this->connection_state->attributes['no_backslash_escapes'] = in_array(
            'NO_BACKSLASH_ESCAPES',
            explode(',', $result->fetch_row()[0]),
            true
        );

        $result->free();
    } // refresh_sql_mode

    /**
     * Stores a native MySQL identifier after checking the PHP integer range.
     *
     * @param int|string $id
     * The identifier returned by mysqli. Zero means that no generated identifier is available.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If the identifier cannot be represented as a PHP integer.
     *
     * @author Oleg Schildt
     */
    private function save_insert_id(int|string $id): void
    {
        // Ignore the native no-identity sentinel and reject identifiers outside the integer API.
        if ((string) $id === '0') {
            return;
        }

        if (filter_var($id, FILTER_VALIDATE_INT) === false) {
            throw new DBWorkerException(
                'Generated identifier exceeds the PHP integer range.',
                self::ERR_WRONG_DATA_FORMAT
            );
        }

        $this->last_insert_id = (int) $id;
    } // save_insert_id

    /**
     * Fetches the next row through the native driver.
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
        // Preserve numeric field positions and distinguish end-of-result from a native error.
        if ($this->statement !== null) {
            $status = $this->statement->fetch();

            if ($status === false) {
                throw $this->query_error($this->statement->error);
            }

            return $status === null ? null : array_map(static fn (mixed $value): mixed => $value, $this->bound_row);
        }

        $row = $this->result->fetch_row();

        return $row === null ? null : $row;
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
        if ($this->result instanceof \mysqli_result) {
            $this->result->free();
        }

        $this->result = null;

        if ($this->statement instanceof \mysqli_stmt) {
            $this->statement->free_result();
        }

        $this->bound_row = [];

        // CALL can leave additional protocol results pending.
        if ($this->connection_state !== null && $this->statement === null) {
            $db = $this->connection_state->handle;

            // Drain additional CALL results before the shared connection accepts another query.
            while ($db->more_results()) {
                $db->next_result();

                $extra = $db->use_result();

                if ($extra instanceof \mysqli_result) {
                    $extra->free();
                }
            }
        }

        if ($this->statement instanceof \mysqli_stmt) {
            while ($this->statement->more_results()) {
                $this->statement->next_result();

                $this->statement->free_result();
            }
        }
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
        if ($this->statement instanceof \mysqli_stmt) {
            $this->statement->close();
        }

        $this->statement = null;
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
     * The validated isolation level applied only to the transaction being started.
     * Ignored for commit and rollback.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If the native transaction operation fails.
     *
     * @author Oleg Schildt
     */
    protected function native_transaction(string $action, string $isolation_level = self::ISOLATION_READ_COMMITTED): void
    {
        // Perform the native operation; the base worker updates the shared flag after success.
        $db = $this->connection_state->handle;

        try {
            if ($action === 'begin') {
                // Without SESSION this applies to the next transaction and preserves the session default.
                if ($db->query('SET TRANSACTION ISOLATION LEVEL ' . $isolation_level) === false) {
                    throw $this->query_error($db->error);
                }
            }

            $success = match ($action) {
                'begin' => $db->begin_transaction($this->read_only ? MYSQLI_TRANS_START_READ_ONLY : 0),
                'commit' => $db->commit(),
                'rollback' => $db->rollback()
            };

            if (!$success) {
                throw $this->query_error($db->error);
            }
        } catch (\mysqli_sql_exception $error) {
            throw $this->query_error($error->getMessage());
        }
    } // native_transaction

    /**
     * Escapes a string for inclusion in an SQL string literal without adding quotation marks.
     *
     * The result is intended for string literals, not SQL identifiers.
     * Prefer bound parameters when supplying application values to a query.
     *
     * @param string $str
     * The string to escape.
     *
     * @return string
     * Returns the escaped string.
     *
     * @throws DBWorkerException
     * If no database connection is open.
     *
     * @see DBWorker::quotes_or_null()
     * @see DBWorker::prepare_query()
     *
     * @author Oleg Schildt
     */
    public function escape(string $str): string
    {
        // Use native escaping except when the session explicitly disables backslash escapes.
        $this->check_connection();

        if ($this->connection_state->attributes['no_backslash_escapes'] ?? false) {
            return str_replace("'", "''", $str);
        }

        return $this->connection_state->handle->real_escape_string($str);
    } // escape

    /**
     * Prepares a value for inclusion in an SQL query according to its type.
     * It performs escaping, formatting and quoting where necessary.
     *
     * @param mixed $value
     * The value to prepare. NULL and the empty string are represented as SQL NULL.
     *
     * @param int $type
     * The DBWorker constant describing the value type.
     *
     * @return string
     * Returns the prepared SQL expression.
     *
     * @throws DBWorkerException
     * If the value format is invalid or the requested type is unsupported.
     *
     * @see DBWorker::quotes_or_null()
     * @see DBWorker::format_date()
     * @see DBWorker::format_datetime()
     *
     * @author Oleg Schildt
     */
    public function prepare_for_query(mixed $value, int $type): string
    {
        // Handle supported spatial expressions here and delegate ordinary value types to the base worker.
        if ($value !== null && $value !== '' && in_array($type, [self::DB_GEOMETRY, self::DB_GEOMETRY_4326], true)) {
            return "ST_GeomFromText('" . $this->escape((string) $value) . "'"
                . ($type === self::DB_GEOMETRY_4326 ? ", 4326, 'axis-order=lat-long')" : ')');
        }

        return parent::prepare_for_query($value, $type);
    } // prepare_for_query

    /**
     * Builds the common field metadata array from mysqli result metadata.
     *
     * @param \mysqli_result $result
     * The native result or prepared-statement metadata result.
     *
     * @return list<array<string, int|string>>
     * Returns ordered field descriptions with type names and text, binary, numeric and date/time flags.
     *
     * @author Oleg Schildt
     */
    private function describe(\mysqli_result $result): array
    {
        // Normalize native type codes while retaining decimal values as strings during row access.
        $types = [
            0 => 'decimal',
            1 => 'tinyint',
            2 => 'smallint',
            3 => 'integer',
            4 => 'float',
            5 => 'double',
            7 => 'timestamp',
            8 => 'bigint',
            9 => 'mediumint',
            10 => 'date',
            11 => 'time',
            12 => 'datetime',
            13 => 'year',
            14 => 'date',
            16 => 'bit',
            245 => 'json',
            246 => 'decimal',
            247 => 'enum',
            248 => 'set',
            249 => 'tinyblob',
            250 => 'mediumblob',
            251 => 'longblob',
            252 => 'blob',
            253 => 'varchar',
            254 => 'char',
            255 => 'geometry'
        ];
        $metadata = [];

        foreach ($result->fetch_fields() as $field) {
            $numeric = in_array($field->type, [0, 1, 2, 3, 4, 5, 8, 9, 13, 16, 246], true);
            $datetime = in_array($field->type, [7, 10, 11, 12, 14], true);
            $binary = $field->charsetnr === 63 && in_array($field->type, [249, 250, 251, 252, 253, 254, 255], true);
            $metadata[] = [
                'name' => $field->name,
                'type' => $types[$field->type] ?? 'undefined',
                'size' => $field->length,
                'numeric' => (int) $numeric,
                'datetime' => (int) $datetime,
                'binary' => (int) $binary,
                'string' => (int) (!$numeric && !$datetime && !$binary)
            ];
        }

        return $metadata;
    } // describe
    /**
     * Resolves a MySQL table and comparison expressions from information_schema.
     *
     * @param string $table
     * The unquoted table name, optionally prefixed by its database.
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

        if (count($parts) > 2) {
            throw new DBWorkerException('MySQL table names support database.table.', self::ERR_WRONG_DATA_FORMAT);
        }

        $name = array_pop($parts);
        $database = $parts[0] ?? $this->save_sql('SELECT DATABASE()')['rows'][0][0];
        $result = $this->save_sql('SELECT TABLE_SCHEMA, TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$database, $name]);

        if (count($result['rows']) !== 1) {
            throw $this->query_error('The save_records table was not found.');
        }

        [$database, $name] = $result['rows'][0];
        $quote = static fn (string $name): string => '`' . str_replace('`', '``', $name) . '`';
        $columns = $this->save_sql(
            'SELECT c.COLUMN_NAME, c.DATA_TYPE, c.NUMERIC_PRECISION, c.NUMERIC_SCALE, c.CHARACTER_SET_NAME, '
            . 'c.COLLATION_NAME, co.PAD_ATTRIBUTE, c.EXTRA, c.CHARACTER_MAXIMUM_LENGTH, c.DATETIME_PRECISION '
            . 'FROM information_schema.COLUMNS c LEFT JOIN information_schema.COLLATIONS co ON co.COLLATION_NAME = c.COLLATION_NAME '
            . 'WHERE c.TABLE_SCHEMA = ? AND c.TABLE_NAME = ?', [$database, $name]
        );
        $context = ['table' => $quote($database) . '.' . $quote($name), 'lock_table' => $quote($database) . '.`dbworker_locks`',
            'resource' => [$database, $name], 'columns' => []];

        foreach ($columns['rows'] as $row) {
            [$field, $type, $precision, $scale, $charset, $collation, $pad, $extra, $length, $fraction] = $row;
            $cast = match ($type) {
                'tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'numeric' => 'CAST(? AS DECIMAL(65,' . (int) $scale . '))',
                'float' => 'CAST(? AS FLOAT(24))',
                'double', 'real' => 'CAST(? AS DOUBLE)',
                'date' => 'CAST(? AS DATE)',
                'datetime', 'timestamp' => 'CAST(? AS DATETIME(' . (int) $fraction . '))',
                'time' => 'CAST(? AS TIME(' . (int) $fraction . '))',
                default => '?'
            };
            $fingerprint = $cast;

            if (in_array($type, ['enum', 'set'], true)) {
                $fingerprint = null;
            } elseif ($charset !== null) {
                // Native sort weights preserve case/accent equivalence, including PAD SPACE collations.
                $cast = 'CONVERT(? USING ' . $charset . ') COLLATE ' . $collation;
                $source = $pad === 'PAD SPACE' || $type === 'char' ? 'RTRIM(' . $cast . ')' : $cast;
                $fingerprint = 'HEX(WEIGHT_STRING(' . $source . '))';
            } elseif (in_array($type, ['binary', 'varbinary'], true)) {
                $cast = $type === 'binary' ? 'CAST(? AS BINARY(' . (int) $length . '))' : 'CAST(? AS BINARY)';
                $fingerprint = 'HEX(' . $cast . ')';
            } elseif (!in_array($type, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'numeric',
                'float', 'double', 'real', 'date', 'datetime', 'timestamp', 'time'], true)) {
                $fingerprint = null;
            }

            $context['columns'][strtolower($field)] = ['name' => $field, 'cast' => $cast, 'fingerprint' => $fingerprint,
                'identity' => str_contains($extra, 'auto_increment'), 'length' => $length];
        }

        return $context;
    } // native_save_table

    /**
     * Normalizes MySQL key values with their column types and native collation weights.
     *
     * @param array $context
     * The resolved table, column metadata and service-table location.
     *
     * @param array<string, scalar|null> $keys
     * The supplied key values, ordered by canonical column name.
     *
     * @return array
     * Returns the database-comparable key fingerprints.
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
                throw new DBWorkerException('Unsupported MySQL record-key type: ' . $field, self::ERR_WRONG_DATA_FORMAT);
            }

            $expressions[] = $column['fingerprint'];
        }

        $result = $this->save_sql('SELECT ' . implode(', ', $expressions), array_values($keys));

        return $result['rows'][0];
    } // native_save_key

    /**
     * Creates or exclusively locks an InnoDB service row without exposing duplicate-key errors.
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
        // Both INSERT and the duplicate-key no-op retain the exclusive record lock until transaction end.
        $this->save_sql('INSERT INTO ' . $context['lock_table'] . ' (resource_key, resource_name) VALUES (?, ?) '
            . 'ON DUPLICATE KEY UPDATE resource_key = resource_key', [$key, $name]);
    } // native_save_lock

    /**
     * Checks the key using an InnoDB current read after acquiring its service lock.
     *
     * @param array $context
     * The resolved table, column metadata and service-table location.
     *
     * @param string $where
     * The typed key predicate.
     *
     * @param array $arguments
     * The bound non-NULL key values.
     *
     * @return bool
     * Returns whether the key currently exists.
     *
     * @throws DBWorkerException
     * If preparing or executing the typed existence query fails.
     *
     * @author Oleg Schildt
     */
    protected function native_save_exists(array $context, string $where, array $arguments): bool
    {
        // A locking read uses current committed data even when the caller already established an RR snapshot.
        $result = $this->save_sql('SELECT 1 FROM ' . $context['table'] . ' WHERE ' . $where . ' FOR UPDATE', $arguments);

        return $result['rows'] !== [];
    } // native_save_exists


    /**
     * Preserves an explicitly supplied zero identity without changing the session's normal SQL mode.
     *
     * @param array $context
     * The resolved table and column metadata.
     *
     * @param array $values
     * The prepared field values, including an explicit identity when supplied.
     *
     * @param array $expressions
     * The positional SQL expression for each field.
     *
     * @param string $identity_field
     * The configured identity field, or an empty string.
     *
     * @return int|null
     * Returns the inserted identity, or NULL when none is configured.
     *
     * @throws DBWorkerException
     * If changing SQL mode, inserting the record or restoring the mode fails.
     *
     * @author Oleg Schildt
     */
    protected function native_save_insert(array $context, array $values, array $expressions, string $identity_field): ?int
    {
        if ($identity_field === '' || ($values[$identity_field] ?? null) !== 0) {
            return parent::native_save_insert($context, $values, $expressions, $identity_field);
        }

        $mode = $this->save_sql('SELECT @@SESSION.sql_mode')['rows'][0][0];

        if (in_array('NO_AUTO_VALUE_ON_ZERO', explode(',', $mode), true)) {
            return parent::native_save_insert($context, $values, $expressions, $identity_field);
        }

        // Zero is a known ID in this API, whereas MySQL normally interprets it as an allocation request.
        $this->save_sql('SET SESSION sql_mode = ?', [ltrim($mode . ',NO_AUTO_VALUE_ON_ZERO', ',')]);

        try {
            return parent::native_save_insert($context, $values, $expressions, $identity_field);
        } finally {
            $this->save_sql('SET SESSION sql_mode = ?', [$mode]);
        }
    } // native_save_insert

} // MySQL_DBWorker
