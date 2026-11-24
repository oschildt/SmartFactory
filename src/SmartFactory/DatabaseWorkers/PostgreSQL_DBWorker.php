<?php

/**
 * This file contains the implementation of the class PostgreSQL_DBWorker.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\DatabaseWorkers;

/**
 * Database worker for PostgreSQL using the pgsql extension.
 *
 * Uses uniquely named native prepared statements so workers sharing one connection
 * do not replace each other's statements. BlobValue binds bytea, while stream_long_data()
 * uses PostgreSQL large objects referenced by OID columns. Large-object reads and writes
 * are performed in an existing or internally owned transaction. SELECT results use forward-only
 * server cursors and fetch one row at a time. Outside an explicit transaction, a read transaction
 * remains open until all shared cursors reach EOF or are freed. Complete these reads before
 * starting an explicit transaction or modifying data on the shared connection.
 *
 * @see DBWorker
 *
 * @author Oleg Schildt
 */
class PostgreSQL_DBWorker extends DBWorker
{
    /**
     * Stores the SQL used to declare a cursor for a prepared SELECT.
     *
     * @var string|null
     *
     * @author Oleg Schildt
     */
    private ?string $cursor_sql = null;

    /**
     * Stores this worker's open forward-only server cursor name.
     *
     * @var string|null
     *
     * @author Oleg Schildt
     */
    private ?string $cursor_name = null;

    /**
     * Stores the unique server-side PostgreSQL prepared-statement name owned by this worker.
     *
     * @var string|null
     *
     * @author Oleg Schildt
     */
    private ?string $statement_name = null;

    /**
     * Stores the identity/serial column expected in an automatically extended PostgreSQL INSERT result.
     *
     * @var string|null
     *
     * @author Oleg Schildt
     */
    private ?string $identity_column = null;

    /**
     * Returns the SQL dialect used by the placeholder compiler.
     *
     * @return string
     * Returns "postgres".
     *
     * @see PreparedQuery::compile()
     *
     * @author Oleg Schildt
     */
    protected function dialect(): string
    {
        // Keep marker translation independent of the human-readable database name.
        return 'postgres';
    } // dialect

    /**
     * Returns the name of the required PHP extension - "pgsql".
     *
     * @return string
     * Returns "pgsql".
     *
     * @see DBWorker::is_extension_installed()
     *
     * @author Oleg Schildt
     */
    public function get_extension_name(): string
    {
        // Expose the extension identifier used by extension_loaded() and the factory.
        return 'pgsql';
    } // get_extension_name

    /**
     * Returns the name of the supported database - "PostgreSQL Server".
     *
     * @return string
     * Returns "PostgreSQL Server".
     *
     * @author Oleg Schildt
     */
    public function get_rdbms_name(): string
    {
        // Expose the database name for diagnostics and application messages.
        return 'PostgreSQL Server';
    } // get_rdbms_name

    /**
     * Creates a clone of the DBWorker that uses the same open connection.
     *
     * This is useful for additional queries while iterating over an active result set.
     * Closing one worker does not close the shared connection while another worker owns it.
     * Native driver restrictions on concurrent results still apply.
     *
     * @return PostgreSQL_DBWorker
     * Returns a worker sharing the connection and transaction state, with independent statements and results.
     *
     * @author Oleg Schildt
     */
    public function create_clone(): PostgreSQL_DBWorker
    {
        // Use the base clone hook to separate per-worker handles while retaining shared connection ownership.
        return clone $this;
    } // create_clone

    /**
     * Returns the state of this worker's native connection.
     *
     * @return bool
     * Returns true if a connection object is attached and its status is PGSQL_CONNECTION_OK;
     * otherwise returns false.
     *
     * @see DBWorker::check_connection()
     * @see PostgreSQL_DBWorker::connect()
     * @see DBWorker::close_connection()
     *
     * @author Oleg Schildt
     */
    public function is_connected(): bool
    {
        // Inspect the attached native handle without opening another connection.
        return $this->connection_state?->handle instanceof \PgSql\Connection
            && pg_connection_status($this->connection_state->handle) === PGSQL_CONNECTION_OK;
    } // is_connected

    /**
     * Resets driver-specific state after cloning or statement cleanup.
     *
     * Detaches the copied server-side statement, cursor names, SQL and identity hint.
     * The original worker remains responsible for its prepared statement.
     *
     * @return void
     * Does not return a value.
     *
     * @author Oleg Schildt
     */
    protected function reset_driver_state(): void
    {
        // Reset only worker-owned native state; shared connection attributes remain intact.
        $this->statement_name = $this->identity_column = null;
        $this->cursor_sql = $this->cursor_name = null;
    } // reset_driver_state

    /**
     * Establishes the database connection using the initialized settings.
     *
     * Opens a new native connection instead of silently reusing a matching connection string.
     * Configures UTF-8, ISO date formatting, the search path and supported session options.
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
     * @see PostgreSQL_DBWorker::is_connected()
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

        if ($this->db_server === '' || $this->db_user === '' || $this->db_name === '') {
            throw new DBWorkerException('Connection data is incomplete.', self::ERR_CONNECTION_DATA_INCOMPLETE);
        }

        $quote = static fn(string $value): string => "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
        $parts = [
            'host=' . $quote($this->db_server),
            'user=' . $quote($this->db_user),
            'password=' . $quote($this->db_password),
            'dbname=' . $quote($this->db_name),
            'connect_timeout=' . (int) ($this->options['connect_timeout'] ?? 20)
        ];

        if ($this->db_port !== '') {
            $parts[] = 'port=' . (int) $this->db_port;
        }

        $db = @pg_connect(implode(' ', $parts), PGSQL_CONNECT_FORCE_NEW);

        if ($db === false) {
            // Never include the connection string/password in diagnostics.
            throw new DBWorkerException('Could not connect to PostgreSQL.', self::ERR_CONNECTION_FAILED);
        }

        $this->connection_state = new DBConnectionState($db, static fn(\PgSql\Connection $db) => pg_close($db));

        try {
            if (pg_set_client_encoding($db, 'UTF8') !== 0) {
                throw $this->query_error(pg_last_error($db));
            }

            $this->control("SET datestyle = 'ISO, YMD'");

            $this->control('SET search_path TO ' . pg_escape_identifier($db, $this->get_schema()));

            $timeout = max(0, (int) ($this->options['query_timeout'] ?? 0));

            if ($timeout > 0) {
                $this->control('SET statement_timeout = ' . ($timeout * 1000));
            }

            if (isset($this->options['isolation_level'])) {
                $level = strtoupper((string) $this->options['isolation_level']);

                if (
                    !in_array($level, ['READ UNCOMMITTED', 'READ COMMITTED', 'REPEATABLE READ', 'SERIALIZABLE'], true)
                ) {
                    throw new DBWorkerException('Invalid transaction isolation level.', self::ERR_WRONG_DATA_FORMAT);
                }

                $this->control('SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL ' . $level);
            }

            if ($this->read_only) {
                $this->control('SET default_transaction_read_only = on');
            }
        } catch (\Throwable $error) {
            $this->connection_state = null;

            throw $error;
        }
    } // connect

    /**
     * Executes an internal SQL command without replacing the public result set.
     *
     * @param string $sql
     * The internal session, transaction or savepoint command.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If the internal command fails.
     *
     * @author Oleg Schildt
     */
    private function control(string $sql): void
    {
        // Use a temporary native handle so an internal command cannot replace the caller's active result.
        $result = @pg_query($this->connection_state->handle, $sql);

        if ($result === false) {
            throw $this->query_error(pg_last_error($this->connection_state->handle));
        }

        pg_free_result($result);
    } // control

    /**
     * Executes SQL and records metadata, using a server cursor for SELECT queries.
     *
     * SELECT rows are fetched lazily. The cursor owns a transaction when the caller has not
     * started one; writes require those internally managed read cursors to be closed first.
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
        $sql = $this->with_identity($sql);

        if (preg_match('/\A(?:SELECT|WITH)\b/i', $this->sql_body($sql))) {
            $this->open_cursor($sql, []);

            return;
        }

        $this->check_cursor_transaction();

        $result = @pg_query($this->connection_state->handle, $sql);

        if ($result === false) {
            throw $this->query_error(pg_last_error($this->connection_state->handle));
        }

        $this->accept_result($result);

        $this->flush_deallocations();
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
        $this->flush_deallocations();

        $sql = $this->with_identity($sql);

        // Unique names prevent cloned workers from replacing each other's prepared statements.
        $this->statement_name = 'sf_' . bin2hex(random_bytes(12));
        $result = @pg_prepare($this->connection_state->handle, $this->statement_name, $sql);

        if ($result === false) {
            $this->statement_name = null;

            throw $this->query_error(pg_last_error($this->connection_state->handle));
        }

        $this->statement = $result;
        $this->cursor_sql = preg_match('/\A(?:SELECT|WITH)\b/i', $this->sql_body($sql)) ? $sql : null;
    } // native_prepare

    /**
     * Binds validated values and executes the native prepared statement.
     *
     * Repeated application names share one native parameter index. Binary LOB wrappers
     * are encoded as bytea; text LOB wrappers are materialized as text and reject NUL bytes.
     * Use stream_long_data() for chunked writes into OID-backed large-object columns.
     * Prepared SELECT queries declare a new forward-only cursor for each execution.
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
        $bound = [];

        // Repeated application names share one PostgreSQL native parameter index.
        $seen = [];

        foreach ($values as $index => $value) {
            if ($this->compiled_query->named) {
                $name = $this->compiled_query->names[$index];

                if (isset($seen[$name])) {
                    continue;
                }

                $seen[$name] = true;
            }

            if ($value instanceof BlobValue || $value instanceof ClobValue) {
                $data = '';

                $this->write_chunks(
                    $value->value,
                    static function (string $chunk) use (&$data): void {
                        $data .= $chunk;
                    },
                    $value instanceof ClobValue
                );

                if ($value instanceof ClobValue && str_contains($data, "\0")) {
                    throw $this->query_error('PostgreSQL text values cannot contain NUL bytes.');
                }

                $bound[] = $value instanceof BlobValue
                    ? pg_escape_bytea($this->connection_state->handle, $data) : $data;
            } elseif (is_bool($value)) {
                $bound[] = (int) $value;
            } else {
                if (is_string($value) && str_contains($value, "\0")) {
                    throw $this->query_error('Binary strings containing NUL must be wrapped in BlobValue.');
                }

                $bound[] = $value;
            }
        }

        if ($this->cursor_sql !== null) {
            $this->open_cursor($this->cursor_sql, $bound);

            return;
        }

        $this->check_cursor_transaction();

        $result = @pg_execute($this->connection_state->handle, $this->statement_name, $bound);

        if ($result === false) {
            throw $this->query_error(pg_last_error($this->connection_state->handle));
        }

        $this->accept_result($result);
    } // native_execute

    /**
     * Declares a forward-only server cursor and reads its metadata without fetching rows.
     *
     * @param string $sql
     * The SELECT query with native positional bind markers, if any.
     *
     * @param list<scalar|null> $values
     * The normalized native parameter values.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If starting the read transaction, declaring the cursor or reading metadata fails.
     *
     * @author Oleg Schildt
     */
    private function open_cursor(string $sql, array $values): void
    {
        if (pg_transaction_status($this->connection_state->handle) === PGSQL_TRANSACTION_IDLE) {
            $this->control('BEGIN');

            $this->connection_state->attributes['cursor_transaction'] = true;
        }

        $this->cursor_name = 'sf_cursor_' . bin2hex(random_bytes(12));
        $this->connection_state->attributes['read_cursors'][$this->cursor_name] = true;

        try {
            $result = @pg_query_params(
                $this->connection_state->handle,
                'DECLARE ' . $this->cursor_name . ' NO SCROLL CURSOR FOR ' . rtrim($sql, " \t\r\n;"),
                $values
            );

            if ($result === false) {
                throw $this->query_error(pg_last_error($this->connection_state->handle));
            }

            pg_free_result($result);

            $result = @pg_query($this->connection_state->handle, 'FETCH FORWARD 0 FROM ' . $this->cursor_name);

            if ($result === false) {
                throw $this->query_error(pg_last_error($this->connection_state->handle));
            }

            $this->accept_result($result);
        } catch (\Throwable $error) {
            $this->native_free_result();

            throw $error;
        }
    } // open_cursor

    /**
     * Prevents writes and explicit transaction changes inside an internally owned cursor transaction.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If a cursor-owned read transaction is still active on the shared connection.
     *
     * @author Oleg Schildt
     */
    private function check_cursor_transaction(): void
    {
        if ($this->connection_state->attributes['cursor_transaction'] ?? false) {
            throw $this->query_error('Exhaust or free the active SELECT results before modifying data or changing transactions.');
        }
    } // check_cursor_transaction

    /**
     * Captures PostgreSQL result metadata, affected rows and a returned generated identifier.
     *
     * Metadata retains native numeric type names and distinguishes bytea from OID references.
     *
     * @param \PgSql\Result $result
     * The successfully executed pgsql result object.
     *
     * @return void
     * Does not return a value.
     *
     * @author Oleg Schildt
     */
    private function accept_result(\PgSql\Result $result): void
    {
        // Record metadata without advancing the application row cursor.
        $this->result = $result;
        $metadata = [];

        for ($i = 0; $i < pg_num_fields($result); $i++) {
            $type = pg_field_type($result, $i);
            $numeric = in_array($type, ['int2', 'int4', 'int8', 'float4', 'float8', 'numeric', 'money'], true);
            $datetime = in_array($type, ['date', 'time', 'timetz', 'timestamp', 'timestamptz', 'interval'], true);
            $metadata[] = [
                'name' => pg_field_name($result, $i),
                'type' => $type,
                'size' => pg_field_size($result, $i),
                'numeric' => (int) $numeric,
                'datetime' => (int) $datetime,
                'binary' => (int) ($type === 'bytea'),
                'string' => (int) in_array($type, ['text', 'varchar', 'bpchar', 'char', 'name', 'json', 'jsonb'], true)
            ];
        }

        $this->set_result($metadata, pg_affected_rows($result));

        if ($this->identity_column !== null && pg_num_rows($result) > 0) {
            $index = pg_field_num($result, $this->identity_column);

            if ($index >= 0) {
                $id = pg_fetch_result($result, 0, $index);

                if ($id !== null && filter_var($id, FILTER_VALIDATE_INT) !== false) {
                    $this->last_insert_id = (int) $id;
                }
            }
        }
    } // accept_result

    /**
     * Adds RETURNING for a table's discovered serial or identity column.
     *
     * Metadata is cached on the shared connection. An actual INSERT result is used instead of lastval().
     *
     * @param string $sql
     * The SQL statement to inspect.
     *
     * @return string
     * Returns SQL extended with RETURNING when one generated column is found and no RETURNING clause is present.
     *
     * @throws DBWorkerException
     * If the identity metadata query fails.
     *
     * @author Oleg Schildt
     */
    private function with_identity(string $sql): string
    {
        // Capture an identifier from the INSERT itself instead of consulting unrelated session sequence state.
        $this->identity_column = null;
        $table = $this->insert_table($sql);

        if ($table === null) {
            return $sql;
        }

        $db = $this->connection_state->handle;
        $cache = &$this->connection_state->attributes['identity_columns'];
        $key = $this->get_schema() . ':' . $table;

        if (!array_key_exists($key, $cache ?? [])) {
            $result = @pg_query_params(
                $db,
                "select a.attname from pg_attribute a where a.attrelid = to_regclass($1) and a.attnum > 0 and not a.attisdropped
                 and (a.attidentity <> '' or pg_get_serial_sequence($1, a.attname) is not null) order by a.attnum",
                [$table]
            );

            if ($result === false) {
                throw $this->query_error(pg_last_error($db));
            }

            $cache[$key] = pg_num_rows($result) === 1 ? pg_fetch_row($result)[0] : null;

            pg_free_result($result);
        }

        $this->identity_column = $cache[$key];

        if ($this->identity_column !== null && !preg_match('/\bRETURNING\b/i', $sql)) {
            $sql = rtrim($sql, " \t\r\n;") . ' RETURNING ' . pg_escape_identifier($db, $this->identity_column);
        }

        return $sql;
    } // with_identity

    /**
     * Fetches the next row, requesting one row at a time from an active server cursor.
     *
     * EOF closes the cursor and completes an internally owned transaction after the last
     * shared cursor closes. A cursor closed by a caller commit or rollback also returns EOF.
     *
     * @return list<mixed>|null
     * Returns a numerically indexed row, or NULL at the end of the result set.
     *
     * @throws DBWorkerException
     * If fetching the next cursor row fails.
     *
     * @see DBWorker::fetch_row()
     *
     * @author Oleg Schildt
     */
    protected function native_fetch(): ?array
    {
        if ($this->cursor_name !== null) {
            if (!isset($this->connection_state->attributes['read_cursors'][$this->cursor_name])) {
                $this->native_free_result();

                return null;
            }

            if ($this->result instanceof \PgSql\Result) {
                pg_free_result($this->result);
            }

            $this->result = @pg_query($this->connection_state->handle, 'FETCH FORWARD 1 FROM ' . $this->cursor_name);

            if ($this->result === false) {
                $error = $this->query_error(pg_last_error($this->connection_state->handle));

                $this->native_free_result();

                throw $error;
            }
        }

        if (!$this->result instanceof \PgSql\Result) {
            return null;
        }

        $row = pg_fetch_row($this->result);

        if ($row === false && $this->cursor_name !== null) {
            $this->native_free_result();
        }

        return $row === false ? null : $row;
    } // native_fetch

    /**
     * Releases native result resources without discarding a reusable prepared statement.
     *
     * Closes the server cursor and completes an internally owned transaction when its last
     * cursor closes. An aborted internally owned transaction is rolled back. Caller-owned
     * transactions remain under the caller's control.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If closing a live cursor or completing the internally owned transaction fails.
     *
     * @see DBWorker::free_result()
     *
     * @author Oleg Schildt
     */
    protected function native_free_result(): void
    {
        // Release result ownership while leaving a reusable prepared statement available.
        if ($this->result instanceof \PgSql\Result) {
            pg_free_result($this->result);
        }

        $this->result = null;

        if ($this->cursor_name !== null) {
            $name = $this->cursor_name;
            $this->cursor_name = null;

            // A failed transaction has already invalidated its cursors; rollback releases them.
            if (
                isset($this->connection_state->attributes['read_cursors'][$name])
                && pg_transaction_status($this->connection_state->handle) === PGSQL_TRANSACTION_INTRANS
            ) {
                $this->control('CLOSE ' . $name);
            }

            unset($this->connection_state->attributes['read_cursors'][$name]);

            if (
                empty($this->connection_state->attributes['read_cursors'])
                && ($this->connection_state->attributes['cursor_transaction'] ?? false)
            ) {
                $this->connection_state->attributes['cursor_transaction'] = false;
                $failed = pg_transaction_status($this->connection_state->handle) === PGSQL_TRANSACTION_INERROR;

                $this->control($failed ? 'ROLLBACK' : 'COMMIT');
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
        if ($this->statement instanceof \PgSql\Result) {
            pg_free_result($this->statement);
        }

        $this->statement = null;

        if ($this->statement_name !== null && $this->connection_state !== null) {
            $this->connection_state->attributes['pending_deallocations'][] = $this->statement_name;
            $this->statement_name = null;

            $this->flush_deallocations();
        }

        $this->identity_column = null;
        $this->cursor_sql = null;
    } // native_free_statement

    /**
     * Deallocates queued PostgreSQL prepared statements when the connection can execute SQL.
     *
     * Deallocation is postponed while the connection is in an aborted transaction.
     * The queue belongs to the shared connection so clones do not overwrite each other's prepared statements.
     *
     * @return void
     * Does not return a value.
     *
     * @author Oleg Schildt
     */
    private function flush_deallocations(): void
    {
        // An aborted PostgreSQL transaction cannot execute DEALLOCATE; keep names queued until recovery.
        if (
            $this->connection_state === null
            || pg_transaction_status($this->connection_state->handle) === PGSQL_TRANSACTION_INERROR
        ) {
            return;
        }

        foreach ($this->connection_state->attributes['pending_deallocations'] ?? [] as $name) {
            $result = @pg_query(
                $this->connection_state->handle,
                'DEALLOCATE ' . pg_escape_identifier($this->connection_state->handle, $name)
            );

            if ($result !== false) {
                pg_free_result($result);
            }
        }

        $this->connection_state->attributes['pending_deallocations'] = [];
    } // flush_deallocations

    /**
     * Performs a native transaction operation on the shared connection.
     *
     * The base class updates the shared transaction flag only after this operation succeeds.
     *
     * @param string $action
     * The operation: begin, commit or rollback.
     *
     * @param string $isolation_level
     * The validated isolation level applied by BEGIN; ignored for commit and rollback.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If a cursor-owned read transaction is active or the native transaction operation fails.
     *
     * @author Oleg Schildt
     */
    protected function native_transaction(string $action, string $isolation_level = self::ISOLATION_READ_COMMITTED): void
    {
        // Do not let BEGIN silently join the transaction owned by an unfinished cursor.
        $this->check_cursor_transaction();

        $this->control(match ($action) {
            'begin' => 'BEGIN ISOLATION LEVEL ' . $isolation_level,
            'commit' => 'COMMIT',
            'rollback' => 'ROLLBACK'
        });

        if ($action !== 'begin') {
            // PostgreSQL closes non-holdable cursors when their transaction ends.
            $this->connection_state->attributes['read_cursors'] = [];
        }

        $this->flush_deallocations();
    } // native_transaction

    /**
     * Stores large data from an input stream using one unique bind parameter.
     *
     * Example:
     * ```php
     * $stream = fopen(".../large_binary.jpg", "rb");
     *
     * try {
     *     $dbw->stream_long_data("UPDATE large_data SET blob_data = ? WHERE id = 1", $stream);
     * } finally {
     *     fclose($stream);
     * }
     * ```
     *
     * The new large object and the row update use one transaction. An existing transaction
     * is protected by a savepoint; otherwise this method owns the transaction. Failed uploads
     * are rolled back. Replacing an existing OID does not unlink the old object.
     *
     * @param string $query_string
     * The INSERT or UPDATE query containing one positional marker or one unique named marker.
     * A named marker may occur in multiple positions.
     *
     * @param resource $stream
     * An open readable stream. Reading starts at its current position.
     * The caller retains ownership and must close it. A seekable stream is restored to its initial position.
     *
     * @param int $type
     * DB_AS_IS selects a binary BLOB; DB_STRING selects UTF-8 text.
     * For PostgreSQL this method writes a large object and stores its OID in the target column.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If no connection is open, the stream or type is invalid, the query does not contain exactly one
     * unique parameter, or preparing, transferring, updating, or completing the transaction fails.
     *
     * @see DBWorker::copy_field_to_stream()
     * @see DBWorker::execute_prepared_query()
     * @see PostgreSQL_DBWorker::unlink_large_object()
     *
     * @author Oleg Schildt
     */
    public function stream_long_data(string $query_string, $stream, int $type = self::DB_AS_IS): void
    {
        // Keep the new large object and the row's OID assignment in the same transaction or savepoint.
        $this->check_connection();

        $this->assert_stream($stream);

        if (!in_array($type, [self::DB_AS_IS, self::DB_STRING], true)) {
            throw new DBWorkerException(
                'Stream type must be DB_AS_IS (binary) or DB_STRING (UTF-8 text).',
                self::ERR_WRONG_DATA_FORMAT
            );
        }

        $this->prepare_query($query_string);

        $names = array_unique($this->compiled_query->names);

        if (count($names) !== 1) {
            $this->free_prepared_query();

            throw new DBWorkerException(
                'stream_long_data requires exactly one unique parameter.',
                self::ERR_WRONG_DATA_FORMAT
            );
        }

        $own = !$this->connection_state->transaction;

        // A savepoint protects an existing caller transaction from a failed upload.
        $savepoint = 'sf_lob_' . bin2hex(random_bytes(6));

        if ($own) {
            $this->start_transaction();
        } else {
            $this->control('SAVEPOINT ' . $savepoint);
        }

        $handle = null;

        try {
            $oid = @pg_lo_create($this->connection_state->handle);

            if ($oid === false) {
                throw $this->stream_error('Cannot create PostgreSQL large object.');
            }

            $offset = 0;

            $this->write_chunks(
                $stream,
                function (string $chunk) use ($oid, &$offset): void {
                    // lo_put is binary-safe even on PHP builds whose pg_lo_write rejects NUL.
                    $result = @pg_query_params(
                        $this->connection_state->handle,
                        'select lo_put($1::oid, $2::bigint, $3::bytea)',
                        [$oid, $offset, pg_escape_bytea($this->connection_state->handle, $chunk)]
                    );

                    if ($result === false) {
                        throw $this->stream_error(pg_last_error($this->connection_state->handle));
                    }

                    pg_free_result($result);

                    $offset += strlen($chunk);
                },
                $type === self::DB_STRING
            );

            // Assign the OID only after all chunks have been written successfully.
            $this->execute_prepared_query($this->compiled_query->named ? [$names[0] => $oid] : [$oid]);

            if ($own) {
                $this->commit_transaction();
            } else {
                $this->control('RELEASE SAVEPOINT ' . $savepoint);
            }
        } catch (\Throwable $error) {
            if ($handle !== null && $handle !== false) {
                @pg_lo_close($handle);
            }

            if ($own) {
                $this->rollback_transaction();
            } else {
                $this->control('ROLLBACK TO SAVEPOINT ' . $savepoint);

                $this->control('RELEASE SAVEPOINT ' . $savepoint);
            }

            throw $error;
        } finally {
            $this->free_prepared_query();
        }
    } // stream_long_data

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
     * Returns decoded bytea data, complete OID large-object contents when requested,
     * or the native field value.
     *
     * @throws DBWorkerException
     * If a stream is invalid or a read/write operation fails.
     *
     * @author Oleg Schildt
     */
    protected function decode_value(int $index, int $type): mixed
    {
        // Decode bytea bytes explicitly and materialize an OID only when the legacy LOB flag requests it.
        if ($this->row[$index] === null) {
            return null;
        }

        if ($type === self::DB_LARGE_OBJECT_STREAM && $this->metadata[$index]['type'] === 'oid') {
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
        }

        return $this->metadata[$index]['type'] === 'bytea' ? pg_unescape_bytea($this->row[$index]) : $this->row[$index];
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
        // Distinguish OID large objects from ordinary text/bytea fields before choosing the read path.
        if ($this->row[$index] === null) {
            return 0;
        }

        if ($this->metadata[$index]['type'] !== 'oid') {
            $count = 0;

            $this->write_chunks(
                (string) $this->decode_value($index, self::DB_AS_IS),
                function (string $chunk) use ($stream, &$count): void {
                    $count += $this->write_all($stream, $chunk);
                }
            );

            return $count;
        }

        $own = !$this->connection_state->transaction
            && !($this->connection_state->attributes['cursor_transaction'] ?? false);

        if ($own) {
            $this->start_transaction();
        }

        $handle = null;

        try {
            $handle = @pg_lo_open($this->connection_state->handle, $this->row[$index], 'r');

            if ($handle === false) {
                throw $this->stream_error('Cannot open PostgreSQL large object.');
            }

            $count = 0;

            while (true) {
                $chunk = @pg_lo_read($handle, 65536);

                if ($chunk === false) {
                    throw $this->stream_error('Cannot read PostgreSQL large object.');
                }

                if ($chunk === '') {
                    break;
                }

                $count += $this->write_all($stream, $chunk);
            }

            pg_lo_close($handle);

            $handle = null;

            if ($own) {
                $this->commit_transaction();
            }

            return $count;
        } catch (\Throwable $error) {
            if ($handle !== null && $handle !== false) {
                @pg_lo_close($handle);
            }

            if ($own) {
                $this->rollback_transaction();
            }

            throw $error;
        }
    } // copy_value_to_stream

    /**
     * Deletes a PostgreSQL large object that is no longer referenced by any row.
     *
     * Replacing or deleting an OID reference does not automatically unlink the large object.
     * This operation uses the existing transaction or starts and completes its own.
     *
     * @param int $oid
     * The OID of the unreferenced large object.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If no connection is open, PostgreSQL cannot unlink the object, or starting or completing
     * the transaction fails.
     *
     * @author Oleg Schildt
     */
    public function unlink_large_object(int $oid): void
    {
        // Use the caller's transaction when present; otherwise own the transaction around deletion.
        $this->check_connection();

        $own = !$this->connection_state->transaction;

        if ($own) {
            $this->start_transaction();
        }

        try {
            if (!@pg_lo_unlink($this->connection_state->handle, $oid)) {
                throw $this->stream_error('Cannot unlink PostgreSQL large object.');
            }

            if ($own) {
                $this->commit_transaction();
            }
        } catch (\Throwable $error) {
            if ($own) {
                $this->rollback_transaction();
            }

            throw $error;
        }
    } // unlink_large_object

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
        // Pass the actual connection so escaping uses its character encoding.
        $this->check_connection();

        return pg_escape_string($this->connection_state->handle, $str);
    } // escape
    /**
     * Resolves PostgreSQL relation names, typed keys, collations and serial or identity sequences.
     *
     * @param string $table
     * The unquoted table name resolved through the current search path.
     *
     * @return array
     * Returns canonical relation and native column metadata.
     *
     * @throws DBWorkerException
     * If the target cannot be resolved or its column metadata cannot be read.
     *
     * @author Oleg Schildt
     */
    protected function native_save_table(string $table): array
    {
        $rows = $this->save_sql("SELECT current_database(), n.nspname, c.relname FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace "
            . "WHERE c.oid = to_regclass(?) AND c.relkind IN ('r', 'p')", [$table]);

        if (count($rows['rows']) !== 1) {
            throw $this->query_error('The save_records table was not found.');
        }

        [$database, $schema, $name] = $rows['rows'][0];
        $quote = static fn(string $name): string => '"' . str_replace('"', '""', $name) . '"';
        $qualified = $quote($schema) . '.' . $quote($name);
        $rows = $this->save_sql("SELECT a.attname, format_type(a.atttypid, a.atttypmod), cn.nspname, co.collname, "
            . "pg_get_serial_sequence(?, a.attname), a.attidentity FROM pg_attribute a "
            . 'LEFT JOIN pg_collation co ON co.oid = a.attcollation LEFT JOIN pg_namespace cn ON cn.oid = co.collnamespace '
            . 'WHERE a.attrelid = to_regclass(?) AND a.attnum > 0 AND NOT a.attisdropped', [$qualified, $qualified]);
        $context = [
            'table' => $qualified,
            'lock_table' => 'public.dbworker_locks',
            'resource' => [$database, $schema, $name],
            'columns' => []
        ];

        foreach ($rows['rows'] as [$field, $type, $collation_schema, $collation, $sequence, $identity]) {
            $cast = 'CAST(? AS ' . $type . ')' . ($collation === null ? '' : ' COLLATE ' . $quote($collation_schema) . '.' . $quote($collation));
            $context['columns'][strtolower($field)] = [
                'name' => $field,
                'cast' => $cast,
                'sequence' => $sequence,
                'identity' => $sequence !== null,
                'always' => false,
                'override' => $identity === 'a'
            ];
        }

        return $context;
    } // native_save_table

    /**
     * Uses PostgreSQL type-aware hashing, including nondeterministic collation support.
     *
     * @param array $context
     * The resolved table, column metadata and service-table location.
     *
     * @param array<string, scalar|null> $keys
     * The supplied key values, ordered by canonical column name.
     *
     * @return array
     * Returns the equality-compatible record fingerprint.
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
            $expressions[] = $this->save_column($context, $field)['cast'];
        }

        // PostgreSQL's record hash dispatches to each type's equality-compatible hash support function.
        $result = $this->save_sql('SELECT hash_record_extended(ROW(' . implode(', ', $expressions) . '), 0)::text', array_values($keys));

        return $result['rows'][0];
    } // native_save_key

    /**
     * Creates or locks a PostgreSQL service row without aborting the transaction on a duplicate.
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
        $this->save_sql('INSERT INTO ' . $context['lock_table'] . ' (resource_key, resource_name) VALUES (?, ?) '
            . 'ON CONFLICT (resource_key) DO NOTHING', [$key, $name]);

        // A separate READ COMMITTED statement sees a competing creator after its transaction commits.
        $this->save_sql('SELECT resource_key FROM ' . $context['lock_table'] . ' WHERE resource_key = ? FOR UPDATE', [$key]);
    } // native_save_lock

    /**
     * Requires a usable PostgreSQL transaction with fresh snapshots after service-lock waits.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If the transaction is aborted or uses a fixed-snapshot isolation level.
     *
     * @author Oleg Schildt
     */
    protected function native_save_check(): void
    {
        parent::native_save_check();

        if (pg_transaction_status($this->connection_state->handle) === PGSQL_TRANSACTION_INERROR) {
            throw $this->query_error('Roll back the aborted PostgreSQL transaction before saving records.');
        }

        // An internally owned save always starts READ COMMITTED, regardless of the session default.
        $level = $this->connection_state->transaction
            ? strtolower($this->save_sql('SHOW transaction_isolation')['rows'][0][0]) : 'read committed';

        if (!in_array($level, ['read committed', 'read uncommitted'], true)) {
            throw new DBWorkerException('save_records() requires READ COMMITTED in PostgreSQL.', self::ERR_WRONG_DATA_FORMAT);
        }
    } // native_save_check

    /**
     * Obtains a native PostgreSQL sequence value for the coordinated identity allocator.
     *
     * @param string $sequence
     * The metadata-resolved sequence name.
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
        $result = $this->save_sql('SELECT nextval(CAST(? AS regclass))::text', [$sequence]);

        return $this->save_identity_value($result['rows'][0][0]);
    } // native_save_next_identity

    /**
     * Preserves explicit PostgreSQL identities and coordinates generated IDs above the service watermark.
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
        $explicit = $values[$identity_field] ?? null;

        do {
            $identity = $this->save_allocate_identity($context, $identity_field, $explicit);
            [$key, $name] = $this->save_resource($context, [strtolower($identity_field) => $identity]);

            $this->native_save_lock($context, $key, $name);

            // A concurrent explicit-ID save may have committed this formerly free candidate while we waited.
            $occupied = $explicit === null
                && $this->native_save_exists($context, $identity_field . ' = ' . $column['cast'], [$identity]);
        } while ($occupied);

        $values[$identity_field] = $identity;
        $expressions[$identity_field] = '?';
        $sql = 'INSERT INTO ' . $context['table'] . ' (' . implode(', ', array_keys($values)) . ')'
            . ($column['override'] ? ' OVERRIDING SYSTEM VALUE' : '')
            . ' VALUES (' . implode(', ', $expressions) . ') RETURNING ' . $identity_field;

        $result = $this->save_sql($sql, array_values($values));
        $this->affected_rows = $result['affected'];

        return $identity;
    } // native_save_insert


} // PostgreSQL_DBWorker

