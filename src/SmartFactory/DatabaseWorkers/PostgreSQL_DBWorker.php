<?php
/**
 * This file contains the implementation of the abstract base class DBWorker
 * for the PostreSQL database using the extension pgsql.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\DatabaseWorkers;

use function \SmartFactory\debugger;

/**
 * This is the class for the PostreSQL database using the extension pgsql.
 *
 * This is a wrapper around the database connectivity. It offers a universal
 * common way for working with databases of different types. Currently, MySQL, PostgreSQL and
 * MS SQL are supported. If in the future, there will be a better solution, or
 * the current solution turns out to be inefficient in a new version of PHP,
 * we can easily reimplement the DB wrapper without touching the business logic code.
 * Adding support for new database types is also much easier with this wrapping approach.
 *
 * @see PostgreSQL_DBWorker
 * @see MSSQL_DBWorker
 *
 * @author Oleg Schildt
 */
class PostgreSQL_DBWorker extends DBWorker
{
    /**
     * Flag for setting the connection to read only.
     *
     * @var bool $read_only
     *
     * @author Oleg Schildt
     */
    protected bool $read_only = false;

    /**
     * Internal PgSql\Connection object.
     *
     * @var ?\PgSql\Connection
     *
     * @author Oleg Schildt
     */
    protected ?\PgSql\Connection $connection = null;

    /**
     * Internal \PgSql\Result object.
     *
     * @var ?\PgSql\Result
     *
     * @author Oleg Schildt
     */
    protected ?\PgSql\Result $result = null;

    /**
     * Internal \PgSql\Result object for statements.
     *
     * @var ?\PgSql\Result
     *
     * @author Oleg Schildt
     */
    protected ?\PgSql\Result $statement = null;

    /**
     * Internal variable for storing of the last prepared query.
     *
     * @var ?string
     *
     * @author Oleg Schildt
     */
    protected ?string $prepared_query = null;

    /**
     * Internal variable for storing of the current fetched row
     * from the result of the last retrieving query.
     *
     * @var null|bool|array
     *
     * @author Oleg Schildt
     */
    protected null|bool|array $row = null;

    /**
     * Internal variable for storing of the column names
     * from the result of the last retrieving query.
     *
     * @var ?array
     *
     * @author Oleg Schildt
     */
    protected ?array $field_names = null;

    /**
     * Creates a clone of the dbworker that is using the same open
     * connection.
     *
     * This might be useful if you want to execute some additional
     * queries while iteration through the active results of a select query.
     *
     * @return PostgreSQL_DBWorker
     * Returns the clone of this dbworker.
     *
     * @author Oleg Schildt
     */
    public function create_clone(): PostgreSQL_DBWorker
    {
        $cln = new PostgreSQL_DBWorker();

        $cln->is_clone = true;

        $cln->db_server = $this->db_server;
        $cln->db_port = $this->db_port;
        $cln->db_name = $this->db_name;
        $cln->db_user = $this->db_user;
        $cln->db_password = $this->db_password;
        $cln->read_only = $this->read_only;

        return $cln;
    } // create_clone

    /**
     * Constructor.
     *
     * @author Oleg Schildt
     */
    public function __construct()
    {
    } // __construct

    /**
     * Destructor.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function __destruct()
    {
        if (!$this->is_clone) {
            $this->close_connection();
        }
    } // __destruct

    /**
     * This auxiliary function reads the data from large object.
     *
     * @param int $oid
     * The oid of the large object.
     *
     * @return string
     * Returns the data read from the largeobject.
     *
     * @used_by PostgreSQL_DBWorker::field_by_name()
     * @used_by PostgreSQL_DBWorker::field_by_num()
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    protected function read_large_object(int $oid): string
    {
        $ohandle = @pg_lo_open($this->connection, $oid, "r");
        if (empty($ohandle)) {
            throw new DBWorkerException("Large object id is invalid!", DBWorker::ERR_STREAM_ERROR);
        }

        $data = "";
        while ($chunk = @pg_lo_read($ohandle)) {
            $data .= $chunk;
        }

        return $data;
    } // read_large_object

    /**
     * Initializes the dbworker with connection parameters.
     *
     * @param array $parameters
     * Connection settings as an associative array in the form key => value:
     *
     * - $parameters["db_server"] - server address.
     * - $parameters["db_port"] - server port.
     * - $parameters["db_name"] - database name.
     * - $parameters["db_user"] - user name.
     * - $parameters["db_password"] - user password.
     * - $parameters["read_only"] - this parameter sets the connection to the read only mode.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void
    {
        if (!empty($parameters["db_server"])) {
            $this->db_server = $parameters["db_server"];
        }
        if (!empty($parameters["db_port"])) {
            $this->db_name = $parameters["db_port"];
        }
        if (!empty($parameters["db_name"])) {
            $this->db_name = $parameters["db_name"];
        }
        if (!empty($parameters["db_user"])) {
            $this->db_user = $parameters["db_user"];
        }
        if (!empty($parameters["db_password"])) {
            $this->db_password = $parameters["db_password"];
        }
        if (!empty($parameters["read_only"])) {
            $this->read_only = $parameters["read_only"];
        }
    } // init

    /**
     * Checks whether the extension (pgsql) is installed which is required for work with
     * the PostreSQL database.
     *
     * @return bool
     * The method should return true if the extension is installed, otherwise false.
     *
     * @see PostgreSQL_DBWorker::get_extension_name()
     *
     * @author Oleg Schildt
     */
    public function is_extension_installed(): bool
    {
        return function_exists("pg_connect");
    } // is_extension_installed

    /**
     * Returns the name of the required PHP extension - "pgsql".
     *
     * @return string
     * Returns the name of the required PHP extension - "pgsql".
     *
     * @see PostgreSQL_DBWorker::is_extension_installed()
     *
     * @author Oleg Schildt
     */
    public function get_extension_name(): string
    {
        return "pgsql";
    } // get_extension_name

    /**
     * Returns the name of the supported database - "PostgreSQL Server".
     *
     * @return string
     * Returns the name of the supported database - "PostgreSQL Server".
     *
     * @author Oleg Schildt
     */
    public function get_rdbms_name(): string
    {
        return "PostgreSQL Server";
    } // get_rdbms_name

    /**
     * Returns the connection state.
     *
     * @return bool
     * Returns true if the connection is open, otherwise false.
     *
     * @see DBWorker::check_connection()
     * @see PostgreSQL_DBWorker::connect()
     * @see PostgreSQL_DBWorker::close_connection()
     *
     * @author Oleg Schildt
     */
    public function is_connected(): bool
    {
        return (!empty($this->connection) && pg_connection_status($this->connection) == PGSQL_CONNECTION_OK);
    } // is_connected

    /**
     * Retrieves the last errors occurred while execution of the query.
     *
     * @return string
     * Returns the string of errors separated by the new line symbol.
     *
     * @author Oleg Schildt
     */
    protected function sys_get_errors(): string
    {
        $errors = error_get_last();
        if (empty($errors)) {
            return "";
        }

        $message_array = [];

        foreach ($errors as $error) {
            $message_array[$error['message']] = $error['message'];
        }

        return implode("\n", $message_array);
    } // sys_get_errors

    /**
     * Establishes the connection to the database using the connection
     * settings parameters specified by the initialization.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see DBWorker::check_connection()
     * @see PostgreSQL_DBWorker::is_connected()
     * @see PostgreSQL_DBWorker::close_connection()
     *
     * @author Oleg Schildt
     */
    public function connect(): void
    {
        if ($this->is_connected()) {
            return;
        }

        if (!$this->connection) {
            if (empty($this->db_server) || empty($this->db_user) || empty($this->db_password)) {
                throw new DBWorkerException("Connection data is incomplete", DBWorker::ERR_CONNECTION_DATA_INCOMPLETE);
            }

            if (empty($this->db_name)) {
                throw new DBWorkerException("The database must be specified immediately by the connection!", DBWorker::ERR_CONNECTION_DATA_INCOMPLETE);
            }

            $connection_string = "connect_timeout=20 options='--client_encoding=UTF8' ";
            if (!empty($this->db_server)) {
                $connection_string .= " host=" . $this->db_server;
            }
            if (!empty($this->db_port)) {
                $connection_string .= " port=" . $this->db_port;
            }
            if (!empty($this->db_user)) {
                $connection_string .= " user=" . $this->db_user;
            }
            if (!empty($this->db_name)) {
                $connection_string .= " dbname=" . $this->db_name;
            }
            if (!empty($this->db_password)) {
                $connection_string .= " password=" . $this->db_password;
            }

            $this->connection = pg_connect($connection_string);
        }

        if (!$this->connection) {
            $err = $this->sys_get_errors();
            $this->connection = null;

            throw new DBWorkerException($err, DBWorker::ERR_CONNECTION_FAILED);
        }
    } // connect

    /**
     * Sets the database as working database.
     *
     * @param string $db_name
     * The name of the database to be set as working database.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function use_database(string $db_name): void
    {
        throw new DBWorkerException("The database must be specified immediately by the connection!", DBWorker::ERR_CONNECTION_DATA_INCOMPLETE);
    } // use_database

    /**
     * Returns the name of the database schema if applicable.
     *
     * @return string
     * Returns the name of the database schema if applicable, or an empty string.
     *
     * @see PostgreSQL_DBWorker::qualify_name_with_schema()
     *
     * @author Oleg Schildt
     */
    public function get_schema(): string
    {
        return "public";
    } // get_schema

    /**
     * Completes the name of a database object with the schema name if applicable.
     *
     * @param string $name
     * The name of the database object to be completed with the schema name.
     *
     * @return string
     * Returns the name of the database object with the schema name if applicable,
     * otherwise the name of the database object remains unchanged.
     *
     * @see PostgreSQL_DBWorker::get_schema()
     *
     * @author Oleg Schildt
     */
    public function qualify_name_with_schema(string $name): string
    {
        $schema = $this->get_schema();

        if (!empty($schema)) {
            $schema .= ".";
        }

        return $schema . $name;
    } // qualify_name_with_schema

    /**
     * Executes the SQL query.
     *
     * @param string $query_string
     * The SQL query to be executed.
     *
     * @return void
     *
     * @throws \Exception|DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function execute_query(string $query_string): void
    {
        $this->check_connection();

        $this->last_query = $query_string;

        if ($this->logging) {
            debugger()->debugMessage($this->last_query, "sql.log");
        }

        $this->result = @pg_query($this->connection, $query_string);
        if (!$this->result) {
            throw new DBWorkerException(pg_last_error(), DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }
    } // execute_query

    /**
     * Prepares the SQL query with bindable variables.
     *
     * @param string $query_string
     * The SQL query to be prepared.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see PostgreSQL_DBWorker::execute_prepared_query()
     * @see PostgreSQL_DBWorker::free_prepared_query()
     *
     * @author Oleg Schildt
     */
    public function prepare_query(string $query_string): void
    {
        $this->check_connection();

        if ($this->statement) {
            @pg_free_result($this->statement);
            $this->statement = null;
        }

        $counter = 1;
        $query_string = preg_replace_callback("/\\?/", function () use (&$counter) {
            return "$" . ($counter++);
        }, $query_string);

        $this->last_query = $query_string;
        $this->prepared_query = $query_string;

        $this->statement = @pg_prepare($this->connection, "", $query_string);
        if (!$this->statement) {
            throw new DBWorkerException(pg_last_error($this->connection), DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }
    } // prepare_query

    /**
     * Stores long data from a stream.
     *
     * @param string $query_string
     * The SQL query to be used for storing the long data.
     *
     * @param resource $stream
     * The opened valid stream for reading the long data.
     *
     * Example:
     * ```php
     * $stream = fopen(".../large_binary.jpg", "rb");
     *
     * if(!$dbw->stream_long_data("UPDATE LARGE_DATA SET BLOB_DATA = ? WHERE ID = 1", $stream))
     * {
     *   error reporting ...;
     * }
     * ```
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function stream_long_data(string $query_string, $stream): void
    {
        $this->check_connection();

        if (!is_resource($stream)) {
            throw new DBWorkerException("Stream is invalid!", DBWorker::ERR_STREAM_ERROR);
        }

        if ($this->statement) {
            @pg_free_result($this->statement);
            $this->statement = null;
        }

        $this->last_query = $query_string;
        $this->prepared_query = $query_string;

        $oid = pg_lo_create($this->connection);

        $this->statement = @pg_prepare($this->connection, "", $query_string);
        if (!$this->statement) {
            throw new DBWorkerException(pg_last_error($this->connection), DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        $this->result = @pg_execute($this->connection, "", [$oid]);
        if (!$this->result) {
            throw new DBWorkerException(pg_last_error(), DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        $ohandle = @pg_lo_open($this->connection, $oid, "w");
        if (empty($ohandle)) {
            throw new DBWorkerException("Error writing from stream to PG large object!", DBWorker::ERR_STREAM_ERROR);
        }

        while (!feof($stream)) {
            if (!@pg_lo_write($ohandle, fread($stream, 8192))) {
                fclose($stream);
                @pg_lo_close($ohandle);
                throw new DBWorkerException("Error writing from stream to PG large object!", DBWorker::ERR_STREAM_ERROR);
            }
        }

        @pg_lo_close($ohandle);
    } // stream_long_data

    /**
     * Executes the prepared SQL query.
     *
     * @param mixed ...$args
     * The number of parameters may vary and be zero. An array can also be passed.
     * These are parameters of the prepared query.
     *
     * @return void
     *
     * @throws \Exception|DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see PostgreSQL_DBWorker::prepare_query()
     * @see PostgreSQL_DBWorker::free_prepared_query()
     *
     * @author Oleg Schildt
     */
    public function execute_prepared_query(...$args): void
    {
        $this->check_connection();

        if (empty($this->prepared_query) || empty($this->statement)) {
            throw new DBWorkerException("No prepared query defined", DBWorker::ERR_QUERY_FAILED);
        }

        if (count($args) == 1 && is_array($args[0])) {
            $args = $args[0];
        }

        $this->last_query = $this->prepared_query;

        if ($this->logging) {
            debugger()->debugMessage($this->last_query, "sql.log");
        }

        $this->result = @pg_execute($this->connection, "", $args);
        if (!$this->result) {
            throw new DBWorkerException(pg_last_error(), DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }
    } // execute_prepared_query

    /**
     * Executes the SQL stored procedure.
     *
     * @param string $procedure
     * The name of the SQL stored procedure.
     *
     * All subsequent parameters are the parameters of the SQL stored procedure.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function execute_procedure(string $procedure /* arg list */): void
    {
        $this->check_connection();

        $args = func_get_args();

        // prepare the arguments for placing in eval()
        // escape single quotes

        $this->last_query = "";

        if (count($args) > 0) {
            $proc_name = "";
            $arg_list = "";

            $first = true;
            foreach ($args as $argval) {
                if ($first) {
                    $proc_name = $argval;
                    $first = false;
                    continue;
                }

                if ($argval === null) {
                    $arg_list .= "null, ";
                } elseif (is_int($argval)) {
                    $arg_list .= "$argval, ";
                } elseif (is_float($argval)) {
                    $arg_list .= "$argval, ";
                } else {
                    $arg_list .= "'" . $this->escape($argval) . "', ";
                }
            }

            $arg_list = trim($arg_list, ", ");

            $this->last_query = "CALL $proc_name($arg_list);";
        }

        $this->execute_query($this->last_query);
    } // execute_procedure

    /**
     * Frees the prepared query.
     *
     * It should be called after all executions of the prepared query.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see PostgreSQL_DBWorker::prepare_query()
     * @see PostgreSQL_DBWorker::execute_prepared_query()
     *
     * @author Oleg Schildt
     */
    public function free_prepared_query(): void
    {
        $this->check_connection();

        if ($this->statement) {
            @pg_free_result($this->statement);

            $this->statement = null;
        }

        $this->statement = null;
        $this->last_query = null;
        $this->prepared_query = null;
    } // free_prepared_query

    /**
     * Closes the currently opened connection.
     *
     * @return void
     *
     * @see PostgreSQL_DBWorker::is_connected()
     * @see PostgreSQL_DBWorker::connect()
     *
     * @author Oleg Schildt
     */
    public function close_connection(): void
    {
        $this->last_query = null;
        $this->prepared_query = null;
        $this->row = null;
        $this->field_names = null;

        if ($this->statement) {
            @pg_free_result($this->statement);
        }

        if ($this->result) {
            @pg_free_result($this->result);
        }

        if ($this->connection) {
            @pg_close($this->connection);
        }

        $this->statement = null;
        $this->connection = null;
        $this->result = null;
    } // close_connection

    /**
     * Starts the transation.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see PostgreSQL_DBWorker::commit_transaction()
     * @see PostgreSQL_DBWorker::rollback_transaction()
     *
     * @author Oleg Schildt
     */
    public function start_transaction(): void
    {
        $this->check_connection();

        $this->execute_query("BEGIN");
    } // start_transaction

    /**
     * Commits the transation.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see PostgreSQL_DBWorker::start_transaction()
     * @see PostgreSQL_DBWorker::rollback_transaction()
     *
     * @author Oleg Schildt
     */
    public function commit_transaction(): void
    {
        $this->check_connection();

        $this->execute_query("COMMIT");
    } // commit_transaction

    /**
     * Rolls back the transation.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see PostgreSQL_DBWorker::start_transaction()
     * @see PostgreSQL_DBWorker::commit_transaction()
     *
     * @author Oleg Schildt
     */
    public function rollback_transaction(): void
    {
        $this->check_connection();

        $this->execute_query("ROLLBACK");
    } // rollback_transaction

    /**
     * Frees the result of the previously executed retrieving query.
     *
     * It should be called only for the retrieving queries.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function free_result(): void
    {
        $this->check_connection();

        if ($this->result) {
            @pg_free_result($this->result);

            $this->result = null;
        }

        $this->row = null;
        $this->field_names = null;
        $this->last_query = null;
    } // free_result

    /**
     * Returns the value of the auto increment field by the last insertion.
     *
     * WARNING! This method only works
     * if there are no triggers with insertion commands.
     *
     * PostgreSQL recommends using the sequences like Oracle. If you want to use sequences,
     * generate ID values with passing to {@see PostgreSQL_DBWorker::execute_query()} the sequence generation commands
     * and write your inserts with explicit ID values.
     *
     * @return ?int
     * Returns the value of the auto increment field by the last insertion.
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function insert_id(): ?int
    {
        $this->check_connection();

        $this->execute_query("select lastval() as iid");

        if (!$this->fetch_row()) {
            $err = "Identity field cannot be retrieved";

            $this->free_result();

            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], "select lastval() as iid");
        }

        $id = $this->field_by_name("iid");

        $this->free_result();

        return $id;
    } // insert_id

    /**
     * Fetches the next row of data from the result of the execution of the retrieving query.
     *
     * @return bool
     * Returns true if the next row exists and has been successfully fetched, otherwise false.
     *
     * Example:
     * ```php
     * if(!$dbw->execute_query("SELECT FIRST_NAME, LAST_NAME FROM USERS"))
     * {
     *   error reporting ...;
     * }
     *
     * while($dbw->fetch_row())
     * {
     *   echo $dbw->field_by_name("FIRST_NAME") . " " . $dbw->field_by_name("LAST_NAME") . "<br>";
     * }
     *
     * $dbw->free_result();
     * ```
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function fetch_row(): bool
    {
        $this->check_connection();

        if (!$this->result) {
            $err = "Result is empty!";
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        $this->row = @pg_fetch_array($this->result, null, PGSQL_ASSOC);

        if (!$this->row) {
            return false;
        }

        if (!$this->field_names) {
            $this->field_names = array_keys($this->row);
        }

        return true;
    } // fetch_row

    /**
     * Fetches all rows from the result into an array.
     *
     * @param array &$rows
     * Target array for loading the results.
     *
     * @param ?array $dimension_keys
     * Array of the column names that should be used as dimensions.
     *
     * Per default, the rows are fetched as two-dimensional array.
     *
     * Example:
     * ```php
     * $rows = [];
     * $dbw->fetch_array($rows);
     *
     * rows[n] = ["col1" => "val1", "col2" => "val2", "col3" => "val3", ...]
     * ```
     * If the dimension columns are specified, their values are used for the dimensions.
     *
     * Example:
     * ```php
     * $rows = [];
     * $dbw->fetch_array($rows, ["col1", "col2"]);
     *
     * rows["val1"]["val2"] = ["col3" => "val3", ...]
     * ```
     *
     * @return int
     * Returns the number of the fetched rows. It might be also 0.
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function fetch_array(array &$rows, ?array $dimension_keys = null): int
    {
        $this->check_connection();

        $rows = [];

        if (!empty($dimension_keys)) {
            $dimension_keys = array_flip($dimension_keys);
        }

        $counter = 0;

        if (!$this->result) {
            $err = "Result is empty!";
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        while ($row = @pg_fetch_array($this->result, null, PGSQL_ASSOC)) {
            $counter++;

            if (!$this->field_names) {
                $this->field_names = array_keys($row);
            }

            if (empty($dimension_keys)) {
                $rows[] = $row;
            } else {
                $dimensions = array_intersect_key($row, $dimension_keys);
                $row = array_diff_key($row, $dimension_keys);

                $reference = &$rows;

                foreach ($dimensions as $dval) {
                    if (empty($reference[$dval])) {
                        $reference[$dval] = [];
                    }
                    $reference = &$reference[$dval];
                }

                $reference = $row;

                unset($reference);
            }
        }

        return $counter;
    } // fetch_array

    /**
     * Returns the number of the rows fetched by the last retrieving query.
     *
     * @return int
     * Returns the number of the rows fetched by the last retrieving query.
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function fetched_count(): int
    {
        $this->check_connection();

        if (!$this->result || !is_object($this->result)) {
            $err = "Result is empty!";
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        return pg_affected_rows($this->result);
    } // fetched_count

    /**
     * Returns the number of the rows affected by the last modification query.
     *
     * @return int
     * Returns the number of the rows affected by the last modification query.
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function affected_count(): int
    {
        $this->check_connection();

        if (!$this->result || !is_object($this->result)) {
            $err = "Result is empty!";
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        return pg_affected_rows($this->result);
    } // affected_count

    /**
     * Returns the number of the fields in the result of the last retrieving query.
     *
     * @return int
     * Returns the number of the fields in the result of the last retrieving query.
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function field_count(): int
    {
        $this->check_connection();

        if (!$this->result || !is_object($this->result)) {
            $err = "Result is empty!";
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        return pg_num_fields($this->result);
    } // field_count

    /**
     * Returns the value of a field specified by name.
     *
     * @param string $name
     * The name of the field.
     *
     * @param int $type
     * The type of the field.
     *
     * @return mixed
     * Returns the value of a field specified by name. In the case
     * of any error returns null.
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see PostgreSQL_DBWorker::field_by_num()
     * @see PostgreSQL_DBWorker::field_name()
     *
     * @author Oleg Schildt
     */
    public function field_by_name(string $name, int $type = self::DB_AS_IS): mixed
    {
        $this->check_connection();

        if (!$this->row) {
            return null;
        }

        if (!array_key_exists($name, $this->row)) {
            trigger_error("Field with the name '$name' does not exist in the result set!", E_USER_WARNING);
            return null;
        }

        if ($type == DBWorker::DB_LARGE_OBJECT_STREAM) {
            return $this->read_large_object($this->row[$name]);
        }

        if (($type == DBWorker::DB_DATE || $type == DBWorker::DB_DATETIME) && !empty($this->row[$name])) {
            return strtotime($this->row[$name]);
        }

        return $this->row[$name];
    } // field_by_name

    /**
     * Returns the value of a field specified by number.
     *
     * @param int $num
     * The number of the field.
     *
     * @param int $type
     * The type of the field.
     *
     * @return mixed
     * Returns the value of a field specified by number. In the case
     * of any error returns null.
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see PostgreSQL_DBWorker::field_by_name()
     * @see PostgreSQL_DBWorker::field_info_by_num()
     * @see PostgreSQL_DBWorker::field_name()
     *
     * @author Oleg Schildt
     */
    public function field_by_num(int $num, int $type = self::DB_AS_IS): mixed
    {
        $this->check_connection();

        if (!$this->row) {
            return null;
        }

        if (!array_key_exists($num, $this->field_names)) {
            trigger_error("Field with the index $num does not exist in the result set!", E_USER_WARNING);
            return null;
        }

        if ($type == DBWorker::DB_LARGE_OBJECT_STREAM) {
            return $this->read_large_object($this->row[$this->field_names[$num]]);
        }

        if (($type == DBWorker::DB_DATE || $type == DBWorker::DB_DATETIME) && !empty($this->row[$this->field_names[$num]])) {
            return strtotime($this->row[$this->field_names[$num]]);
        }

        return $this->row[$this->field_names[$num]];
    } // field_by_num

    /**
     * Returns the name of the field by number.
     *
     * @param int $num
     * The number of the field.
     *
     * @return ?string
     * Returns the value of a field specified by number as an object with properties. In the case
     * of any error returns null.
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see PostgreSQL_DBWorker::field_by_num()
     * @see PostgreSQL_DBWorker::field_info_by_num()
     *
     * @author Oleg Schildt
     */
    public function field_name(int $num): ?string
    {
        $this->check_connection();

        $info = $this->field_info_by_num($num);
        if (!$info) {
            return null;
        }

        return $info["name"] ?? "";
    } // field_name

    /**
     * Returns the meta information about the field as an object with properties.
     *
     * @param int $num
     * The number of the field.
     *
     * @return ?array
     * Returns the associative array with properties. In the case
     * of any error returns null.
     *
     * - $info["name"] - name of the field.
     * - $info["type"] - type of the field.
     * - $info["size"] - size of the field.
     * - $info["binary"] - whether the filed is binary.
     * - $info["numeric"] - whether the filed is numeric.
     * - $info["datetime"] - whether the filed is datetime.
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see PostgreSQL_DBWorker::field_by_num()
     * @see PostgreSQL_DBWorker::field_name()
     *
     * @author Oleg Schildt
     */
    public function field_info_by_num(int $num): ?array
    {
        $this->check_connection();

        if (!$this->result) {
            $err = "Result is empty!";
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        $field_info = [];

        $field_info["name"] = pg_field_name($this->result, $num);
        $field_info["type"] = pg_field_type($this->result, $num);
        $field_info["size"] = pg_field_size($this->result, $num);

        $field_info["string"] = in_array($field_info["type"], ["text", "varchar", "char"]) ? 1 : 0;
        $field_info["binary"] = ($field_info["type"] == "bytea") ? 1 : 0;
        $field_info["numeric"] = ($field_info["type"] == "int" || $field_info["type"] == "float" || $field_info["type"] == "numeric") ? 1 : 0;
        $field_info["datetime"] = ($field_info["type"] == "timestamp" || $field_info["type"] == "date") ? 1 : 0;

        return $field_info;
    } // field_info_by_num

    /**
     * Escapes the string so that it can be used in the query without causing an error.
     *
     * @param string $str
     * The string to be escaped.
     *
     * @return string
     * Returns the escaped string.
     *
     * @see PostgreSQL_DBWorker::format_date()
     * @see PostgreSQL_DBWorker::format_datetime()
     *
     * @author Oleg Schildt
     */
    public function escape(string $str): string
    {
        return pg_escape_string($this->connection, $str);
    } // escape

    /**
     * Formats the date to a string compatible for the corresponding database.
     *
     * @param int $date
     * The date value as timestamp.
     *
     * @return string
     * Returns the string representation of the date compatible for the corresponding database.
     *
     * @see PostgreSQL_DBWorker::escape()
     * @see PostgreSQL_DBWorker::format_datetime()
     *
     * @author Oleg Schildt
     */
    public function format_date(int $date): string
    {
        if (empty($date)) {
            return $this->quotes_or_null("");
        }

        return $this->quotes_or_null(date("Y-m-d", $date));
    } // format_date

    /**
     * Formats the date/time to a string compatible for the corresponding database.
     *
     * @param int $datetime
     * The date/time value as timestamp.
     *
     * @return string
     * Returns the string representation of the date/time compatible for the corresponding database.
     *
     * @see PostgreSQL_DBWorker::escape()
     * @see PostgreSQL_DBWorker::format_date()
     *
     * @author Oleg Schildt
     */
    public function format_datetime(int $datetime): string
    {
        if (empty($datetime)) {
            return $this->quotes_or_null("");
        }

        return $this->quotes_or_null(date("Y-m-d H:i:s", $datetime));
    } // format_datetime

    /**
     * Prepares the value for putting to a query depending on its type. It does escaping, formatting
     * and quotation if necessary.
     *
     * @param mixed $value
     * The value to be formatted.
     *
     * @param int $type
     * The type of the value.
     *
     * @return string
     * Returns the prepared value.
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    function prepare_for_query(mixed $value, int $type): string
    {
        if (empty($value) && (string)$value != "0") {
            return "null";
        } else {
            return match ($type) {
                DBWorker::DB_NUMBER => $this->number_or_null($value),
                DBWorker::DB_DATETIME => $this->format_datetime($value),
                DBWorker::DB_DATE => $this->format_date($value),
                default => $this->quotes_or_null($value)
            };
        }
    } // prepare_for_query

    /**
     * Builds simple select query based on parameters.
     *
     * It is used for building queries with limits.
     *
     * @param string $table
     * The name of the table.
     *
     * @param array $fields
     * The list of request fields.
     *
     * @param string $where_clause
     * The where clause that should restrict the result.
     *
     * @param string $order_clause
     * The order clause to sort the results.
     *
     * @param int $limit
     * The limit how many records should be loaded. 0 for unlimited.
     *
     * @return string
     * Returns the built query.
     *
     * @author Oleg Schildt
     */
    function build_select_query(string $table, array $fields, string $where_clause, string $order_clause, int $limit): string
    {
        $query = "select\n";

        $query .= implode(", ", $fields) . "\n";

        $query .= "from " . $table . "\n";

        if (!empty($where_clause)) {
            $query .= $where_clause . "\n";
        }

        if (!empty($where_clause)) {
            $query .= $order_clause . "\n";
        }

        if (!empty($limit)) {
            $query .= "limit " . $limit . "\n";
        }

        return $query;
    } // build_select_query
} // PostgreSQL_DBWorker
