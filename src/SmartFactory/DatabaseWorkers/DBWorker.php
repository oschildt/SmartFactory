<?php
/**
 * This file contains the declaration of the abstract base class DBWorker
 * for all dbworkers for different databases.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\DatabaseWorkers;

use \SmartFactory\Interfaces\IInitable;

/**
 * This is the abstract base class for all dbworkers for different databases.
 *
 * This is a wrapper around the database connectivity. It offers a universal
 * common way for working with databases of different types. Currently, MySQL and
 * MS SQL are supported. If in the future, there will be a better solution, or
 * the current solution turns out to be inefficient in a new version of PHP,
 * we can easily reimplement the DB wrapper without touching the business logic code.
 * Adding support for new database types is also much easier with this wrapping approach.
 *
 * @author Oleg Schildt
 */
abstract class DBWorker implements IInitable
{
    /**
     * The constant for the error: connection data is incomplete.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_CONNECTION_DATA_INCOMPLETE = "err_connection_data_incomplete";
    
    /**
     * The constant for the error: connection to the server failed.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_CONNECTION_FAILED = "err_connection_failed";

    /**
     * The constant for the error: host not reachable.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_HOST_UNREACHABLE = "err_host_unreachable";

    /**
     * The constant for the error: wrong user credentials.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_WRONG_USER_CREDENTIALS = "err_wrong_user_credentials";

    /**
     * The constant for the error: database not found.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_DATABASE_NOT_FOUND = "err_database_not_found";
    
    /**
     * The constant for the error: query failed.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_QUERY_FAILED = "err_query_failed";

    /**
     * The constant for the error: not connected.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_NOT_CONNECTED = "err_not_connected";

    /**
     * The constant for the error: stream invalid.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_STREAM_ERROR = "err_stream_error";

    /**
     * The constant for the error: wrong data format.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_WRONG_DATA_FORMAT = "err_data_format";

    /**
     * The constant for the returning field value as is.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    const int DB_AS_IS = 0;

    /**
     * The constant for the number type.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    const int DB_NUMBER = 1;

    /**
     * The constant for the string type.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    const int DB_STRING = 2;

    /**
     * The constant for the date type.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    const int DB_DATE = 3;

    /**
     * The constant for the date/time type.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    const int DB_DATETIME = 4;

    /**
     * The constant for the type geometry SRID 0.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    const int DB_GEOMETRY = 5;
    
    /**
     * The constant for the type geometry SRID 4326 (latitude, longitude).
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    const int DB_GEOMETRY_4326 = 6;

    /**
     * The constant for the large objects.
     *
     * If a RDBMS supports stream reading, it is performed.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    const int DB_LARGE_OBJECT_STREAM = 7;

    /**
     * Name or IP address of the server.
     *
     * @var string $db_server
     *
     * @author Oleg Schildt
     */
    protected string $db_server;

    /**
     * Port of the server.
     *
     * @var string $db_port
     *
     * @author Oleg Schildt
     */
    protected string $db_port;

    /**
     * Name of the database.
     *
     * @var string $db_name
     *
     * @author Oleg Schildt
     */
    protected string $db_name;
    
    /**
     * Name of the database user.
     *
     * @var string $db_user
     *
     * @author Oleg Schildt
     */
    protected string $db_user;
    
    /**
     * Password of the database user.
     *
     * @var string $db_password
     *
     * @author Oleg Schildt
     */
    protected string $db_password;
    
    /**
     * This variable stores the last executed query.
     *
     * @var ?string $last_query
     *
     * @author Oleg Schildt
     */
    protected ?string $last_query = null;
    
    /**
     * This variable stores the logging flag.
     *
     * @var bool $logging
     *
     * @author Oleg Schildt
     */
    protected bool $logging = false;
    /**
     * Flag property for storing the state whether it is clone or not.
     *
     * @var bool $is_clone
     *
     * @author Oleg Schildt
     */
    protected bool $is_clone = false;
    
    /**
     * Initializes the dbworker with connection parameters.
     *
     * @param array $parameters
     * The parameters may vary for each database.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    abstract public function init(array $parameters): void;
    
    /**
     * Checks whether the extension is installed which is required for work with
     * the corresponding database.
     *
     * @return bool
     * The method should return true if the extension is installed, otherwise false.
     *
     * @see DBWorker::get_extension_name()
     *
     * @author Oleg Schildt
     */
    abstract public function is_extension_installed(): bool;
    
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
     * Creates a clone of the dbworker that is using the same open
     * connection.
     *
     * This might be useful if you want to execute some additional
     * queries while iteration through the active results of a select query.
     *
     * @return DBWorker
     * Returns the clone of this dbworker.
     *
     * @author Oleg Schildt
     */
    abstract public function create_clone(): DBWorker;
    
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
     * Check whether the connection exists and throws an exceptions if not.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception if there is no connection.
     *
     * @see DBWorker::connect()
     * @see DBWorker::is_connected()
     * @see DBWorker::close_connection()
     *
     * @author Oleg Schildt
     */
    public function check_connection(): void
    {
        if (!$this->is_connected()) {
            $err = "Database server not connected!";
            trigger_error($err, E_USER_WARNING);
            throw new DBWorkerException($err, DBWorker::ERR_NOT_CONNECTED);
        }
    } // check_connection

    /**
     * Establishes the connection to the database using the connection
     * settings parameters specified by the initialization.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * - if some parameters are missing.
     *
     * @see DBWorker::check_connection()
     * @see DBWorker::is_connected()
     * @see DBWorker::close_connection()
     *
     * @author Oleg Schildt
     */
    abstract public function connect(): void;
    
    /**
     * Sets the database as working database.
     *
     * @param string $db_name
     * The name of the database to be set as working database.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    abstract public function use_database(string $db_name): void;
    
    /**
     * Returns the name of the database schema if applicable.
     *
     * @return string
     * Returns the name of the database schema if applicable, or an empty string.
     *
     * @see DBWorker::qualify_name_with_schema()
     *
     * @author Oleg Schildt
     */
    abstract public function get_schema(): string;
    
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
     * @see DBWorker::get_schema()
     *
     * @author Oleg Schildt
     */
    abstract public function qualify_name_with_schema(string $name): string;
    
    /**
     * Executes the SQL query.
     *
     * @param string $query_string
     * The SQL query to be executed.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    abstract public function execute_query(string $query_string): void;
    
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
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    abstract public function execute_procedure(string $procedure /* arg list */): void;
    
    /**
     * Executes the prepared SQL query.
     *
     * @param mixed ...$args
     * The number of parameters may vary and be zero. An array can also be passed.
     * These are parameters of the prepared query.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @see DBWorker::prepare_query()
     * @see DBWorker::free_prepared_query()
     *
     * @author Oleg Schildt
     */
    abstract public function execute_prepared_query(...$args): void;
    
    /**
     * Prepares the SQL query with bindable variables.
     *
     * @param string $query_string
     * The SQL query to be prepared.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @see DBWorker::execute_prepared_query()
     * @see DBWorker::free_prepared_query()
     *
     * @author Oleg Schildt
     */
    abstract public function prepare_query(string $query_string): void;
    
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
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    abstract public function stream_long_data(string $query_string, $stream): void;
    
    /**
     * Closes the currently opened connection.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @see DBWorker::is_connected()
     * @see DBWorker::check_connection()
     * @see DBWorker::connect()
     *
     * @author Oleg Schildt
     */
    abstract public function close_connection(): void;
    
    /**
     * Starts the translation.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @see DBWorker::commit_transaction()
     * @see DBWorker::rollback_transaction()
     *
     * @author Oleg Schildt
     */
    abstract public function start_transaction(): void;
    
    /**
     * Commits the transation.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @see DBWorker::start_transaction()
     * @see DBWorker::rollback_transaction()
     *
     * @author Oleg Schildt
     */
    abstract public function commit_transaction(): void;
    
    /**
     * Rolls back the translation.
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @return void
     *
     * @see DBWorker::start_transaction()
     * @see DBWorker::commit_transaction()
     *
     * @author Oleg Schildt
     */
    abstract public function rollback_transaction(): void;
    
    /**
     * Frees the result of the previously executed retrieving query.
     *
     * It should be called only for the retrieving queries.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    abstract public function free_result(): void;
    
    /**
     * Frees the prepared query.
     *
     * It should be called after all executions of the prepared query.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @see DBWorker::prepare_query()
     * @see DBWorker::execute_prepared_query()
     *
     * @author Oleg Schildt
     */
    abstract public function free_prepared_query(): void;
    
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
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    abstract public function fetch_row(): bool;
    
    /**
     * Fetches all rows from the result into an array.
     *
     * @param array &$rows
     * Target array for loading the results.
     *
     * @param array|null $dimension_keys
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
     * Returns the number of the fetched rows. It might be also 0. In the case of
     * any error returns false.
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    abstract public function fetch_array(array &$rows, ?array $dimension_keys = null): int;
    
    /**
     * Returns the number of the rows fetched by the last retrieving query.
     *
     * @return int
     * Returns the number of the rows fetched by the last retrieving query.
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    abstract public function fetched_count(): int;
    
    /**
     * Returns the number of the rows affected by the last modification query.
     *
     * @return int
     * Returns the number of the rows affected by the last modification query.
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    abstract public function affected_count(): int;
    
    /**
     * Returns the number of the fields in the result of the last retrieving query.
     *
     * @return int
     * Returns the number of the fields in the result of the last retrieving query.
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    abstract public function field_count(): int;
    
    /**
     * Returns the value of the auto increment field by the last insertion.
     *
     * @return ?int
     * Returns the value of the auto increment field by the last insertion.
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    abstract public function insert_id(): ?int;
    
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
     * @see DBWorker::field_by_num()
     * @see DBWorker::field_name()
     *
     * @author Oleg Schildt
     */
    abstract public function field_by_name(string $name, int $type = self::DB_AS_IS): mixed;

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
     * @see DBWorker::field_by_name()
     * @see DBWorker::field_info_by_num()
     * @see DBWorker::field_name()
     *
     * @author Oleg Schildt
     */
    abstract public function field_by_num(int $num, int $type = self::DB_AS_IS): mixed;
    
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
     * @see DBWorker::field_by_num()
     * @see DBWorker::field_name()
     *
     * @author Oleg Schildt
     */
    abstract public function field_info_by_num(int $num): ?array;
    
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
     * @see DBWorker::field_by_num()
     * @see DBWorker::field_info_by_num()
     *
     * @author Oleg Schildt
     */
    abstract public function field_name(int $num): ?string;
    
    /**
     * Escapes the string so that it can be used in the query without causing an error.
     *
     * @param string $str
     * The string to be escaped.
     *
     * @return string
     * Returns the escaped string.
     *
     * @see DBWorker::format_date()
     * @see DBWorker::format_datetime()
     * @see DBWorker::quotes_or_null()
     * @see DBWorker::number_or_null()
     *
     * @author Oleg Schildt
     */
    abstract public function escape(string $str): string;

    /**
     * Escapes the string so that it can be used in the query without causing an error or returns the string NULL if the string is empty.
     *
     * @param string $str
     * The string to be escaped.
     *
     * @return string
     * Returns the escaped string.
     *
     * @see DBWorker::escape()
     * @see DBWorker::format_date()
     * @see DBWorker::format_datetime()
     * @see DBWorker::number_or_null()
     *
     * @author Oleg Schildt
     */
    function quotes_or_null(string $str): string
    {
        return $str === "" ? "null" : "'" . $this->escape($str) . "'";
    }

    /**
     * Checks that the value is a number and returns it, or returns the string NULL if the value is empty.
     *
     * @param string $str
     * The string to be escaped.
     *
     * @return float|int|string
     * Returns the escaped string.
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @see DBWorker::escape()
     * @see DBWorker::format_date()
     * @see DBWorker::format_datetime()
     * @see DBWorker::quotes_or_null()
     *
     * @author Oleg Schildt
     */
    function number_or_null(string $str): float|int|string
    {
        if (empty($str) && $str != "0") {
            return "null";
        }

        if (!is_numeric($str)) {
            throw new DBWorkerException("Value '$str' is not a number!", DBWorker::ERR_WRONG_DATA_FORMAT);
        }

        return $str;
    }

    /**
     * Formats the date to a string compatible for the corresponding database.
     *
     * @param int $date
     * The date value as timestamp.
     *
     * @return string
     * Returns the string representation of the date compatible for the corresponding database.
     *
     * @see DBWorker::escape()
     * @see DBWorker::format_datetime()
     * @see DBWorker::quotes_or_null()
     * @see DBWorker::number_or_null()
     *
     * @author Oleg Schildt
     */
    abstract public function format_date(int $date): string;
    
    /**
     * Formats the date/time to a string compatible for the corresponding database.
     *
     * @param int $datetime
     * The date/time value as timestamp.
     *
     * @return string
     * Returns the string representation of the date/time compatible for the corresponding database.
     *
     * @see DBWorker::escape()
     * @see DBWorker::format_date()
     * @see DBWorker::quotes_or_null()
     * @see DBWorker::number_or_null()
     *
     * @author Oleg Schildt
     */
    abstract public function format_datetime(int $datetime): string;
    
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
     * @author Oleg Schildt
     */
    abstract function prepare_for_query(mixed $value, int $type): string;

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
    abstract function build_select_query(string $table, array $fields, string $where_clause, string $order_clause, int $limit): string;

    /**
     * Returns the last executed query.
     *
     * @return string
     * Returns the last executed query.
     *
     * @author Oleg Schildt
     */
    function get_last_query(): string
    {
        return trim($this->last_query);
    } // get_last_query

    /**
     * Sets the logging flag. If set, query is logged to a log file.
     *
     * @param bool $state
     * The state of the logging.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    function enable_logging(bool $state): void
    {
        $this->logging = $state;
    } // enable_logging
} // class DBWorker
