<?php
/**
 * This file contains the implementation of the abstract base class DBWorker
 * for the MySQL database using the extension mysqli.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\DatabaseWorkers;

use function \SmartFactory\debugger;

/**
 * This is the class for the MySQL database using the extension mysqli.
 *
 * This is a wrapper around the database connectivity. It offers an universal
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
class MySQL_DBWorker extends DBWorker
{
    /**
     * Stores the resource handle of the opened connection.
     *
     * @var resource
     *
     * @author Oleg Schildt
     */
    protected $connection = null;

    /**
     * Flag for setting the connection to read only.
     *
     * @var bool $read_only
     *
     * @author Oleg Schildt
     */
    protected bool $read_only = false;

    /**
     * Internal MySQLi object.
     *
     * @var ?\MySQLi
     *
     * @author Oleg Schildt
     */
    protected ?\MySQLi $mysqli = null;

    /**
     * Internal mysqli_result object.
     *
     * @var bool|null|\mysqli_result
     *
     * @author Oleg Schildt
     */
    protected bool|null|\mysqli_result $mysqli_result = null;

    /**
     * Internal mysqli_stmt object.
     *
     * @var ?\mysqli_stmt
     *
     * @author Oleg Schildt
     */
    protected ?\mysqli_stmt $statement = null;

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
     * @var ?array
     *
     * @author Oleg Schildt
     */
    protected ?array $row = null;

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
     * @return MySQL_DBWorker
     * It throws an exception in the case of any errors.
     *
     * Returns the clone of this dbworker.
     *
     * @author Oleg Schildt
     */
    public function create_clone(): MySQL_DBWorker
    {
        $cln = new MySQL_DBWorker();

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
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
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
            $this->db_port = $parameters["db_port"];
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
     * Checks whether the extension (mysqli) is installed which is required for work with
     * the MySQL database.
     *
     * @return bool
     * The method should return true if the extension is installed, otherwise false.
     *
     * @see MySQL_DBWorker::get_extension_name()
     *
     * @author Oleg Schildt
     */
    public function is_extension_installed(): bool
    {
        if (!class_exists("MySQLi")) {
            return false;
        }

        return true;
    } // is_extension_installed

    /**
     * Returns the name of the required PHP extension - "mysqli".
     *
     * @return string
     * Returns the name of the required PHP extension - "mysqli".
     *
     * @see MySQL_DBWorker::is_extension_installed()
     *
     * @author Oleg Schildt
     */
    public function get_extension_name(): string
    {
        return "mysqli";
    } // get_extension_name

    /**
     * Returns the name of the supported database - "MySQL Server".
     *
     * @return string
     * Returns the name of the supported database - "MySQL Server".
     *
     * @author Oleg Schildt
     */
    public function get_rdbms_name(): string
    {
        return "MySQL Server";
    } // get_rdbms_name

    /**
     * Returns the connection state.
     *
     * @return bool
     * Returns true if the connection is open, otherwise false.
     *
     * @see DBWorker::check_connection()
     * @see MySQL_DBWorker::connect()
     * @see MySQL_DBWorker::close_connection()
     *
     * @author Oleg Schildt
     */
    public function is_connected(): bool
    {
        return (!empty($this->mysqli) && empty($this->mysqli->connect_error));
    } // is_connected

    /**
     * Establishes the connection to the database using the connection
     * settings parameters specified by the initialization.
     *
     * @return void
     *
     * @throws \Throwable|DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see DBWorker::check_connection()
     * @see MySQL_DBWorker::is_connected()
     * @see MySQL_DBWorker::close_connection()
     *
     * @author Oleg Schildt
     */
    public function connect(): void
    {
        if ($this->is_connected()) {
            return;
        }

        mysqli_report(MYSQLI_REPORT_STRICT);

        if (!$this->mysqli) {
            if (empty($this->db_server) || empty($this->db_user) || empty($this->db_password)) {
                throw new DBWorkerException("Connection data is incomplete", DBWorker::ERR_CONNECTION_DATA_INCOMPLETE);
            }

            try {
                $this->mysqli = @new \MySQLi;

                $this->mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 20);
                $this->mysqli->options(MYSQLI_OPT_READ_TIMEOUT, 20);

                $port = null;
                if (!empty($this->db_port)) {
                    $port = $this->db_port;
                }

                $this->mysqli->real_connect($this->db_server, $this->db_user, $this->db_password, null, $port);
            } catch (\mysqli_sql_exception $ex) {
                $err = $ex->getMessage();

                throw match ($ex->getCode()) {
                    2002 => new DBWorkerException($err, DBWorker::ERR_HOST_UNREACHABLE),
                    1045 => new DBWorkerException($err, DBWorker::ERR_WRONG_USER_CREDENTIALS),
                    default => new DBWorkerException($err, DBWorker::ERR_CONNECTION_FAILED)
                };
            }
        }

        if (!empty($this->db_name)) {
            try {
                $this->use_database($this->db_name);
            } catch (\Throwable $ex) {
                $this->connection = null;
                throw $ex;
            }
        }

        try {
            $this->execute_query("set charset utf8");

            if (!empty($this->read_only)) {
                $this->execute_query("set transaction read only");
                $this->execute_query("start transaction");
            }
        } catch (\mysqli_sql_exception $ex) {
            $err = $ex->getMessage();
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED);
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
        $this->check_connection();

        $this->db_name = $db_name;

        try {
            if (!$this->mysqli->select_db($this->db_name)) {
                throw new \mysqli_sql_exception($this->mysqli->error);
            }
        } catch (\mysqli_sql_exception $ex) {
            $err = $ex->getMessage();
            throw new DBWorkerException($err, DBWorker::ERR_DATABASE_NOT_FOUND);
        }
    } // use_database

    /**
     * Returns the name of the database schema if applicable.
     *
     * @return string
     * Returns the name of the database schema if applicable, or an empty string.
     *
     * @see MySQL_DBWorker::qualify_name_with_schema()
     *
     * @author Oleg Schildt
     */
    public function get_schema(): string
    {
        return "";
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
     * @see MySQL_DBWorker::get_schema()
     *
     * @author Oleg Schildt
     */
    public function qualify_name_with_schema(string $name): string
    {
        return $name;
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

        $this->mysqli_result = @$this->mysqli->query($query_string);
        if (!$this->mysqli_result) {
            throw new DBWorkerException($this->mysqli->error, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
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
     * @see MySQL_DBWorker::execute_prepared_query()
     * @see MySQL_DBWorker::free_prepared_query()
     *
     * @author Oleg Schildt
     */
    public function prepare_query(string $query_string): void
    {
        $this->check_connection();

        if ($this->statement) {
            $this->statement->close();
            $this->statement = null;
        }

        $this->last_query = $query_string;
        $this->prepared_query = $query_string;

        try {
            $this->statement = $this->mysqli->prepare($query_string);
        } catch (\mysqli_sql_exception $ex) {
            $err = $ex->getMessage();
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
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
            $this->statement->close();
            $this->statement = null;
        }

        $this->last_query = $query_string;
        $this->prepared_query = $query_string;

        try {
            $this->statement = $this->mysqli->prepare($query_string);

            $null = null;
            $this->statement->bind_param("b", $null);

            while (!feof($stream)) {
                $this->statement->send_long_data(0, fread($stream, 8192));
            }

            $this->statement->execute();
        } catch (\mysqli_sql_exception $ex) {
            $err = $ex->getMessage();
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        } finally {
            fclose($stream);

            if ($this->statement) {
                $this->statement->close();
            }
            $this->statement = null;
        }
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
     * @see MySQL_DBWorker::prepare_query()
     * @see MySQL_DBWorker::free_prepared_query()
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

        $parameters = [];
        $parameters[0] = "";

        $counter = 1;
        foreach ($args as &$argval) {
            if ($argval === null) {
                $parameters[0] .= "i";
                $parameters[$counter] = &$argval;

                $this->last_query = preg_replace("/\\?/", "null", $this->last_query, 1);
            } elseif (is_int($argval)) {
                $parameters[0] .= "i";
                $parameters[$counter] = &$argval;

                $this->last_query = preg_replace("/\\?/", $argval, $this->last_query, 1);
            } elseif (is_float($argval)) {
                $parameters[0] .= "d";
                $parameters[$counter] = &$argval;

                $this->last_query = preg_replace("/\\?/", $argval, $this->last_query, 1);
            } else {
                $parameters[0] .= "s";
                $parameters[$counter] = &$argval;

                $this->last_query = preg_replace("/\\?/", \SmartFactory\preg_r_escape("'" . $this->escape($argval) . "'"), $this->last_query, 1);
            }

            $counter++;
        }

        if ($this->logging) {
            debugger()->debugMessage($this->last_query, "sql.log");
        }

        if (count($args) > 0 && !call_user_func_array([$this->statement, 'bind_param'], $parameters)) {
            $err = "Number of elements in type definition string doesn't match number of bind variables.";
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        if (!$this->statement->execute()) {
            throw new DBWorkerException($this->statement->error, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        if (!$this->statement->store_result()) {
            throw new DBWorkerException($this->statement->error, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        $this->mysqli_result = $this->statement->result_metadata();
    } // execute_prepared_query

    /**
     * Executes the SQL stored procedure.
     *
     * @param string $procedure
     * The name of the SQL stored procedure.
     *
     * All subsequent parameters are the paramteres of the SQL stored procedure.
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

        if (is_object($this->mysqli_result) && get_class($this->mysqli_result) == "mysqli_result") {
            $this->mysqli_result->free_result();

            $this->mysqli_result = null;
        }
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
     * @see MySQL_DBWorker::prepare_query()
     * @see MySQL_DBWorker::execute_prepared_query()
     *
     * @author Oleg Schildt
     */
    public function free_prepared_query(): void
    {
        $this->check_connection();

        try {
            $this->statement?->close();
        } catch (\mysqli_sql_exception $ex) {
            $err = $ex->getMessage();
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        } finally {
            $this->statement = null;
            $this->last_query = null;
            $this->prepared_query = null;
        }
    } // free_prepared_query

    /**
     * Closes the currently opened connection.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see MySQL_DBWorker::is_connected()
     * @see MySQL_DBWorker::connect()
     *
     * @author Oleg Schildt
     */
    public function close_connection(): void
    {
        $this->last_query = null;
        $this->prepared_query = null;
        $this->row = null;
        $this->field_names = null;

        try {
            if ($this->statement) {
                @$this->statement->close();
            }

            if ($this->mysqli) {
                @$this->mysqli->close();
            }
        } catch (\mysqli_sql_exception $ex) {
            $err = $ex->getMessage();
            throw new DBWorkerException($err, DBWorker::ERR_CONNECTION_FAILED);
        } finally {
            $this->statement = null;
            $this->mysqli = null;
            $this->mysqli_result = null;
        }
    } // close_connection

    /**
     * Starts the translation.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see MySQL_DBWorker::commit_transaction()
     * @see MySQL_DBWorker::rollback_transaction()
     *
     * @author Oleg Schildt
     */
    public function start_transaction(): void
    {
        $this->check_connection();

        $this->execute_query("BEGIN");
    } // start_transaction

    /**
     * Commits the translation.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see MySQL_DBWorker::start_transaction()
     * @see MySQL_DBWorker::rollback_transaction()
     *
     * @author Oleg Schildt
     */
    public function commit_transaction(): void
    {
        $this->check_connection();

        $this->execute_query("COMMIT");
    } // commit_transaction

    /**
     * Rolls back the translation.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see MySQL_DBWorker::start_transaction()
     * @see MySQL_DBWorker::commit_transaction()
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

        try {
            if (is_object($this->mysqli_result) && get_class($this->mysqli_result) == "mysqli_result") {
                $this->mysqli_result->free_result();

                $this->mysqli_result = null;
            }
        } catch (\mysqli_sql_exception $ex) {
            $err = $ex->getMessage();
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        } finally {
            $this->row = null;
            $this->field_names = null;
            $this->last_query = null;
        }
    } // free_result

    /**
     * Returns the value of the auto increment field by the last insertion.
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

        return $this->mysqli->insert_id;
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

        try {
            if ($this->statement) {
                if (!$this->field_names) {
                    $this->row = [];

                    $fcnt = $this->statement->field_count;
                    if ($fcnt == 0) {
                        return false;
                    }

                    $params = [];
                    for ($i = 0; $i < $fcnt; $i++) {
                        $finfo = $this->field_info_by_num($i);
                        if (empty($finfo)) {
                            return false;
                        }

                        $this->field_names[] = $finfo["name"];

                        $this->row[$finfo["name"]] = "";
                        $params[] = &$this->row[$finfo["name"]];
                    }

                    call_user_func_array([$this->statement, 'bind_result'], $params);

                    unset($params);
                }

                $result = $this->statement->fetch();
                if (!$result) {
                    $this->row = null;
                }

                return !empty($result);
            } // prepared query

            if (!$this->mysqli_result) {
                $err = "Result is empty!";
                throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
            }

            $this->row = @$this->mysqli_result->fetch_assoc();

            if (!$this->row) {
                return false;
            }

            if (!$this->field_names) {
                $this->field_names = array_keys($this->row);
            }
        } catch (\mysqli_sql_exception $ex) {
            $err = $ex->getMessage();
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
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
        $row = [];

        try {
            if ($this->statement) {
                $fcnt = $this->statement->field_count;
                if ($fcnt == 0) {
                    return false;
                }

                for ($i = 0; $i < $fcnt; $i++) {
                    $finfo = $this->field_info_by_num($i);
                    if (empty($finfo)) {
                        return false;
                    }

                    $this->field_names[] = $finfo["name"];

                    $row[$finfo["name"]] = "";
                }

                $params = [];
                foreach ($this->field_names as $fname) {
                    $params[] = &$row[$fname];
                }

                call_user_func_array([$this->statement, 'bind_result'], $params);

                unset($params);

                while ($this->statement->fetch()) {
                    $counter++;

                    // we must create a copy from $row, because its
                    // items are bound per reference
                    $rowcpy = array_map(function ($val) {
                        return $val;
                    }, $row);

                    if (empty($dimension_keys)) {
                        $rows[] = $rowcpy;
                    } else {
                        $dimensions = array_intersect_key($rowcpy, $dimension_keys);
                        $rowcpy = array_diff_key($rowcpy, $dimension_keys);

                        $reference = &$rows;

                        foreach ($dimensions as $dval) {
                            if (empty($reference[$dval])) {
                                $reference[$dval] = [];
                            }
                            $reference = &$reference[$dval];
                        }

                        $reference = $rowcpy;

                        unset($reference);
                    }
                }

                return $counter;
            } // prepared query

            if (!$this->mysqli_result) {
                $err = "Result is empty!";
                throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
            }

            while ($row = @$this->mysqli_result->fetch_assoc()) {
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

        } catch (\mysqli_sql_exception $ex) {
            $err = $ex->getMessage();
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }
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

        if ($this->statement) {
            return $this->statement->num_rows;
        }

        if (!$this->mysqli_result || !is_object($this->mysqli_result)) {
            $err = "Result fetch error";
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        return $this->mysqli_result->num_rows;
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

        if (!$this->mysqli_result) {
            $err = "Result fetch error";
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        return $this->mysqli->affected_rows;
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

        if ($this->statement) {
            return $this->statement->field_count;
        }

        if (!$this->mysqli_result || !is_object($this->mysqli_result)) {
            $err = "Result fetch error";
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        return $this->mysqli_result->field_count;
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
     * @see MySQL_DBWorker::field_by_num()
     * @see MySQL_DBWorker::field_name()
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
     * @see MySQL_DBWorker::field_by_name()
     * @see MySQL_DBWorker::field_info_by_num()
     * @see MySQL_DBWorker::field_name()
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
     * @see MySQL_DBWorker::field_by_num()
     * @see MySQL_DBWorker::field_info_by_num()
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
     * @see MySQL_DBWorker::field_by_num()
     * @see MySQL_DBWorker::field_name()
     *
     * @author Oleg Schildt
     */
    public function field_info_by_num(int $num): ?array
    {
        $this->check_connection();

        if (!$this->mysqli_result) {
            $err = "Result fetch error";
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        try {
            $res = @$this->mysqli_result->fetch_field_direct($num);
        } catch (\mysqli_sql_exception $ex) {
            $err = $ex->getMessage();
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        // some corrections for compatible format

        $mysqli_type = [];

        $mysqli_type[0] = "decimal";
        $mysqli_type[1] = "tinyint";
        $mysqli_type[2] = "smallint";
        $mysqli_type[3] = "integer";
        $mysqli_type[4] = "float";
        $mysqli_type[5] = "double";

        $mysqli_type[7] = "timestamp";
        $mysqli_type[8] = "bigint";
        $mysqli_type[9] = "mediumint";
        $mysqli_type[10] = "date";
        $mysqli_type[11] = "time";
        $mysqli_type[12] = "datetime";
        $mysqli_type[13] = "year";
        $mysqli_type[14] = "date";

        $mysqli_type[16] = "bit";

        $mysqli_type[246] = "decimal";
        $mysqli_type[247] = "enum";
        $mysqli_type[248] = "set";
        $mysqli_type[249] = "tinyblob";
        $mysqli_type[250] = "mediumblob";
        $mysqli_type[251] = "longblob";
        $mysqli_type[252] = "blob";
        $mysqli_type[253] = "varchar";
        $mysqli_type[254] = "char";
        $mysqli_type[255] = "geometry";

        $field_info = [];

        $field_info["name"] = $res->name;
        $field_info["type"] = $res->type;
        $field_info["size"] = $res->length;

        $field_info["binary"] = 0;
        $field_info["numeric"] = 0;

        if (!empty($field_info["type"])) {
            if (in_array($field_info["type"], [0, 1, 2, 3, 4, 5, 7, 8, 9, 13, 16, 246])) {
                $field_info["numeric"] = 1;
            }
            elseif (in_array($field_info["type"], [252]) && !($res->flags & MYSQLI_BINARY_FLAG)) {
                $field_info["string"] = 1;
            } 
            elseif (in_array($field_info["type"], [249, 250, 251, 252])) {
                $field_info["binary"] = 1;
            }
            elseif (in_array($field_info["type"], [12, 14])) {
                $field_info["datetime"] = 1;
            }
            elseif (in_array($field_info["type"], [253, 254])) {
                $field_info["string"] = 1;
            }

            if (isset($mysqli_type[$field_info["type"]])) {
                $field_info["type"] = $mysqli_type[$field_info["type"]];
            } else {
                $field_info["type"] = "undefined";
            }
        }

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
     * @see MySQL_DBWorker::format_date()
     * @see MySQL_DBWorker::format_datetime()
     *
     * @author Oleg Schildt
     */
    public function escape(string $str): string
    {
        return preg_replace("/(['\\\\])/", "\\\\$1", $str);
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
     * @see MySQL_DBWorker::escape()
     * @see MySQL_DBWorker::format_datetime()
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
     * @see MySQL_DBWorker::escape()
     * @see MySQL_DBWorker::format_date()
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
     * It might throw an exception in the case of any errors.
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
                DBWorker::DB_GEOMETRY => "ST_GeomFromText('" . $this->escape($value) . "')",
                DBWorker::DB_GEOMETRY_4326 => "ST_GeomFromText('" . $this->escape($value) . "', 4326, 'axis-order=lat-long')",
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
     * The limit how many records shoud be loaded. 0 for unlimited.
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

        if (!empty($order_clause)) {
            $query .= $order_clause . "\n";
        }

        if (!empty($limit)) {
            $query .= "limit " . $limit . "\n";
        }

        return $query;
    } // build_select_query
} // MySQL_DBWorker
