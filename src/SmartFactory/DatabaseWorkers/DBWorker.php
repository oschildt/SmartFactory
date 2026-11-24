<?php
/**
 * This file contains the declaration and shared implementation of the class DBWorker.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\DatabaseWorkers;

use SmartFactory\Interfaces\IInitable;

/**
 * Abstract base class for native database workers.
 *
 * This wrapper provides a common API for MySQL, PostgreSQL, Microsoft SQL Server and Oracle.
 * Shared implementations validate bind arguments, manage results and streams, and coordinate
 * transaction state. Derived classes implement the native driver operations.
 *
 * Workers created with create_clone() share connection ownership and transaction state,
 * while their prepared statements and fetched rows remain independent.
 *
 * @see MySQL_DBWorker
 * @see MSSQL_DBWorker
 * @see PostgreSQL_DBWorker
 * @see Oracle_DBWorker
 *
 * @author Oleg Schildt
 */
abstract class DBWorker implements IInitable
{
    /**
     * Reads committed data using the database's statement-level isolation rules.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    public const string ISOLATION_READ_COMMITTED = 'READ COMMITTED';

    /**
     * Requests repeatable reads; Oracle does not support this named isolation level.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    public const string ISOLATION_REPEATABLE_READ = 'REPEATABLE READ';

    /**
     * Requests the database's native serializable transaction isolation mode.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    public const string ISOLATION_SERIALIZABLE = 'SERIALIZABLE';

    /**
     * Error code for missing or incomplete connection settings.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    public const string ERR_CONNECTION_DATA_INCOMPLETE = 'err_connection_data_incomplete';

    /**
     * Error code for a failed native database connection.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    public const string ERR_CONNECTION_FAILED = 'err_connection_failed';

    /**
     * Error code for an unreachable database server.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    public const string ERR_HOST_UNREACHABLE = 'err_host_unreachable';

    /**
     * Error code for rejected database credentials.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    public const string ERR_WRONG_USER_CREDENTIALS = 'err_wrong_user_credentials';

    /**
     * Error code for a database that cannot be selected or found.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    public const string ERR_DATABASE_NOT_FOUND = 'err_database_not_found';

    /**
     * Error code for failed query execution or transaction operations.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    public const string ERR_QUERY_FAILED = 'err_query_failed';

    /**
     * Error code for an operation requiring an open connection.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    public const string ERR_NOT_CONNECTED = 'err_not_connected';

    /**
     * Error code for invalid streams or failed stream I/O operations.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    public const string ERR_STREAM_ERROR = 'err_stream_error';

    /**
     * Error code for invalid bind arguments, field references or value formats.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    public const string ERR_WRONG_DATA_FORMAT = 'err_data_format';

    /**
     * Uses the native value without an explicit field conversion; selects binary stream upload data.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    public const int DB_AS_IS = 0;

    /**
     * Identifies a numeric value to validate when preparing an SQL expression.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    public const int DB_NUMBER = 1;

    /**
     * Identifies a string value or selects UTF-8 text for a stream upload.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    public const int DB_STRING = 2;

    /**
     * Identifies a date SQL expression or requests a Unix timestamp when reading a date field.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    public const int DB_DATE = 3;

    /**
     * Identifies a date/time SQL expression or requests a Unix timestamp when reading a date/time field.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    public const int DB_DATETIME = 4;

    /**
     * Identifies a geometry SQL expression supported by the MySQL driver.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    public const int DB_GEOMETRY = 5;

    /**
     * Identifies a MySQL geometry expression using SRID 4326 and latitude-longitude axis order.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    public const int DB_GEOMETRY_4326 = 6;

    /**
     * Legacy flag for reading complete LOB contents as a string, not a stream resource.
     *
     * Use copy_field_to_stream() to copy a value to an output stream.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    public const int DB_LARGE_OBJECT_STREAM = 7;

    /**
     * Stores the configured server address or Oracle connection descriptor.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $db_server = '';

    /**
     * Stores the optional TCP port as a string; an empty string selects the driver default.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $db_port = '';

    /**
     * Stores the configured database name or Oracle service name.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $db_name = '';

    /**
     * Stores the database user name.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $db_user = '';

    /**
     * Stores the database password used when establishing a connection.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $db_password = '';

    /**
     * Requests a native read-only mode where the selected driver supports it.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $read_only = false;

    /**
     * Stores the isolation level used when start_transaction() receives no explicit level.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $default_isolation_level = self::ISOLATION_READ_COMMITTED;

    /**
     * Controls query logging through the SmartFactory debugger.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $logging = false;

    /**
     * Stores the last application query or prepared-query template without interpolated values.
     *
     * @var string|null
     *
     * @author Oleg Schildt
     */
    protected ?string $last_query = null;

    /**
     * Stores connection settings and driver-specific options supplied to init().
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected array $options = [];

    /**
     * Stores the connection owner and transaction state shared with cloned workers.
     *
     * @var DBConnectionState|null
     *
     * @author Oleg Schildt
     */
    protected ?DBConnectionState $connection_state = null;

    /**
     * Stores the compiled SQL and validated parameter mapping of the current prepared query.
     *
     * @var PreparedQuery|null
     *
     * @author Oleg Schildt
     */
    protected ?PreparedQuery $compiled_query = null;

    /**
     * Stores the native prepared statement or preparation acknowledgement, depending on the driver.
     *
     * @var mixed
     *
     * @author Oleg Schildt
     */
    protected mixed $statement = null;

    /**
     * Stores the current native result handle. It may refer to the prepared statement itself in OCI8.
     *
     * @var mixed
     *
     * @author Oleg Schildt
     */
    protected mixed $result = null;

    /**
     * Stores the current numerically indexed row, including any row-owned LOB descriptors or streams.
     *
     * @var array|null
     *
     * @author Oleg Schildt
     */
    protected ?array $row = null;

    /**
     * Stores ordered metadata for the columns of the current result set.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected array $metadata = [];

    /**
     * Stores the native affected-row count of the last executed statement.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $affected_rows = 0;

    /**
     * Stores the generated integer identifier captured from the last INSERT, if available.
     *
     * @var int|null
     *
     * @author Oleg Schildt
     */
    protected ?int $last_insert_id = null;

    /**
     * Indicates whether the current statement exposes result columns that can be fetched.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $result_open = false;

    /**
     * Returns the SQL dialect used by the placeholder compiler.
     *
     * @return string
     * Returns mysql, mssql, postgres or oracle.
     *
     * @see PreparedQuery::compile()
     *
     * @author Oleg Schildt
     */
    abstract protected function dialect(): string;

    /**
     * Returns the name of the required PHP extension.
     *
     * @return string
     * Returns the name of the required PHP extension.
     *
     * @see DBWorker::is_extension_installed()
     *
     * @author Oleg Schildt
     */
    abstract public function get_extension_name(): string;

    /**
     * Returns the name of the supported database.
     *
     * @return string
     * Returns the name of the supported database.
     *
     * @author Oleg Schildt
     */
    abstract public function get_rdbms_name(): string;

    /**
     * Returns the connection state.
     *
     * @return bool
     * Returns true if the connection is open, otherwise false.
     *
     * @see DBWorker::check_connection()
     * @see DBWorker::connect()
     * @see DBWorker::close_connection()
     *
     * @author Oleg Schildt
     */
    abstract public function is_connected(): bool;

    /**
     * Establishes the native connection using the initialized driver settings.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception if connection settings are incomplete or the native connection fails.
     *
     * @see DBWorker::init()
     * @see DBWorker::is_connected()
     * @see DBWorker::close_connection()
     *
     * @author Oleg Schildt
     */
    abstract public function connect(): void;

    /**
     * Executes SQL through the native driver and records the resulting metadata.
     *
     * @param string $sql
     * The SQL query to execute. Driver-specific identity handling may extend it.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception if native query execution fails.
     *
     * @see DBWorker::execute_query()
     *
     * @author Oleg Schildt
     */
    abstract protected function native_query(string $sql): void;

    /**
     * Creates a native statement from SQL whose bind markers have already been compiled.
     *
     * @param string $sql
     * The SQL query containing native bind markers.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception if the native statement cannot be prepared.
     *
     * @see DBWorker::prepare_query()
     *
     * @author Oleg Schildt
     */
    abstract protected function native_prepare(string $sql): void;

    /**
     * Binds the validated argument values and executes the native prepared statement.
     *
     * @param array $values
     * The ordered values produced by PreparedQuery::arguments(), including supported LOB wrappers.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception if binding, LOB processing or native execution fails.
     *
     * @see DBWorker::execute_prepared_query()
     *
     * @author Oleg Schildt
     */
    abstract protected function native_execute(array $values): void;

    /**
     * Releases native result resources without discarding a reusable prepared statement.
     *
     * Implementations must distinguish a result handle from a statement handle when both refer to the same resource.
     *
     * @return void
     *
     * @see DBWorker::free_result()
     *
     * @author Oleg Schildt
     */
    abstract protected function native_free_result(): void;

    /**
     * Releases the native prepared statement and its driver-specific bind state.
     *
     * @return void
     *
     * @see DBWorker::free_prepared_query()
     *
     * @author Oleg Schildt
     */
    abstract protected function native_free_statement(): void;

    /**
     * Fetches the next row through the native driver.
     *
     * @return array|null
     * Returns a numerically indexed row, or NULL at the end of the result set.
     *
     * @throws DBWorkerException
     * It might throw an exception if the native fetch operation fails.
     *
     * @see DBWorker::fetch_row()
     *
     * @author Oleg Schildt
     */
    abstract protected function native_fetch(): ?array;

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
     *
     * @return void
     * Starts or completes the native transaction without returning a value.
     *
     * @throws DBWorkerException
     * It might throw an exception if the native transaction operation fails.
     *
     * @author Oleg Schildt
     */
    abstract protected function native_transaction(string $action, string $isolation_level = self::ISOLATION_READ_COMMITTED): void;

    /**
     * Restores session settings after the managed transaction has ended.
     *
     * @return void
     * The default implementation needs no cleanup because its isolation setting is transaction-scoped.
     *
     * @throws DBWorkerException
     * A driver override may fail to restore the connection's previous session isolation level.
     * The completed transaction remains marked as ended even if restoration fails.
     *
     * @author Oleg Schildt
     */
    protected function native_restore_transaction_isolation(): void
    {
        // Drivers with session-scoped isolation settings override this transaction-completion hook.
    } // native_restore_transaction_isolation

    /**
     * Completes any procedure results that must be consumed before freeing its statement.
     *
     * @return void
     * The default implementation requires no additional result handling.
     *
     * @throws DBWorkerException
     * A driver override may report a deferred procedure error while consuming its remaining results.
     *
     * @author Oleg Schildt
     */
    protected function native_finish_procedure(): void
    {
        // Drivers with pending protocol results override this hook before statement cleanup.
    } // native_finish_procedure

    /**
     * Resolves a save target and supplies native column comparison metadata.
     *
     * @param string $table
     * The validated unquoted table name, optionally qualified by schema or database.
     *
     * @return array
     * Returns the quoted target and service table, canonical resource identity and column metadata.
     *
     * @throws DBWorkerException
     * If the table is absent, its name is unsupported or metadata cannot be read.
     *
     * @author Oleg Schildt
     */
    abstract protected function native_save_table(string $table): array;

    /**
     * Converts record keys to fingerprints compatible with native database equality.
     *
     * @param array $context
     * The table and column metadata returned by native_save_table().
     *
     * @param array<string, scalar|null> $keys
     * The supplied key values ordered by canonical column name.
     *
     * @return array
     * Returns native fingerprints that are equal whenever the corresponding typed keys compare equal.
     *
     * @throws DBWorkerException
     * If a key type is unsupported, a value cannot be converted or normalization SQL fails.
     *
     * @author Oleg Schildt
     */
    abstract protected function native_save_key(array $context, array $keys): array;

    /**
     * Ensures that a service row exists and holds its exclusive lock until transaction end.
     *
     * @param array $context
     * The resolved target and service-table metadata.
     *
     * @param string $key
     * The SHA-256 resource key.
     *
     * @param string $name
     * The description to store only when creating the row.
     *
     * @return void
     * Returns only after the exclusive resource lock has been acquired.
     *
     * @throws DBWorkerException
     * If the service table is unavailable, lock acquisition fails or the transaction cannot continue.
     * Expected concurrent creation of the same primary key must be handled by the driver.
     *
     * @author Oleg Schildt
     */
    abstract protected function native_save_lock(array $context, string $key, string $name): void;

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
     * A connection-dependent driver might throw an exception if no connection is open.
     *
     * @see DBWorker::quotes_or_null()
     * @see DBWorker::prepare_query()
     *
     * @author Oleg Schildt
     */
    abstract public function escape(string $str): string;

    /**
     * Initializes the DBWorker with connection parameters and driver-specific options.
     *
     * @param array $parameters
     * Connection settings and options as an associative array.
     *
     * - $parameters["db_server"] - server address or Oracle connection descriptor.
     * - $parameters["db_port"] - optional TCP port.
     * - $parameters["db_name"] - database name or Oracle service name.
     * - $parameters["db_user"] - database user name.
     * - $parameters["db_password"] - database password; an empty password is permitted.
     * - $parameters["db_schema"] - optional schema name.
     * - $parameters["read_only"] - requests a supported native read-only mode.
     * - $parameters["default_isolation_level"] - default for start_transaction(); READ COMMITTED when not configured.
     * - $parameters["query_logger"] - optional query timing callback.
     *
     * Timeout and isolation options depend on the driver. Supplied false and empty values
     * replace previous settings. This method does not open or replace a connection.
     *
     * @return void
     * Updates the supplied connection settings and the default transaction isolation level.
     *
     * @throws DBWorkerException
     * If the port is not a number between 1 and 65535 or the default isolation is not a supported string value.
     *
     * @see DBWorker::connect()
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void
    {
        // Apply explicitly supplied values so empty strings and false do not silently preserve old settings.
        foreach (['db_server', 'db_port', 'db_name', 'db_user', 'db_password'] as $key) {
            if (array_key_exists($key, $parameters)) {
                $this->$key = (string) $parameters[$key];
            }
        }

        if (
            $this->db_port !== ''
            && (!ctype_digit($this->db_port) || (int) $this->db_port < 1 || (int) $this->db_port > 65535)
        ) {
            throw new DBWorkerException('Database port must be between 1 and 65535.', self::ERR_WRONG_DATA_FORMAT);
        }

        if (array_key_exists('read_only', $parameters)) {
            $this->read_only = (bool) $parameters['read_only'];
        }

        if (array_key_exists('default_isolation_level', $parameters)) {
            $level = $parameters['default_isolation_level'];

            if (!is_string($level)) {
                throw new DBWorkerException('The default isolation level must be a string.', self::ERR_WRONG_DATA_FORMAT);
            }

            $level = strtoupper(trim($level));

            if (!in_array($level, [self::ISOLATION_READ_COMMITTED, self::ISOLATION_REPEATABLE_READ,
                self::ISOLATION_SERIALIZABLE], true)) {
                throw new DBWorkerException('Unsupported default transaction isolation level: ' . $level, self::ERR_WRONG_DATA_FORMAT);
            }

            $this->default_isolation_level = $level;
        }

        // Retain driver options alongside the common connection settings.
        $this->options = array_replace($this->options, $parameters);
    } // init

    /**
     * Checks whether the PHP extension required by this driver is loaded.
     *
     * @return bool
     * Returns true if the extension is loaded, otherwise false.
     *
     * @see DBWorker::get_extension_name()
     *
     * @author Oleg Schildt
     */
    public function is_extension_installed(): bool
    {
        // Check the driver requirement before attempting native operations.
        return extension_loaded($this->get_extension_name());
    } // is_extension_installed

    /**
     * Checks whether a connection exists and throws an exception if it does not.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception if the connection is not open.
     *
     * @see DBWorker::is_connected()
     * @see DBWorker::connect()
     *
     * @author Oleg Schildt
     */
    public function check_connection(): void
    {
        // Fail before a native operation receives a missing or detached connection.
        if (!$this->is_connected()) {
            throw new DBWorkerException('Database server not connected!', self::ERR_NOT_CONNECTED);
        }
    } // check_connection

    /**
     * Creates a clone of the DBWorker that uses the same open connection.
     *
     * This is useful for additional queries while iterating over an active result set.
     * Closing one worker does not close the shared connection while another worker owns it.
     * Native driver restrictions on concurrent results still apply.
     *
     * @return DBWorker
     * Returns a worker sharing the connection and transaction state, with independent statements and results.
     *
     * @author Oleg Schildt
     */
    public function create_clone(): DBWorker
    {
        // Use the base clone hook to separate per-worker handles while retaining shared connection ownership.
        return clone $this;
    } // create_clone

    /**
     * Resets per-worker result and statement state after cloning.
     *
     * The shared connection object is deliberately retained. Native handles belonging to the original
     * worker must not be freed from the clone.
     *
     * @return void
     *
     * @see DBWorker::create_clone()
     *
     * @author Oleg Schildt
     */
    public function __clone()
    {
        // Detach copied result handles before resetting native state; the original worker still owns those handles.
        $this->statement = $this->result = $this->row = null;
        $this->compiled_query = null;
        $this->metadata = [];
        $this->last_query = null;
        $this->result_open = false;
        $this->affected_rows = 0;
        $this->last_insert_id = null;

        $this->reset_driver_state();
    } // __clone

    /**
     * Resets driver-specific fields that must not be shared by cloned workers.
     *
     * The base implementation has no additional native fields to reset.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    protected function reset_driver_state(): void
    {
        // Reset only worker-owned native state; shared connection attributes remain intact.
    } // reset_driver_state

    /**
     * Releases this worker's resources when the object is destroyed.
     *
     * Cleanup exceptions are suppressed here so an exception already being unwound is not replaced.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function __destruct()
    {
        // Release this owner without disturbing resources still owned by another worker.
        try {
            $this->close_connection();
        } catch (\Throwable) {
            // Never replace an exception already being unwound.
        }
    } // __destruct

    /**
     * Releases this worker's statements and detaches it from the shared connection.
     *
     * The native connection is closed when the last worker using it detaches.
     * This method can also be called when the worker has not been connected.
     *
     * @return void
     *
     * @see DBWorker::create_clone()
     * @see DBWorker::free_prepared_query()
     *
     * @author Oleg Schildt
     */
    public function close_connection(): void
    {
        // Release statements before dropping this worker's reference to the shared connection owner.
        $this->free_prepared_query();

        $this->connection_state = null;
    } // close_connection

    /**
     * Returns the configured or driver-default schema name.
     *
     * @return string
     * Returns the schema name, or an empty string when no schema qualification is required.
     *
     * @see DBWorker::qualify_name_with_schema()
     *
     * @author Oleg Schildt
     */
    public function get_schema(): string
    {
        // Resolve the configured schema before falling back to the driver's default.
        return (string) ($this->options['db_schema'] ?? match ($this->dialect()) {
            'postgres' => 'public',
            'mssql' => 'dbo',
            'oracle' => strtoupper($this->db_user),
            default => ''
        });
    } // get_schema

    /**
     * Qualifies an unqualified name with the current schema.
     *
     * This helper adds a schema prefix; it does not quote or validate identifiers.
     *
     * @param string $name
     * The table or object name. Names already containing a dot are returned unchanged.
     *
     * @return string
     * Returns the original or schema-qualified name.
     *
     * @see DBWorker::get_schema()
     *
     * @author Oleg Schildt
     */
    public function qualify_name_with_schema(string $name): string
    {
        // Avoid adding another prefix to an already qualified name.
        return $this->get_schema() === '' || str_contains($name, '.') ? $name : $this->get_schema() . '.' . $name;
    } // qualify_name_with_schema

    /**
     * Selects another database when the native driver supports switching.
     *
     * MySQL and SQL Server override this method to select a database. Oracle overrides it
     * to switch CURRENT_SCHEMA. PostgreSQL keeps its database for the lifetime of a connection.
     *
     * @param string $db_name
     * The database name, or the current schema name in the Oracle implementation.
     *
     * @return void
     *
     * @throws DBWorkerException
     * The default implementation throws an exception because the database must be selected when connecting.
     *
     * @author Oleg Schildt
     */
    public function use_database(string $db_name): void
    {
        // Use the driver-specific override when switching databases or schemas is supported.
        throw new DBWorkerException(
            'Select the database when connecting with this driver.',
            self::ERR_CONNECTION_DATA_INCOMPLETE
        );
    } // use_database

    /**
     * Executes an SQL query.
     *
     * Releases the previous prepared query and result. Values in this SQL are supplied by the caller;
     * use a prepared query to bind values separately. Native errors are reported as DBWorkerException.
     *
     * @param string $query_string
     * The complete SQL query to execute.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception if the connection, query or parameter validation fails.
     *
     * @see DBWorker::prepare_query()
     * @see DBWorker::fetch_row()
     * @see DBWorker::get_last_query()
     *
     * @author Oleg Schildt
     */
    public function execute_query(string $query_string): void
    {
        // Replace the old result and statement before recording and executing the new application SQL.
        $this->check_connection();

        $this->free_prepared_query();

        $this->last_query = $query_string;
        $this->last_insert_id = null;

        $this->timed(fn () => $this->native_query($query_string));

        // Schema changes may invalidate identity-column discovery cached on the shared connection.
        if (preg_match('/\A(?:CREATE|ALTER|DROP)\b/i', $this->sql_body($query_string))) {
            $this->connection_state->attributes['identity_columns'] = [];
        }
    } // execute_query

    /**
     * Prepares an SQL query with positional or named bind variables.
     *
     * The previous statement and result are released before compiling the new query.
     * The driver translates the public markers into its native bind syntax.
     *
     * @param string $query_string
     * The SQL query containing positional ? markers or named :name markers.
     * The two styles must not be mixed. Native numbered markers such as $1 are not accepted.
     * A named marker may occur more than once; SQL strings, identifiers and comments are preserved.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception if the connection, query or parameter validation fails.
     *
     * @see DBWorker::execute_prepared_query()
     * @see DBWorker::free_prepared_query()
     * @see PreparedQuery
     *
     * @author Oleg Schildt
     */
    public function prepare_query(string $query_string): void
    {
        // Compile public markers before asking the native driver to prepare its statement.
        $this->check_connection();

        $this->free_prepared_query();

        $this->last_query = $query_string;
        $this->compiled_query = PreparedQuery::compile(
            $query_string,
            $this->dialect(),
            !($this->connection_state->attributes['no_backslash_escapes'] ?? false)
        );

        try {
            $this->native_prepare($this->compiled_query->sql);
        } catch (\Throwable $error) {
            // Release partially prepared native state and keep the public exception type consistent.
            $this->free_prepared_query();

            throw $error instanceof DBWorkerException ? $error : $this->query_error($error->getMessage());
        }
    } // prepare_query

    /**
     * Executes the prepared SQL query with validated bind values.
     *
     * Example:
     * ```php
     * $dbw->prepare_query("SELECT id FROM users WHERE first_name = :name OR last_name = :name");
     * $dbw->execute_prepared_query(name: "John");
     * $dbw->free_result();
     * $dbw->execute_prepared_query(["name" => "Smith"]);
     * ```
     *
     * Missing/extra arguments and mixed styles cause an exception before execution.
     * Caller-owned streams remain open; seekable input positions are preserved.
     *
     * @param mixed ...$args
     * Positional arguments, a contiguous positional array, PHP named arguments, or one associative array.
     * The argument style must match the prepared query. Names must match exactly; their order is not important.
     * Explicit NULL values are accepted. Repeated named markers use the same supplied value.
     * Strings and scalar values are supported; wrap binary/text LOB values or streams in BlobValue/ClobValue.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception if the connection, query or parameter validation fails.
     *
     * @see DBWorker::prepare_query()
     * @see DBWorker::free_prepared_query()
     * @see BlobValue
     * @see ClobValue
     *
     * @author Oleg Schildt
     */
    public function execute_prepared_query(...$args): void
    {
        // Validate all arguments before releasing the previous result or executing any SQL.
        $this->check_connection();

        if ($this->compiled_query === null) {
            throw $this->query_error('No prepared query defined.');
        }

        $values = $this->compiled_query->arguments($args);

        $this->free_result();

        $this->last_query = $this->compiled_query->original;
        $this->last_insert_id = null;
        $temporary = [];

        try {
            // Repeated named streams must also work for non-seekable input.
            // Reuse a single spool for every occurrence of the same non-seekable source.
            $spooled = [];

            foreach ($values as &$value) {
                if (
                    ($value instanceof BlobValue || $value instanceof ClobValue) && is_resource($value->value)
                    && $this->stream_position($value->value) === null
                ) {
                    $id = get_resource_id($value->value);

                    if (!isset($spooled[$id])) {
                        $target = tmpfile();

                        if ($target === false) {
                            throw $this->stream_error('Cannot create a temporary LOB stream.');
                        }

                        $temporary[] = $target;

                        $this->write_chunks($value->value, fn (string $chunk) => $this->write_all($target, $chunk));

                        rewind($target);

                        $spooled[$id] = $target;
                    }

                    $value = $value instanceof BlobValue ? new BlobValue($spooled[$id]) : new ClobValue($spooled[$id]);
                }
            }

            unset($value);

            $this->timed(fn () => $this->native_execute($values), $values);
        } finally {
            // Only internally created temporary streams are closed here.
            foreach ($temporary as $stream) {
                fclose($stream);
            }
        }
    } // execute_prepared_query

    /**
     * Stores large data from an input stream using one unique bind parameter.
     *
     * Example:
     * ```php
     * $stream = fopen(".../large_binary.jpg", "rb");
     * try {
     *     $dbw->stream_long_data("UPDATE large_data SET blob_data = ? WHERE id = 1", $stream);
     * } finally {
     *     fclose($stream);
     * }
     * ```
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
     *
     * @throws DBWorkerException
     * It might throw an exception if the connection, query or parameter validation fails.
     * It might throw an exception if a stream is invalid or a read/write operation fails.
     *
     * @see DBWorker::copy_field_to_stream()
     * @see DBWorker::execute_prepared_query()
     *
     * @author Oleg Schildt
     */
    public function stream_long_data(string $query_string, $stream, int $type = self::DB_AS_IS): void
    {
        // Validate the caller-owned stream before preparing the single-parameter upload query.
        $this->assert_stream($stream);

        if (!in_array($type, [self::DB_AS_IS, self::DB_STRING], true)) {
            throw new DBWorkerException(
                'Stream type must be DB_AS_IS (binary) or DB_STRING (UTF-8 text).',
                self::ERR_WRONG_DATA_FORMAT
            );
        }

        $this->prepare_query($query_string);

        try {
            $value = $type === self::DB_STRING ? new ClobValue($stream) : new BlobValue($stream);
            $names = array_unique($this->compiled_query->names);

            if (count($names) !== 1) {
                throw new DBWorkerException(
                    'stream_long_data requires exactly one unique parameter.',
                    self::ERR_WRONG_DATA_FORMAT
                );
            }

            $this->execute_prepared_query($this->compiled_query->named ? [$names[0] => $value] : [$value]);
        } finally {
            $this->free_prepared_query();
        }
    } // stream_long_data

    /**
     * Executes a stored procedure using native bind parameters.
     *
     * The procedure name is validated separately from its values.
     * Temporary statement resources are released after execution. Output parameters
     * and procedure result sets are not exposed by this convenience method.
     * The driver completes any required pending results before releasing the statement.
     *
     * @param string $procedure
     * The procedure identifier, optionally qualified with a schema or package name.
     *
     * @param mixed ...$args
     * The positional input arguments of the stored procedure. No arguments are required for a parameterless procedure.
     *
     * @return void
     * Does not expose procedure rows or output parameters.
     *
     * @throws DBWorkerException
     * If the procedure name is invalid, arguments are named or invalid, no connection is open,
     * or native execution or required result completion reports a database error.
     *
     * @author Oleg Schildt
     */
    public function execute_procedure(string $procedure, ...$args): void
    {
        // Validate the identifier separately; procedure argument values are always bound.
        if (!preg_match('/\A[a-zA-Z_][a-zA-Z0-9_$#]*(?:\.[a-zA-Z_][a-zA-Z0-9_$#]*){0,2}\z/', $procedure)) {
            throw new DBWorkerException('Invalid procedure name.', self::ERR_WRONG_DATA_FORMAT);
        }

        if (!array_is_list($args)) {
            throw new DBWorkerException('Procedure arguments must be positional.', self::ERR_WRONG_DATA_FORMAT);
        }

        $parameters = implode(', ', array_fill(0, count($args), '?'));
        $sql = match ($this->dialect()) {
            'mssql' => 'EXEC ' . $procedure . ($parameters === '' ? '' : ' ' . $parameters),
            'oracle' => 'BEGIN ' . $procedure . '(' . $parameters . '); END;',
            default => 'CALL ' . $procedure . '(' . $parameters . ')'
        };

        $this->prepare_query($sql);

        try {
            $this->execute_prepared_query(...$args);

            $this->native_finish_procedure();
        } finally {
            $this->free_prepared_query();
        }
    } // execute_procedure

    /**
     * Frees the current result and its field resources while keeping the prepared query.
     *
     * Clears the current row and column metadata.
     * A retained prepared statement can be executed again with new arguments.
     *
     * @return void
     *
     * @see DBWorker::free_prepared_query()
     * @see DBWorker::execute_prepared_query()
     *
     * @author Oleg Schildt
     */
    public function free_result(): void
    {
        // Release row-owned resources before the native cursor that supplied them.
        $this->clear_row();

        $this->native_free_result();

        $this->metadata = [];
        $this->result_open = false;
    } // free_result

    /**
     * Frees the current result and prepared statement.
     *
     * Releases statement-specific native resources and removes the compiled parameter mapping.
     * The connection and its transaction remain available.
     *
     * @return void
     *
     * @see DBWorker::free_result()
     * @see DBWorker::prepare_query()
     *
     * @author Oleg Schildt
     */
    public function free_prepared_query(): void
    {
        // Release the result before freeing the statement and its parameter mapping.
        $this->free_result();

        $this->native_free_statement();

        $this->compiled_query = null;
    } // free_prepared_query

    /**
     * Starts a transaction on the shared database connection.
     *
     * Clones share the transaction state. Nested transactions are not emulated.
     *
     * @param string|null $isolation_level
     * ISOLATION_READ_COMMITTED, ISOLATION_REPEATABLE_READ or ISOLATION_SERIALIZABLE.
     * NULL selects default_isolation_level configured through init(), initially READ COMMITTED.
     * An explicit argument overrides the configured default for this transaction only.
     * Oracle rejects REPEATABLE READ. Native read-only transaction rules still apply.
     *
     * @return void
     * Records the active transaction and its chosen isolation level after native startup succeeds.
     *
     * @throws DBWorkerException
     * If no connection is open, a transaction is already active, the level is invalid or unsupported,
     * or applying the isolation level or starting the native transaction fails.
     *
     * @see DBWorker::commit_transaction()
     * @see DBWorker::rollback_transaction()
     *
     * @author Oleg Schildt
     */
    public function start_transaction(?string $isolation_level = null): void
    {
        // Check the connection-wide flag so a clone cannot start a second managed transaction.
        $this->check_connection();

        if ($this->connection_state->transaction) {
            throw $this->query_error('A transaction is already active on this connection.');
        }

        $isolation_level = strtoupper(trim($isolation_level ?? $this->default_isolation_level));

        if (!in_array($isolation_level, [self::ISOLATION_READ_COMMITTED, self::ISOLATION_REPEATABLE_READ,
            self::ISOLATION_SERIALIZABLE], true)) {
            throw new DBWorkerException('Unsupported transaction isolation level: ' . $isolation_level, self::ERR_WRONG_DATA_FORMAT);
        }

        $this->native_transaction('begin', $isolation_level);

        $this->connection_state->transaction = true;
        $this->connection_state->attributes['transaction_isolation_level'] = $isolation_level;
    } // start_transaction

    /**
     * Reports the active managed transaction's isolation level on the shared connection.
     *
     * @return string|null
     * Returns the selected isolation level, or NULL if no managed transaction is active.
     * Clones report the same connection-wide state. No connection is opened implicitly.
     *
     * @author Oleg Schildt
     */
    public function get_transaction_isolation_level(): ?string
    {
        return $this->connection_state?->transaction
            ? $this->connection_state->attributes['transaction_isolation_level'] : null;
    } // get_transaction_isolation_level

    /**
     * Commits the active transaction on the shared database connection.
     *
     * The transaction flag is cleared after native commit succeeds, before restoring session isolation.
     *
     * @return void
     * Commits the transaction and restores any temporary session isolation setting.
     *
     * @throws DBWorkerException
     * If no transaction is active, a failed save requires rollback, native commit fails or session restoration fails.
     * A restoration error does not undo an already successful commit.
     *
     * @see DBWorker::start_transaction()
     *
     * @author Oleg Schildt
     */
    public function commit_transaction(): void
    {
        // Complete the transaction through the shared-state validation path.
        $this->finish_transaction('commit');
    } // commit_transaction

    /**
     * Rolls back the active transaction on the shared database connection.
     *
     * The transaction flag is cleared after native rollback succeeds, before restoring session isolation.
     *
     * @return void
     * Rolls back the transaction and restores any temporary session isolation setting.
     *
     * @throws DBWorkerException
     * If no transaction is active, native rollback fails or restoring session isolation fails.
     * A restoration error does not make the completed transaction active again.
     *
     * @see DBWorker::start_transaction()
     *
     * @author Oleg Schildt
     */
    public function rollback_transaction(): void
    {
        // Complete the transaction through the shared-state validation path.
        $this->finish_transaction('rollback');
    } // rollback_transaction

    /**
     * Completes the active transaction after validating the shared transaction state.
     *
     * @param string $action
     * The completion operation: commit or rollback.
     *
     * @return void
     * Completes the native transaction, clears shared state and restores driver session settings.
     *
     * @throws DBWorkerException
     * If no transaction is active, a failed save prohibits commit, native completion fails or session restoration fails.
     *
     * @author Oleg Schildt
     */
    private function finish_transaction(string $action): void
    {
        // Retain the active flag until native commit or rollback has succeeded.
        $this->check_connection();

        if (!$this->connection_state->transaction) {
            throw $this->query_error('No transaction is active on this connection.');
        }

        if ($action === 'commit' && ($this->connection_state->attributes['save_records_failed'] ?? false)) {
            throw $this->query_error('Roll back the transaction after a failed save_records() call.');
        }

        $this->native_transaction($action);

        $this->connection_state->transaction = false;

        unset($this->connection_state->attributes['save_records_failed'],
            $this->connection_state->attributes['transaction_isolation_level']);

        $this->native_restore_transaction_isolation();
    } // finish_transaction

    /**
     * Initializes metadata and counters for a newly executed statement.
     *
     * @param array $metadata
     * Column metadata in the same order as the numeric values returned by native_fetch().
     *
     * @param int $affected
     * The native affected-row count, or zero if no DML count is supplied.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    protected function set_result(array $metadata, int $affected = 0): void
    {
        // Keep metadata order aligned with numeric row values.
        $this->metadata = $metadata;
        $this->affected_rows = $affected;
        $this->result_open = count($metadata) > 0;
    } // set_result

    /**
     * Fetches the next row from the active result set.
     *
     * The previous row's LOB descriptors or temporary streams are released before fetching.
     *
     * Example:
     * ```php
     * $dbw->execute_query("SELECT first_name, last_name FROM users");
     * while ($dbw->fetch_row()) {
     *     echo $dbw->field_by_name("first_name") . " " . $dbw->field_by_name("last_name");
     * }
     * $dbw->free_result();
     * ```
     *
     * @return bool
     * Returns true when a row was fetched, or false when no row is available.
     *
     * @throws DBWorkerException
     * It might throw an exception if the connection, query or parameter validation fails.
     *
     * @see DBWorker::field_by_name()
     * @see DBWorker::field_by_num()
     *
     * @author Oleg Schildt
     */
    public function fetch_row(): bool
    {
        // Release the previous row before fetching another row from the active native result.
        $this->check_connection();

        $this->clear_row();

        if (!$this->result_open) {
            return false;
        }

        $this->row = $this->native_fetch();

        if ($this->row === null) {
            return false;
        }

        return true;
    } // fetch_row

    /**
     * Fetches all remaining result rows into an array.
     *
     * By default, rows are appended to a two-dimensional array.
     * Field conversion types are inferred from column metadata, as with explicitly typed
     * RecordsetManager fields. Date/time values become Unix timestamps unless fields_as_is is true.
     * Numeric, string and binary values retain the behavior of the corresponding field accessor.
     *
     * Example:
     * ```php
     * $rows = [];
     * $dbw->fetch_array($rows, ["page_id", "language_key"]);
     * // $rows[$page_id][$language_key] = ["title" => ..., "content" => ...];
     * ```
     *
     * @param array &$rows
     * The target array. Its previous contents are replaced.
     *
     * @param string[] $dimension_keys
     * Field names used as nested array keys, in the supplied order. An empty array appends rows sequentially.
     * Each entry must be a nonempty string field name; numeric column indexes are not accepted.
     * The values read from dimension fields must be integers or strings.
     * Dimension fields are removed from leaf records.
     * Date/time dimensions use Unix timestamps unless fields_as_is is true.
     * Rows with the same complete dimension key overwrite the previous leaf.
     * Invalid entry types are rejected before output is replaced or any row is fetched.
     *
     * @param bool $fields_as_is
     * True reads every field with DB_AS_IS, preserving the driver's decoded values.
     * False selects DB_DATETIME, DB_NUMBER or DB_STRING from column metadata and uses DB_AS_IS
     * for unclassified fields. NULL values remain NULL in both modes.
     *
     * @return int
     * Returns the number of fetched rows, including zero. Errors are reported by exceptions.
     *
     * @throws DBWorkerException
     * If the connection is not open, fetching or LOB decoding fails, a date/time value cannot be parsed,
     * a dimension key is not a nonempty string, or a dimension field is unknown or has a value
     * other than an integer or string.
     *
     * @author Oleg Schildt
     */
    public function fetch_array(array &$rows, array $dimension_keys = [], bool $fields_as_is = false): int
    {
        // Reject column indexes and invalid names before changing output or consuming result rows.
        foreach ($dimension_keys as $key) {
            if (!is_string($key) || $key === '') {
                throw new DBWorkerException(
                    'Dimension keys must be nonempty field names.',
                    self::ERR_WRONG_DATA_FORMAT
                );
            }
        }

        // Build output records through the public field accessors so native LOB decoding remains consistent.
        $rows = [];
        $count = 0;

        while ($this->fetch_row()) {
            $record = [];

            foreach ($this->metadata as $index => $field) {
                $type = $fields_as_is ? self::DB_AS_IS : match (true) {
                    !empty($field['datetime']) => self::DB_DATETIME,
                    !empty($field['numeric']) => self::DB_NUMBER,
                    !empty($field['string']) => self::DB_STRING,
                    default => self::DB_AS_IS
                };
                $record[$field['name']] = $this->field_by_num($index, $type);
            }

            if ($dimension_keys === []) {
                $rows[] = $record;
            } else {
                // Follow the requested dimension order, which may differ from SELECT column order.
                $target = &$rows;

                foreach ($dimension_keys as $key) {
                    if (!array_key_exists($key, $record)) {
                        throw new DBWorkerException('Unknown dimension column: ' . $key, self::ERR_WRONG_DATA_FORMAT);
                    }

                    $dimension = $record[$key];

                    if (!is_int($dimension) && !is_string($dimension)) {
                        throw new DBWorkerException(
                            'Dimension values must be integers or strings.',
                            self::ERR_WRONG_DATA_FORMAT
                        );
                    }

                    $target[$dimension] ??= [];
                    $target = &$target[$dimension];
                }

                // Repeated complete dimension keys intentionally replace the existing leaf record.
                $target = array_diff_key($record, array_fill_keys($dimension_keys, true));
                unset($target);
            }

            $count++;
        }

        return $count;
    } // fetch_array

    /**
     * Executes an SQL query, fetches its rows into an array and releases the result.
     *
     * Uses the selected field conversion mode of fetch_array().
     * The result is released even if execution or fetching throws an exception.
     *
     * @param array &$rows
     * The target array. Its previous contents are replaced when fetching begins.
     * Without dimensions, each element is a field-value array; otherwise records are nested by dimension values.
     *
     * @param string[] $dimension_keys
     * Nonempty field names used as nested array keys, in order. Pass [] for sequential rows.
     * Dimension values must be integers or strings. Dimension fields are removed from leaf records,
     * and rows with the same complete dimension key overwrite the previous leaf.
     *
     * @param bool $fields_as_is
     * True preserves the driver's decoded field values using DB_AS_IS. False applies metadata-based
     * conversions, including Unix timestamps for date/time fields and dimensions. NULL values remain NULL.
     *
     * @param string $query_string
     * The complete SQL query to execute. Use prepared_query_to_array() to bind values separately.
     *
     * @return int
     * Returns the number of fetched rows, including overwritten leaves, or zero for an empty result.
     *
     * @throws DBWorkerException
     * If the connection is not open, SQL execution, fetching, LOB decoding or date/time conversion fails,
     * or a dimension name or value is invalid or references an unknown field.
     *
     * @see DBWorker::fetch_array()
     * @see DBWorker::prepared_query_to_array()
     *
     * @author Oleg Schildt
     */
    public function query_to_array(
        array &$rows,
        array $dimension_keys,
        bool $fields_as_is,
        string $query_string
    ): int
    {
        try {
            $this->execute_query($query_string);

            return $this->fetch_array($rows, $dimension_keys, $fields_as_is);
        } finally {
            $this->free_result();
        }
    } // query_to_array

    /**
     * Executes the prepared query, fetches its rows and releases the result and prepared statement.
     *
     * Call prepare_query() first. Uses the selected field conversion mode of fetch_array().
     * The result and statement are released even if execution or fetching throws an exception.
     * Prepare a new query before calling this method again.
     *
     * @param array &$rows
     * The target array. Its previous contents are replaced when fetching begins.
     * Without dimensions, each element is a field-value array; otherwise records are nested by dimension values.
     *
     * @param string[] $dimension_keys
     * Nonempty field names used as nested array keys, in order. Pass [] for sequential rows.
     * Dimension values must be integers or strings. Dimension fields are removed from leaf records,
     * and rows with the same complete dimension key overwrite the previous leaf.
     *
     * @param bool $fields_as_is
     * True preserves the driver's decoded field values using DB_AS_IS. False applies metadata-based
     * conversions, including Unix timestamps for date/time fields and dimensions. NULL values remain NULL.
     *
     * @param mixed ...$args
     * Bind values forwarded unchanged to execute_prepared_query(): positional arguments, a contiguous
     * positional array, PHP named arguments, or one associative array. The style and names must match
     * the prepared query. NULL and scalar values are accepted; wrap LOB values or streams in BlobValue/ClobValue.
     * Use an associative array for bind names that collide with this method's rows, dimension_keys
     * or fields_as_is parameters.
     *
     * @return int
     * Returns the number of fetched rows, including overwritten leaves, or zero for an empty result.
     *
     * @throws DBWorkerException
     * If the connection is not open, no query is prepared, bind validation, SQL execution, fetching,
     * LOB decoding or date/time conversion fails, or a dimension name or value is invalid or references an unknown field.
     *
     * @see DBWorker::prepare_query()
     * @see DBWorker::execute_prepared_query()
     * @see DBWorker::fetch_array()
     *
     * @author Oleg Schildt
     */
    public function prepared_query_to_array(
        array &$rows,
        array $dimension_keys,
        bool $fields_as_is,
        mixed ...$args
    ): int
    {
        try {
            $this->execute_prepared_query(...$args);

            return $this->fetch_array($rows, $dimension_keys, $fields_as_is);
        } finally {
            // free_prepared_query() also releases the current result.
            $this->free_prepared_query();
        }
    } // prepared_query_to_array

    /**
     * Inserts or updates a single record or records indexed by configured array dimensions.
     *
     * Dimension keys supply the corresponding field values and take precedence over leaf fields.
     * Only configured fields present in a record or its dimensions are written. Missing fields
     * retain their database defaults on INSERT and their current values on UPDATE. Empty strings
     * become SQL NULL, matching prepare_for_query(). All record values use bound parameters.
     * A supplied integer identity is preserved on INSERT. Otherwise its generated value replaces
     * the corresponding array dimension, or is stored in the leaf when it is not a dimension.
     * Every record must supply at least one configured field other than the identity column.
     * Known keys are serialized through dbworker_locks (see: [Concurrent record saving](markdown:SavingRecords.md)) until the transaction ends. The method owns
     * a transaction when none is active; otherwise the caller retains commit and rollback control.
     * On failure the supplied array is unchanged and a caller transaction must be rolled back.
     *
     * @param array &$records
     * A field-value array when array_dimension_keys is empty; otherwise nested arrays whose keys
     * represent those dimensions in order and whose leaves contain field-value arrays.
     * Identities are written back after the complete call succeeds. Other data is preserved.
     *
     * @param array{table: string, fields: array<string, int>, array_dimension_keys: list<string>, record_key_fields: list<string>, identity_field?: string} $table_config
     * The table name, nonempty field/type map, array dimensions, existence-check fields, and optional
     * auto-increment field name (empty means none). Names must be unquoted SQL identifiers; the
     * table may be schema-qualified. Dimension, record-key and identity fields must occur in fields.
     * Empty record_key_fields always inserts. Otherwise every key must be supplied, except that
     * a missing or empty identity value requests INSERT. NULL key values are matched with IS NULL.
     * Dates accept Unix timestamps, integer timestamp strings, DateTimeInterface objects or NULL.
     *
     * @return void
     * Updates the supplied array with generated identities and releases temporary prepared queries.
     *
     * @throws DBWorkerException
     * If configuration, record structure or field values are invalid, a record key is missing,
     * a record has no configured fields to write after excluding the identity column,
     * a database operation fails, an INSERT returns no configured identity, or reindexing would
     * overwrite an existing record. A connection must already be open.
     *
     * @link markdown:SavingRecords.md Concurrent record saving
     *
     * @author Oleg Schildt
     */
    public function save_records(array &$records, array $table_config): void
    {
        $table = $table_config['table'] ?? null;
        $fields = $table_config['fields'] ?? null;
        $dimension_keys = $table_config['array_dimension_keys'] ?? null;
        $record_keys = $table_config['record_key_fields'] ?? null;
        $identity_field = $table_config['identity_field'] ?? '';
        $identifier = '[a-zA-Z_][a-zA-Z0-9_$#]*';

        if (
            !is_string($table) || !preg_match('/\A' . $identifier . '(?:\.' . $identifier . '){0,2}\z/', $table)
            || !is_array($fields) || $fields === [] || !is_array($dimension_keys)
            || !array_is_list($dimension_keys) || !is_array($record_keys) || !array_is_list($record_keys)
            || !is_string($identity_field)
        ) {
            throw new DBWorkerException('Invalid save_records table configuration.', self::ERR_WRONG_DATA_FORMAT);
        }

        foreach ($fields as $field => $type) {
            if (
                !is_string($field) || !preg_match('/\A' . $identifier . '\z/', $field)
                || !in_array($type, [self::DB_AS_IS, self::DB_NUMBER, self::DB_STRING, self::DB_DATE,
                    self::DB_DATETIME, self::DB_GEOMETRY, self::DB_GEOMETRY_4326, self::DB_LARGE_OBJECT_STREAM], true)
            ) {
                throw new DBWorkerException('Invalid save_records field definition.', self::ERR_WRONG_DATA_FORMAT);
            }
        }

        foreach ([$dimension_keys, $record_keys, $identity_field === '' ? [] : [$identity_field]] as $names) {
            foreach ($names as $name) {
                if (!is_string($name) || !array_key_exists($name, $fields)) {
                    throw new DBWorkerException('Unknown save_records configuration field.', self::ERR_WRONG_DATA_FORMAT);
                }
            }

            if (count(array_unique($names)) !== count($names)) {
                throw new DBWorkerException('Duplicate save_records configuration field.', self::ERR_WRONG_DATA_FORMAT);
            }
        }

        $this->check_connection();

        // Empty nested inputs must not acquire a connection-level transaction or query metadata.
        if ($dimension_keys !== [] && $records === []) {
            return;
        }

        $this->native_save_check();

        $own_transaction = !$this->connection_state->transaction;
        $working = $records;
        $context = null;
        $this->last_insert_id = null;

        if ($own_transaction) {
            // The save-lock protocol requires fresh statement snapshots regardless of the application's default.
            $this->start_transaction(self::ISOLATION_READ_COMMITTED);
        }

        try {
            // Traverse a snapshot so generated keys cannot cause records to be visited twice.
            foreach ($this->process_subarray($working, $dimension_keys) as [$dimensions, $record]) {
                $values = [];
                $expressions = [];

                foreach (array_replace($record, $dimensions) as $field => $value) {
                    if (!array_key_exists($field, $fields)) {
                        continue;
                    }

                    $type = $fields[$field];
                    $values[$field] = $this->record_bind_value($value, $type);
                    $expressions[$field] = match ($type) {
                        self::DB_GEOMETRY => 'ST_GeomFromText(?)',
                        self::DB_GEOMETRY_4326 => "ST_GeomFromText(?, 4326, 'axis-order=lat-long')",
                        default => '?'
                    };
                }

                $where = [];
                $key_values = [];
                $must_insert = $record_keys === []
                    || ($identity_field !== '' && in_array($identity_field, $record_keys, true)
                        && ($values[$identity_field] ?? null) === null);

                foreach ($record_keys as $field) {
                    if (!array_key_exists($field, $values)) {
                        if ($field === $identity_field) {
                            continue;
                        }

                        throw new DBWorkerException('Missing record key: ' . $field, self::ERR_WRONG_DATA_FORMAT);
                    }

                    $where[] = $field . ($values[$field] === null ? ' IS NULL' : ' = ' . $expressions[$field]);

                    if ($values[$field] !== null) {
                        $key_values[] = $values[$field];
                    }
                }

                $explicit_identity = $identity_field !== '' && ($values[$identity_field] ?? null) !== null
                    ? $this->save_identity_value($values[$identity_field]) : null;
                $write_values = $values;
                $write_expressions = $expressions;

                unset($write_values[$identity_field], $write_expressions[$identity_field]);

                if ($write_values === []) {
                    throw new DBWorkerException(
                        'A record must contain at least one configured field other than the identity field.',
                        self::ERR_WRONG_DATA_FORMAT
                    );
                }

                $context ??= $this->native_save_table($table);

                if ($identity_field !== '' && !$this->save_column($context, $identity_field)['identity']) {
                    throw new DBWorkerException('The configured identity field is not generated by the database.', self::ERR_WRONG_DATA_FORMAT);
                }

                // Compare keys after the same native conversions used to derive their service resource.
                $where = [];
                $key_values = [];

                foreach ($record_keys as $field) {
                    if (!array_key_exists($field, $values)) {
                        continue;
                    }

                    $column = $this->save_column($context, $field);
                    $where[] = $field . ($values[$field] === null ? ' IS NULL' : ' = ' . $column['cast']);

                    if ($values[$field] !== null) {
                        $key_values[] = $values[$field];
                    }
                }

                if (!$must_insert) {
                    $lock_keys = [];

                    foreach ($record_keys as $field) {
                        $lock_keys[strtolower($field)] = $values[$field];
                    }

                    [$resource_key, $resource_name] = $this->save_resource($context, $lock_keys);

                    $this->native_save_lock($context, $resource_key, $resource_name);

                    $must_insert = !$this->native_save_exists($context, implode(' AND ', $where), $key_values);
                }

                if ($must_insert) {
                    if ($explicit_identity !== null) {
                        $write_values[$identity_field] = $explicit_identity;
                        $write_expressions[$identity_field] = '?';
                    }

                    $identity = $this->native_save_insert($context, $write_values, $write_expressions, $identity_field);
                    $this->last_insert_id = $identity;
                } else {
                    $assignments = [];

                    foreach ($write_expressions as $field => $expression) {
                        $assignments[] = $field . ' = ' . $expression;
                    }

                    $query = 'UPDATE ' . $context['table'] . ' SET ' . implode(', ', $assignments)
                        . ' WHERE ' . implode(' AND ', $where);

                    $result = $this->save_sql($query, array_merge(array_values($write_values), $key_values));
                    $this->affected_rows = $result['affected'];
                    $this->last_insert_id = null;

                    continue;
                }

                if ($identity_field === '') {
                    continue;
                }

                if ($identity === null) {
                    throw $this->query_error('INSERT did not return the configured identity field: ' . $identity_field);
                }

                $source = &$working;
                $parents = [];

                foreach ($dimensions as $dimension) {
                    $parents[] = &$source;
                    $source = &$source[$dimension];
                }

                if (!array_key_exists($identity_field, $dimensions) || array_key_exists($identity_field, $source)) {
                    $source[$identity_field] = $identity;
                }

                if (array_key_exists($identity_field, $dimensions) && (string) $dimensions[$identity_field] !== (string) $identity) {
                    $new_dimensions = array_replace($dimensions, [$identity_field => $identity]);
                    $target = &$working;
                    $last_dimension = array_key_last($new_dimensions);

                    foreach ($new_dimensions as $field => $dimension) {
                        if (array_key_exists($dimension, $target) && ($field === $last_dimension || !is_array($target[$dimension]))) {
                            throw new DBWorkerException('Generated identity would overwrite an existing record.', self::ERR_WRONG_DATA_FORMAT);
                        }

                        $target[$dimension] ??= [];
                        $target = &$target[$dimension];
                    }

                    $target = $source;
                    $old_path = array_values($dimensions);

                    unset($parents[count($old_path) - 1][$old_path[count($old_path) - 1]]);

                    // Remove empty ancestors after moving a leaf, including identities in outer dimensions.
                    for ($index = count($old_path) - 2; $index >= 0; $index--) {
                        if ($parents[$index][$old_path[$index]] !== []) {
                            break;
                        }

                        unset($parents[$index][$old_path[$index]]);
                    }

                    unset($target);
                }

                unset($source, $parents);
            }

            if ($own_transaction) {
                $this->commit_transaction();
            }

            $records = $working;
        } catch (\Throwable $error) {
            $this->last_insert_id = null;
            $this->affected_rows = 0;

            if ($own_transaction) {
                try {
                    $this->rollback_transaction();
                } catch (\Throwable) {
                    // Preserve the original failure and require explicit connection recovery.
                    $this->connection_state->attributes['save_records_failed'] = true;
                }
            } else {
                $this->connection_state->attributes['save_records_failed'] = true;
            }

            throw $error;
        }
    } // save_records

    /**
     * Locks a logical record key until the caller's transaction ends, without writing its data.
     *
     * Uses the same database-normalized service-row lock as save_records(), including
     * keys whose target records do not yet exist. Call this before reading and merging
     * a settings aggregate. All cooperating writers must use the same table and key fields.
     *
     * @param string $table
     * An unquoted, optionally schema-qualified table name.
     *
     * @param array<string, scalar|null> $key_values
     * A nonempty map of key columns to bound values. Empty strings become NULL,
     * matching save_records(). Dates must already be formatted for binding.
     *
     * @return bool
     * Returns whether a matching record exists after acquiring the lock.
     * Does not replace the public prepared query or result.
     *
     * @throws DBWorkerException
     * If names or values are invalid, no transaction is active, the worker is read-only,
     * the isolation level is unsupported, or resolving, locking or querying the key fails.
     * After a database failure the caller must roll back the transaction.
     *
     * @throws \Throwable
     * If a configured query logger throws while resolving, locking or checking the record key.
     *
     * @author Oleg Schildt
     */
    public function lock_record(string $table, array $key_values): bool
    {
        $identifier = '[a-zA-Z_][a-zA-Z0-9_$#]*';

        if (!preg_match('/\A' . $identifier . '(?:\.' . $identifier . '){0,2}\z/', $table)
            || $key_values === []) {
            throw new DBWorkerException('Invalid record lock configuration.', self::ERR_WRONG_DATA_FORMAT);
        }

        $keys = [];

        foreach ($key_values as $field => $value) {
            if (!is_string($field) || !preg_match('/\A' . $identifier . '\z/', $field)
                || (!is_scalar($value) && $value !== null)
                || (is_float($value) && !is_finite($value))
                || array_key_exists(strtolower($field), $keys)) {
                throw new DBWorkerException('Invalid record lock key.', self::ERR_WRONG_DATA_FORMAT);
            }

            $keys[strtolower($field)] = $value === '' ? null : $value;
        }

        $this->check_connection();

        if ($this->get_transaction_isolation_level() === null) {
            throw $this->query_error('lock_record() requires an active managed transaction.');
        }

        $this->native_save_check();

        try {
            $context = $this->native_save_table($table);
            $where = [];
            $arguments = [];

            foreach ($keys as $field => $value) {
                $column = $this->save_column($context, $field);
                $where[] = $field . ($value === null ? ' IS NULL' : ' = ' . $column['cast']);

                if ($value !== null) {
                    $arguments[] = $value;
                }
            }

            [$resource_key, $resource_name] = $this->save_resource($context, $keys);

            $this->native_save_lock($context, $resource_key, $resource_name);

            return $this->native_save_exists($context, implode(' AND ', $where), $arguments);
        } catch (\Throwable $exception) {
            $this->connection_state->attributes['save_records_failed'] = true;

            throw $exception;
        }
    } // lock_record

    /**
     * Yields leaf records together with the field values represented by their array dimensions.
     *
     * @param array $records
     * The record or remaining nested record arrays, read as a snapshot.
     *
     * @param list<string> $dimension_keys
     * The remaining dimension field names in traversal order.
     *
     * @param array<string, int|string> $dimensions
     * The dimension field values collected from parent array keys.
     *
     * @return \Generator<int, array{array<string, int|string>, array}>
     * Yields the collected dimensions and each leaf record exactly once.
     *
     * @throws DBWorkerException
     * If a dimension branch or leaf record is not an array.
     *
     * @author Oleg Schildt
     */
    protected function process_subarray(array $records, array $dimension_keys, array $dimensions = []): \Generator
    {
        if ($dimension_keys === []) {
            yield [$dimensions, $records];

            return;
        }

        $field = array_shift($dimension_keys);

        foreach ($records as $key => $subarray) {
            if (!is_array($subarray)) {
                throw new DBWorkerException('Record dimensions must contain arrays.', self::ERR_WRONG_DATA_FORMAT);
            }

            yield from $this->process_subarray($subarray, $dimension_keys, array_replace($dimensions, [$field => $key]));
        }
    } // process_subarray

    /**
     * Converts a configured record field to an unquoted bind value.
     *
     * @param mixed $value
     * A scalar, NULL, a date/time object for date fields, or a BlobValue/ClobValue for DB_AS_IS.
     * Empty strings become NULL. Date strings must contain an integer Unix timestamp.
     *
     * @param int $type
     * The validated DBWorker field type. Geometry requires the MySQL dialect.
     *
     * @return scalar|BlobValue|ClobValue|null
     * Returns a bind value; numeric strings retain their precision and dates are formatted for binding.
     *
     * @throws DBWorkerException
     * If a value has an unsupported type, a number is invalid or non-finite, a date is not a timestamp
     * or date/time object, or a geometry field is used with a driver other than MySQL.
     *
     * @author Oleg Schildt
     */
    protected function record_bind_value(mixed $value, int $type): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($type === self::DB_DATE || $type === self::DB_DATETIME) {
            if (is_string($value) && preg_match('/\A-?\d+\z/', $value)) {
                $value = filter_var($value, FILTER_VALIDATE_INT);
            }

            if (!is_int($value) && !$value instanceof \DateTimeInterface) {
                throw new DBWorkerException('Date fields require an integer timestamp or date/time object.', self::ERR_WRONG_DATA_FORMAT);
            }

            return $type === self::DB_DATE ? $this->format_date_bind($value) : $this->format_datetime_bind($value);
        }

        if ($type === self::DB_AS_IS && ($value instanceof BlobValue || $value instanceof ClobValue)) {
            return $value;
        }

        if (!is_scalar($value) || (is_float($value) && !is_finite($value))) {
            throw new DBWorkerException('Unsupported record field value.', self::ERR_WRONG_DATA_FORMAT);
        }

        if ($type === self::DB_NUMBER && !is_numeric($value)) {
            throw new DBWorkerException('Value is not a number.', self::ERR_WRONG_DATA_FORMAT);
        }

        if (in_array($type, [self::DB_GEOMETRY, self::DB_GEOMETRY_4326], true) && $this->dialect() !== 'mysql') {
            throw new DBWorkerException('Geometry is not supported by this driver.', self::ERR_WRONG_DATA_FORMAT);
        }

        return $value;
    } // record_bind_value

    /**
     * Returns the row count reported for the last data modification statement.
     *
     * @return int
     * Returns the native affected-row count. A native value of -1 means that the count is unknown.
     *
     * @throws DBWorkerException
     * It might throw an exception if the connection is not open.
     *
     * @author Oleg Schildt
     */
    public function affected_count(): int
    {
        // Return the DML count saved during execution instead of querying a consumed result.
        $this->check_connection();

        return $this->affected_rows;
    } // affected_count

    /**
     * Returns the number of columns in the current result metadata.
     *
     * @return int
     * Returns the number of columns, or zero when no result metadata is available.
     *
     * @author Oleg Schildt
     */
    public function field_count(): int
    {
        // Metadata remains the authoritative column list for the active result.
        return count($this->metadata);
    } // field_count

    /**
     * Returns the generated integer identifier captured from the last INSERT.
     *
     * Identifiers are captured from the actual INSERT using the native insert ID, a same-batch identity query
     * or automatic RETURNING handling. Explicit output clauses remain under caller control.
     * The method does not query a session-wide last sequence value. UUID and composite identifiers
     * are outside this integer-returning API.
     *
     * @return int|null
     * Returns the generated identifier, or NULL when no identifier was captured.
     *
     * @throws DBWorkerException
     * It might throw an exception if the connection is not open.
     *
     * @author Oleg Schildt
     */
    public function insert_id(): ?int
    {
        // Return the identifier captured during INSERT rather than running a session-wide identity query.
        $this->check_connection();

        return $this->last_insert_id;
    } // insert_id

    /**
     * Returns metadata for a field as an associative array.
     *
     * @param int $num
     * The zero-based field index.
     *
     * @return array|null
     * Returns field metadata, or NULL if the index is not present.
     *
     * - $info["name"] - field name.
     * - $info["type"] - native type name.
     * - $info["size"] - native field size, if available.
     * - $info["binary"] - whether the field contains binary data.
     * - $info["numeric"] - whether the field has a numeric type.
     * - $info["datetime"] - whether the field has a date/time type.
     * - $info["string"] - whether the field has a text type.
     *
     * Drivers may also provide precision and scale. Classification flags use integer values 0 or 1.
     *
     * @see DBWorker::field_by_num()
     * @see DBWorker::field_name()
     *
     * @author Oleg Schildt
     */
    public function field_info_by_num(int $num): ?array
    {
        // An out-of-range metadata lookup has no field description to return.
        return $this->metadata[$num] ?? null;
    } // field_info_by_num

    /**
     * Returns the name of a field by its position.
     *
     * @param int $num
     * The zero-based field index.
     *
     * @return string|null
     * Returns the field name, or NULL if the index is not present.
     *
     * @see DBWorker::field_info_by_num()
     *
     * @author Oleg Schildt
     */
    public function field_name(int $num): ?string
    {
        // Read the name from metadata without fetching or advancing a row.
        return $this->metadata[$num]['name'] ?? null;
    } // field_name

    /**
     * Returns a value from the current row by field name.
     *
     * An unknown or ambiguous field name causes an exception.
     * Use copy_field_to_stream() to copy a large value to a caller-owned stream.
     *
     * @param string $name
     * The field name. An exact match is preferred; a unique case-insensitive match is also accepted.
     *
     * @param int $type
     * The requested DBWorker conversion type. DB_DATE/DB_DATETIME return Unix timestamps.
     * The legacy DB_LARGE_OBJECT_STREAM flag returns the complete LOB contents as a string, not a resource.
     *
     * @return mixed
     * Returns the field value, or NULL when no current row is available.
     *
     * @throws DBWorkerException
     * It might throw an exception if the connection, query or parameter validation fails.
     *
     * @see DBWorker::field_by_num()
     * @see DBWorker::copy_field_to_stream()
     *
     * @author Oleg Schildt
     */
    public function field_by_name(string $name, int $type = self::DB_AS_IS): mixed
    {
        // Resolve a unique column first, then reuse positional decoding and conversion.
        return $this->field_by_num($this->field_index($name), $type);
    } // field_by_name

    /**
     * Returns a value from the current row by its position.
     *
     * An invalid index in an existing row or an invalid date value causes an exception.
     *
     * @param int $num
     * The zero-based field index. Positional access preserves columns with duplicate names.
     *
     * @param int $type
     * The requested DBWorker conversion type. DB_DATE/DB_DATETIME return Unix timestamps.
     * The legacy DB_LARGE_OBJECT_STREAM flag returns complete LOB contents as a string.
     *
     * @return mixed
     * Returns the field value, or NULL when no current row is available.
     *
     * @throws DBWorkerException
     * It might throw an exception if the connection, query or parameter validation fails.
     *
     * @see DBWorker::field_by_name()
     * @see DBWorker::copy_field_to_stream()
     *
     * @author Oleg Schildt
     */
    public function field_by_num(int $num, int $type = self::DB_AS_IS): mixed
    {
        // Decode the native value before applying an optional date/time conversion.
        $this->check_connection();

        if ($this->row === null) {
            return null;
        }

        if (!array_key_exists($num, $this->row)) {
            throw new DBWorkerException('Unknown field index: ' . $num, self::ERR_WRONG_DATA_FORMAT);
        }

        $value = $this->decode_value($num, $type);

        if ($value !== null && $value !== '' && ($type === self::DB_DATE || $type === self::DB_DATETIME)) {
            $date = strtotime((string) $value);

            if ($date === false) {
                throw new DBWorkerException('Invalid database date value.', self::ERR_WRONG_DATA_FORMAT);
            }

            return $date;
        }

        return $value;
    } // field_by_num

    /**
     * Resolves a field name to an unambiguous numeric result index.
     *
     * @param string $name
     * The field name. Exact matches take precedence over case-insensitive matches.
     *
     * @return int
     * Returns the zero-based field index.
     *
     * @throws DBWorkerException
     * It might throw an exception if the name is missing or ambiguous.
     *
     * @author Oleg Schildt
     */
    protected function field_index(string $name): int
    {
        // Prefer exact names so distinct quoted aliases are not merged by a case-insensitive lookup.
        $exact = [];

        foreach ($this->metadata as $index => $field) {
            if ($field['name'] === $name) {
                $exact[] = $index;
            }
        }

        if (count($exact) === 1) {
            return $exact[0];
        }

        if (count($exact) > 1) {
            throw new DBWorkerException('Ambiguous field name: ' . $name, self::ERR_WRONG_DATA_FORMAT);
        }

        // Fall back to case-insensitive matching only when it resolves to exactly one column.
        $matches = [];

        foreach ($this->metadata as $index => $field) {
            if (strcasecmp($field['name'], $name) === 0) {
                $matches[] = $index;
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        throw new DBWorkerException('Unknown or ambiguous field name: ' . $name, self::ERR_WRONG_DATA_FORMAT);
    } // field_index

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
     * Returns the native scalar value or complete contents of a streamed field.
     *
     * @throws DBWorkerException
     * It might throw an exception if a stream is invalid or a read/write operation fails.
     *
     * @author Oleg Schildt
     */
    protected function decode_value(int $index, int $type): mixed
    {
        // Materialize a field only when the caller requests its complete value.
        $value = $this->row[$index];

        if (is_resource($value)) {
            rewind($value);

            $contents = stream_get_contents($value);

            if ($contents === false) {
                throw $this->stream_error('Cannot read result stream.');
            }

            return $contents;
        }

        return $value;
    } // decode_value

    /**
     * Copies a field from the current row to a caller-owned output stream.
     *
     * Repeated copies of the same field are supported. Drivers may use native LOB descriptors,
     * temporary files or a value already materialized by the native client. mysqli retrieves
     * a complete BLOB field before this copy, so it still requires memory for the row.
     *
     * @param string|int $field
     * The field name or zero-based field index.
     *
     * @param resource $stream
     * An open writable destination stream. The caller retains ownership and must close it.
     *
     * @return int
     * Returns the number of bytes written. SQL NULL produces zero bytes.
     *
     * @throws DBWorkerException
     * It might throw an exception if the connection, query or parameter validation fails.
     * It might throw an exception if a stream is invalid or a read/write operation fails.
     *
     * @see DBWorker::field_by_name()
     * @see DBWorker::stream_long_data()
     *
     * @author Oleg Schildt
     */
    public function copy_field_to_stream(string|int $field, $stream): int
    {
        // Validate both the current field and the caller-owned destination before copying.
        $this->check_connection();

        $this->assert_stream($stream);

        $index = is_int($field) ? $field : $this->field_index($field);

        if ($this->row === null || !array_key_exists($index, $this->row)) {
            throw $this->stream_error('There is no current field to copy.');
        }

        return $this->copy_value_to_stream($index, $stream);
    } // copy_field_to_stream

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
     * It might throw an exception if a stream is invalid or a read/write operation fails.
     *
     * @author Oleg Schildt
     */
    protected function copy_value_to_stream(int $index, $stream): int
    {
        // Copy bytes in chunks and count successful writes without closing the destination.
        $value = $this->row[$index];

        if ($value === null) {
            return 0;
        }

        if (is_resource($value)) {
            rewind($value);
        }

        $count = 0;

        $this->write_chunks($value, function (string $chunk) use ($stream, &$count): void {
            $count += $this->write_all($stream, $chunk);
        });

        return $count;
    } // copy_value_to_stream

    /**
     * Releases LOB descriptors and temporary streams owned by the current row.
     *
     * The current row is cleared after its resources have been released.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    protected function clear_row(): void
    {
        // Close only resources owned by the row, then discard its numeric values.
        if ($this->row !== null) {
            foreach ($this->row as $value) {
                if (is_resource($value)) {
                    fclose($value);
                } elseif ($value instanceof \OCILob) {
                    $value->free();
                }
            }
        }

        $this->row = null;
    } // clear_row

    /**
     * Checks whether a value is an open PHP stream.
     *
     * @param resource $stream
     * The stream to validate.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception if the value is not an open stream resource.
     *
     * @author Oleg Schildt
     */
    protected function assert_stream($stream): void
    {
        // Distinguish an open PHP stream from unrelated or already closed resources.
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw $this->stream_error('An open stream is required.');
        }
    } // assert_stream

    /**
     * Checks whether a stream can actually seek to its current position.
     *
     * User-space wrappers may report seekable metadata while rejecting seek operations.
     *
     * @param resource $stream
     * The open input stream to inspect.
     *
     * @return int|null
     * Returns its byte position if seeking succeeds, or NULL if it cannot seek.
     *
     * @author Oleg Schildt
     */
    protected function stream_position($stream): ?int
    {
        // Probe a real seek because wrapper metadata alone may report a capability it cannot perform.
        if (!stream_get_meta_data($stream)['seekable']) {
            return null;
        }

        $position = @ftell($stream);

        return $position !== false && @fseek($stream, $position) === 0 ? $position : null;
    } // stream_position

    /**
     * Reads a string or stream in chunks and passes each chunk to a writer callback.
     *
     * Non-seekable streams are consumed but not closed. UTF-8 validation rejects invalid
     * characters and incomplete final sequences. Reads that make no progress cause an exception.
     *
     * @param string|resource $source
     * The source string or open readable stream. A seekable stream starts and finishes at its current position.
     *
     * @param callable $write
     * The callback accepting one nonempty string chunk and writing or processing it.
     *
     * @param bool $utf8
     * True validates UTF-8 and preserves complete character boundaries between chunks.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception if a stream is invalid or a read/write operation fails.
     * The writer callback may also propagate an exception.
     *
     * @author Oleg Schildt
     */
    protected function write_chunks(mixed $source, callable $write, bool $utf8 = false): void
    {
        // Remember a usable input position so repeated named LOB occurrences can reread the same bytes.
        $position = null;

        if (is_resource($source)) {
            $this->assert_stream($source);

            $position = $this->stream_position($source);
        } elseif (!is_string($source)) {
            throw $this->stream_error('LOB data must be a string or stream.');
        }

        $offset = 0;
        // Carry an incomplete UTF-8 character into the next chunk instead of splitting it.
        $carry = '';

        try {
            while (is_resource($source) ? !feof($source) : $offset < strlen($source)) {
                $chunk = is_resource($source) ? @fread($source, 65536) : substr($source, $offset, 65536);

                if ($chunk === false || ($chunk === '' && is_resource($source) && !feof($source))) {
                    throw $this->stream_error('Error reading from input stream.');
                }

                $offset += strlen($chunk);
                $chunk = $carry . $chunk;
                $carry = '';

                if ($utf8 && $chunk !== '' && !mb_check_encoding($chunk, 'UTF-8')) {
                    for ($tail = 1; $tail <= 3 && $tail <= strlen($chunk); $tail++) {
                        if (mb_check_encoding(substr($chunk, 0, -$tail), 'UTF-8')) {
                            $carry = substr($chunk, -$tail);
                            $chunk = substr($chunk, 0, -$tail);
                            break;
                        }
                    }

                    if (!mb_check_encoding($chunk, 'UTF-8')) {
                        throw $this->stream_error('CLOB input is not valid UTF-8.');
                    }
                }

                if ($chunk !== '') {
                    $write($chunk);
                }
            }

            // A final partial character is malformed input, not a successful short read.
            if ($carry !== '') {
                throw $this->stream_error('CLOB input ends with an incomplete UTF-8 character.');
            }
        } finally {
            // Restore seekable inputs even when a reader or writer callback throws.
            if ($position !== null && $position !== false && is_resource($source)) {
                fseek($source, $position);
            }
        }
    } // write_chunks

    /**
     * Writes every byte of a string, including when fwrite() performs a partial write.
     *
     * @param resource $stream
     * The open writable output stream.
     *
     * @param string $data
     * The bytes to write.
     *
     * @return int
     * Returns the number of bytes written.
     *
     * @throws DBWorkerException
     * It might throw an exception if a stream is invalid or a read/write operation fails.
     *
     * @author Oleg Schildt
     */
    protected function write_all($stream, string $data): int
    {
        // Advance only by bytes actually written; a zero-length write must not cause an endless loop.
        $offset = 0;

        while ($offset < strlen($data)) {
            $written = @fwrite($stream, substr($data, $offset));

            if ($written === false || $written === 0) {
                throw $this->stream_error('Error writing to output stream.');
            }

            $offset += $written;
        }

        return $offset;
    } // write_all

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
        return $str === null || $str === '' ? 'null' : "'" . $this->escape($str) . "'";
    } // quotes_or_null

    /**
     * Validates a numeric string, or prepares an SQL NULL literal.
     *
     * @param string|null $str
     * The numeric value as a string. NULL and the empty string are represented as SQL NULL.
     *
     * @return float|int|string
     * Returns the original numeric string without losing decimal precision, or the literal null.
     *
     * @throws DBWorkerException
     * It might throw an exception if the value is not numeric.
     *
     * @see DBWorker::quotes_or_null()
     *
     * @author Oleg Schildt
     */
    public function number_or_null(?string $str): float|int|string
    {
        // Validate without converting to float, which could lose decimal precision.
        if ($str === null || $str === '') {
            return 'null';
        }

        if (!is_numeric($str)) {
            throw new DBWorkerException('Value is not a number.', self::ERR_WRONG_DATA_FORMAT);
        }

        return $str;
    } // number_or_null

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

        return $value === null ? 'null' : "'" . $value . "'";
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

        return $value === null ? 'null' : "'" . $value . "'";
    } // format_datetime

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
        // Return an unquoted value while preserving NULL and timestamp zero.
        return $date === null ? null : (
            $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : date('Y-m-d', $date)
        );
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
        // Return an unquoted value while preserving NULL and timestamp zero.
        return $datetime === null ? null : (
            $datetime instanceof \DateTimeInterface ? $datetime->format('Y-m-d H:i:s') : date('Y-m-d H:i:s', $datetime)
        );
    } // format_datetime_bind

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
     * It might throw an exception if the value format is invalid or the requested type is unsupported.
     *
     * @see DBWorker::quotes_or_null()
     * @see DBWorker::format_date()
     * @see DBWorker::format_datetime()
     *
     * @author Oleg Schildt
     */
    public function prepare_for_query(mixed $value, int $type): string
    {
        // Select the SQL representation from the explicit DBWorker type.
        if ($value === null || $value === '') {
            return 'null';
        }

        return match ($type) {
            self::DB_NUMBER => (string) $this->number_or_null((string) $value),
            self::DB_DATE => $this->format_date($value),
            self::DB_DATETIME => $this->format_datetime($value),
            self::DB_GEOMETRY, self::DB_GEOMETRY_4326 => throw new DBWorkerException(
                'Geometry is not supported by this driver.',
                self::ERR_WRONG_DATA_FORMAT
            ),
            default => $this->quotes_or_null((string) $value)
        };
    } // prepare_for_query

    /**
     * Builds a simple SELECT query using the database-specific limit syntax.
     *
     * SQL expressions and identifiers are supplied by the caller and are not escaped by this helper.
     *
     * @param string $table
     * The table expression supplied by the caller.
     *
     * @param array $fields
     * The nonempty list of field expressions to select.
     *
     * @param string $where_clause
     * The complete optional WHERE clause, including the WHERE keyword.
     *
     * @param string $order_clause
     * The complete optional ORDER BY clause, including its keywords.
     *
     * @param int $limit
     * The maximum number of rows to return. Zero means no limit; negative limits are invalid.
     *
     * @return string
     * Returns the assembled SQL query.
     *
     * @throws DBWorkerException
     * It might throw an exception if the field list is empty or the limit is negative.
     *
     * @author Oleg Schildt
     */
    public function build_select_query(
        string $table,
        array $fields,
        string $where_clause,
        string $order_clause,
        int $limit
    ): string {
        // Assemble the clauses independently so ORDER BY also works without a WHERE clause.
        if ($limit < 0 || !$fields) {
            throw new DBWorkerException('Invalid SELECT fields or limit.', self::ERR_WRONG_DATA_FORMAT);
        }

        $top = $this->dialect() === 'mssql' && $limit > 0 ? 'top ' . $limit . ' ' : '';
        $sql = 'select ' . $top . implode(', ', $fields) . PHP_EOL . "from " . $table;

        if ($where_clause !== '') {
            $sql .= PHP_EOL . $where_clause;
        }

        if ($order_clause !== '') {
            $sql .= PHP_EOL . $order_clause;
        }

        if ($limit > 0 && $this->dialect() !== 'mssql') {
            $sql .= $this->dialect() === 'oracle' ? PHP_EOL . "fetch first " . $limit . ' rows only' : PHP_EOL . "limit " . $limit;
        }

        return $sql . PHP_EOL;
    } // build_select_query

    /**
     * Returns the last application SQL query or prepared-query template.
     *
     * @return string
     * Returns the trimmed SQL text, or an empty string before any query is recorded.
     * Bound parameter values are not interpolated into prepared-query templates.
     *
     * @author Oleg Schildt
     */
    public function get_last_query(): string
    {
        // Keep the pre-query state safe for callers and exception diagnostics.
        return trim($this->last_query ?? '');
    } // get_last_query

    /**
     * Enables or disables query logging through the SmartFactory debugger.
     *
     * A configured query_logger callback takes precedence over the debugger.
     * Prepared parameter values are not interpolated; ordinary SQL is logged as supplied.
     *
     * @param bool $state
     * True enables logging; false disables it.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function enable_logging(bool $state): void
    {
        // Change logging behavior without changing the connection or current statement.
        $this->logging = $state;
    } // enable_logging

    /**
     * Removes leading whitespace and SQL comments for statement classification.
     *
     * @param string $sql
     * The original SQL text.
     *
     * @return string
     * Returns the remaining SQL text without its leading comments.
     *
     * @author Oleg Schildt
     */
    protected function sql_body(string $sql): string
    {
        // Ignore leading comments only for classification; native execution retains the original SQL.
        return preg_replace('~\A(?:\s+|--[^\n]*(?:\n|\z)|/\*[\s\S]*?\*/)*~', '', $sql);
    } // sql_body

    /**
     * Recognizes the target identifier of a simple INSERT INTO statement.
     *
     * Quoted identifiers are recognized. This is a conservative identity-handling helper, not a full SQL parser.
     *
     * @param string $sql
     * The SQL statement to inspect.
     *
     * @return string|null
     * Returns the optionally database/schema-qualified target, or NULL when no supported INSERT prefix is found.
     *
     * @author Oleg Schildt
     */
    protected function insert_table(string $sql): ?string
    {
        // Recognize a conservative INSERT prefix without trying to rewrite arbitrary SQL.
        $identifier = '(?:"(?:[^"]|"")+"|`(?:[^`]|``)+`|\[(?:[^\]]|\]\])+\]|[a-zA-Z_][a-zA-Z0-9_$#]*)';

        return preg_match(
            '~\AINSERT\s+INTO\s+(' . $identifier . '(?:\s*\.\s*' . $identifier . '){0,2})(?=\s|\()~i',
            $this->sql_body($sql),
            $match
        ) ? $match[1] : null;
    } // insert_table

    /**
     * Creates a database exception containing the current SQL template as technical information.
     *
     * @param string $message
     * The native or validation error message.
     *
     * @return DBWorkerException
     * Returns an exception with the ERR_QUERY_FAILED error code. This method does not throw it.
     *
     * @author Oleg Schildt
     */
    protected function query_error(string $message): DBWorkerException
    {
        // Keep application error codes separate from native message text and technical SQL.
        return new DBWorkerException($message, self::ERR_QUERY_FAILED, '', [], $this->get_last_query());
    } // query_error

    /**
     * Creates a stream exception containing the current SQL template as technical information.
     *
     * @param string $message
     * The stream error message.
     *
     * @return DBWorkerException
     * Returns an exception with the ERR_STREAM_ERROR error code. This method does not throw it.
     *
     * @author Oleg Schildt
     */
    protected function stream_error(string $message): DBWorkerException
    {
        // Keep stream failures distinguishable from native SQL execution failures.
        return new DBWorkerException($message, self::ERR_STREAM_ERROR, '', [], $this->get_last_query());
    } // stream_error

    /**
     * Measures native query execution and reports its outcome to the configured logger.
     *
     * Native execution failures are normalized to DBWorkerException. Bound values are not interpolated
     * into SQL or logged. A custom query_logger callback takes precedence over the debugger.
     * Ordinary SQL is logged exactly as supplied by the application.
     *
     * @param callable $action
     * The native query operation to execute.
     *
     * @param array $values
     * The bind values used only to derive parameter type names for the log.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw a database exception if execution fails, or propagate an exception raised by the logger.
     *
     * @author Oleg Schildt
     */
    protected function timed(callable $action, array $values = []): void
    {
        // Measure the native operation and keep bind contents out of the diagnostic context.
        $started = hrtime(true);
        $failed = false;

        try {
            $action();
        } catch (DBWorkerException $error) {
            $failed = true;

            throw $error;
        } catch (\Throwable $error) {
            $failed = true;

            throw $this->query_error($error->getMessage());
        } finally {
            // Record templates, timing and type names; never add bind contents to this context.
            $context = [
                'sql' => $this->last_query,
                'duration_ms' => (hrtime(true) - $started) / 1000000,
                'parameter_types' => array_map(get_debug_type(...), $values),
                'failed' => $failed
            ];

            if (is_callable($this->options['query_logger'] ?? null)) {
                ($this->options['query_logger'])($context);
            } elseif ($this->logging && function_exists('SmartFactory\\debugger')) {
                \SmartFactory\debugger()->debugMessage(
                    json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                    false,
                    'sql.log'
                );
            }
        }
    } // timed
    /**
     * Executes an internal bound statement without replacing the public prepared query or result.
     *
     * @param string $sql
     * The internal SQL template.
     *
     * @param array $arguments
     * The positional parameter values.
     *
     * @param bool $capture_identity
     * Whether the driver should inspect its native generated-ID result.
     *
     * @return array{rows: list<list<mixed>>, affected: int, identity: ?int}
     * Returns result rows, affected rows and the identity captured by the driver.
     *
     * @throws DBWorkerException
     * If preparing, executing or reading the internal statement fails.
     *
     * @author Oleg Schildt
     */
    protected function save_sql(string $sql, array $arguments = [], bool $capture_identity = true): array
    {
        // A clone shares the transaction but keeps internal statements separate from public query state.
        $worker = $this->create_clone();
        $worker->options['save_records_explicit_identity'] = !$capture_identity;

        try {
            $worker->prepare_query($sql);

            $worker->execute_prepared_query($arguments);

            $rows = [];
            $count = $worker->field_count();

            while ($count > 0 && $worker->fetch_row()) {
                $row = [];

                for ($index = 0; $index < $count; $index++) {
                    $row[] = $worker->field_by_num($index);
                }

                $rows[] = $row;
            }

            return ['rows' => $rows, 'affected' => $worker->affected_count(), 'identity' => $worker->insert_id()];
        } finally {
            $worker->free_prepared_query();
        }
    } // save_sql

    /**
     * Builds a per-record resource key using the database column types and comparison rules.
     *
     * @param array $context
     * The resolved table, column metadata and service-table location.
     *
     * @param array<string, scalar|null> $keys
     * The supplied key values, ordered by canonical column name.
     *
     * @return array{string, string}
     * Returns the SHA-256 lock key and a description without record values.
     *
     * @throws DBWorkerException
     * If a key cannot be normalized according to its database column type.
     *
     * @author Oleg Schildt
     */
    protected function save_resource(array $context, array $keys): array
    {
        // Hash database comparison keys rather than PHP representations of the caller's values.
        ksort($keys, SORT_STRING);

        $fingerprints = $this->native_save_key($context, $keys);
        $name = json_encode(['save_records', $context['resource'], array_keys($keys)], JSON_THROW_ON_ERROR);
        $key = hash('sha256', json_encode([$name, $fingerprints], JSON_THROW_ON_ERROR));

        return [$key, $name];
    } // save_resource

    /**
     * Resolves an unquoted configured column against the target table metadata.
     *
     * @param array $context
     * The resolved table, column metadata and service-table location.
     *
     * @param string $field
     * The configured unquoted field name.
     *
     * @return array
     * Returns the native column metadata.
     *
     * @throws DBWorkerException
     * If the configured column is absent or unsupported.
     *
     * @author Oleg Schildt
     */
    protected function save_column(array $context, string $field): array
    {
        $name = strtolower($field);

        if (!isset($context['columns'][$name])) {
            throw new DBWorkerException('Unknown save_records column: ' . $field, self::ERR_WRONG_DATA_FORMAT);
        }

        return $context['columns'][$name];
    } // save_column

    /**
     * Normalizes an explicit integer identity without interpreting zero or negative values as missing.
     *
     * @param mixed $value
     * The supplied identity value.
     *
     * @return int
     * Returns the normalized identity.
     *
     * @throws DBWorkerException
     * If the value is not an integer or exceeds the PHP integer range.
     *
     * @author Oleg Schildt
     */
    protected function save_identity_value(mixed $value): int
    {
        // Identity values must fit the existing integer insert_id() contract without truncation.
        if (!is_int($value) && !(is_string($value) && preg_match('/\A[+-]?\d+\z/', $value))) {
            throw new DBWorkerException('An explicit identity must be an integer.', self::ERR_WRONG_DATA_FORMAT);
        }

        $text = (string) $value;
        $negative = str_starts_with($text, '-');
        $digits = ltrim(ltrim($text, '+-'), '0');
        $normalized = $digits === '' ? '0' : ($negative ? '-' : '') . $digits;
        $integer = filter_var($normalized, FILTER_VALIDATE_INT);

        if ($integer === false) {
            throw new DBWorkerException('Identity exceeds the PHP integer range.', self::ERR_WRONG_DATA_FORMAT);
        }

        return $integer;
    } // save_identity_value

    /**
     * Rejects further saves in a caller transaction after a failed save.
     *
     * @return void
     * Does not return a value.
     *
     * @throws DBWorkerException
     * If the worker is read-only or the transaction requires an explicit rollback.
     *
     * @author Oleg Schildt
     */
    protected function native_save_check(): void
    {
        if ($this->read_only) {
            throw $this->query_error('save_records() cannot use a read-only worker.');
        }

        if ($this->connection_state->attributes['save_records_failed'] ?? false) {
            throw $this->query_error('Roll back the failed transaction before calling save_records() again.');
        }
    } // native_save_check

    /**
     * Checks existence after acquiring the service-row lock using a fresh statement snapshot.
     *
     * @param array $context
     * The resolved table, column metadata and service-table location.
     *
     * @param string $where
     * The bound key predicate.
     *
     * @param array $arguments
     * The non-NULL key values.
     *
     * @return bool
     * Returns whether the key matches an existing row.
     *
     * @throws DBWorkerException
     * If preparing or executing the typed existence query fails.
     *
     * @author Oleg Schildt
     */
    protected function native_save_exists(array $context, string $where, array $arguments): bool
    {
        $result = $this->save_sql('SELECT 1 FROM ' . $context['table'] . ' WHERE ' . $where, $arguments);

        return $result['rows'] !== [];
    } // native_save_exists

    /**
     * Inserts a record using native identity handling or an explicitly supplied integer identity.
     *
     * @param array $context
     * The resolved table, column metadata and service-table location.
     *
     * @param array $values
     * The bound field values, including an explicit identity when supplied.
     *
     * @param array<string, string> $expressions
     * The positional SQL expression for each field.
     *
     * @param string $identity_field
     * The configured identity field, or an empty string.
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
        $query = 'INSERT INTO ' . $context['table'] . ' (' . implode(', ', array_keys($values))
            . ') VALUES (' . implode(', ', $expressions) . ')';
        $result = $this->save_sql($query, array_values($values), $identity_field === '' || !isset($values[$identity_field]));
        $this->affected_rows = $result['affected'];

        return $identity_field === '' ? null : ($values[$identity_field] ?? $result['identity']);
    } // native_save_insert

    /**
     * Allocates sequence-backed identities using a short separately committed service-row watermark.
     *
     * The watermark is intentionally not rolled back with the saved record. Gaps are permitted.
     * This coordinates save_records() allocations; direct SQL sequence users are outside this protocol.
     *
     * @param array $context
     * The resolved table, column metadata and service-table location.
     *
     * @param string $field
     * The configured sequence-backed identity field.
     *
     * @param int|null $explicit
     * The requested ID, or NULL to allocate a new ID.
     *
     * @return int
     * Returns the requested or newly allocated integer identity.
     *
     * @throws DBWorkerException
     * If an identity is out of range, its generator is unsupported or reserving an ID fails.
     *
     * @author Oleg Schildt
     */
    protected function save_allocate_identity(array $context, string $field, ?int $explicit): int
    {
        $column = $this->save_column($context, $field);
        $sequence = $column['sequence'] ?? null;

        if ($sequence === null || ($column['always'] ?? false)) {
            if ($explicit !== null) {
                throw new DBWorkerException('Explicit identity insertion requires GENERATED BY DEFAULT.', self::ERR_WRONG_DATA_FORMAT);
            }

            throw $this->query_error('No supported identity sequence was found for ' . $field);
        }

        // Persist a high-water mark so explicit IDs cannot collide with subsequent save_records() allocations.
        // Native sequence caches may retain older ranges; the service watermark takes precedence over them.
        $coordinator = new static();
        $parameters = array_replace($this->options, ['read_only' => false,
            'isolation_level' => self::ISOLATION_READ_COMMITTED,
            'default_isolation_level' => self::ISOLATION_READ_COMMITTED]);

        $coordinator->init($parameters);

        $coordinator->connect();

        try {
            $coordinator->start_transaction(self::ISOLATION_READ_COMMITTED);

            $resource = hash('sha256', json_encode(['save_identity', $context['resource'], $column['name']], JSON_THROW_ON_ERROR));

            $coordinator->native_save_lock($context, $resource, '0');

            $result = $coordinator->save_sql('SELECT resource_name FROM ' . $context['lock_table'] . ' WHERE resource_key = ?', [$resource]);
            $stored = (string) ($result['rows'][0][0] ?? '0');
            $state = json_decode($stored, true);
            $watermark = is_array($state) ? (int) $state['watermark'] : (int) $stored;
            $existing = $coordinator->save_sql('SELECT MAX(' . $field . ') FROM ' . $context['table'])['rows'][0][0];
            $watermark = max($watermark, $existing === null ? 0 : $this->save_identity_value($existing));
            $native = $explicit === null ? $coordinator->native_save_next_identity($sequence) : 0;

            if ($explicit === null && $watermark === PHP_INT_MAX) {
                throw $this->query_error('Identity allocator exceeds the PHP integer range.');
            }

            $identity = $explicit ?? max($native, $watermark + 1);
            $high = max($watermark, $identity);

            // Reject a value outside the native column range before persisting an unusable watermark.
            $coordinator->save_sql('SELECT ' . $column['cast'] . ($this->dialect() === 'oracle' ? ' FROM dual' : ''), [$identity]);

            $description = json_encode(['resource' => $context['resource'], 'identity' => $column['name'],
                'watermark' => (string) $high], JSON_THROW_ON_ERROR);

            $coordinator->save_sql('UPDATE ' . $context['lock_table'] . ' SET resource_name = ? WHERE resource_key = ?', [$description, $resource]);

            $coordinator->commit_transaction();

            return $identity;
        } catch (\Throwable $error) {
            if ($coordinator->connection_state?->transaction) {
                $coordinator->rollback_transaction();
            }

            throw $error;
        } finally {
            $coordinator->close_connection();
        }
    } // save_allocate_identity

    /**
     * Provides the driver hook for obtaining a native sequence value.
     *
     * @param string $sequence
     * The resolved native sequence identifier.
     *
     * @return int
     * Returns the native sequence value.
     *
     * @throws DBWorkerException
     * If the driver does not implement sequence-backed allocation.
     *
     * @author Oleg Schildt
     */
    protected function native_save_next_identity(string $sequence): int
    {
        throw $this->query_error('This driver does not use the sequence-backed save_records() allocator.');
    } // native_save_next_identity


} // DBWorker
