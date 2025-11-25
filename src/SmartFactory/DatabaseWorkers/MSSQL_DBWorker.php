<?php
/**
 * This file contains the implementation of the abstract base class DBWorker
 * for the MS SQL database using the extension mysqli.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\DatabaseWorkers;

use function \SmartFactory\debugger;

/**
 * This is the class for the MS SQL database using the extension sqlsrv.
 *
 * This is a wrapper around the database connectivity. It offers a universal
 * common way for working with databases of different types. Currently, MySQL, PostgreSQL and
 * MS SQL are supported. If in the future, there will be a better solution, or
 * the current solution turns out to be inefficient in a new version of PHP,
 * we can easily reimplement the DB wrapper without touching the business logic code.
 * Adding support for new database types is also much easier with this wrapping approach.
 *
 * @see PostgreSQL_DBWorker
 * @see MySQL_DBWorker
 *
 * @author Oleg Schildt
 */
class MSSQL_DBWorker extends DBWorker
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
     * Stores the resource handle of the statement
     * of the last executed query.
     *
     * @var resource
     *
     * @author Oleg Schildt
     */
    protected $statement = null;

    /**
     * Internal variable for storing of the last prepared query.
     *
     * @var ?string
     *
     * @author Oleg Schildt
     */
    protected ?string $prepared_query = null;

    /**
     * Stores the state whether the last query was an insert or not.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $last_query_is_insert = false;

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
     * Internal variable for storing of the parameters
     * of the last prepared query.
     *
     * @var ?array
     *
     * @author Oleg Schildt
     */
    protected ?array $query_parameters = null;

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
        $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);
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
     * Creates a clone of the dbworker that is using the same open
     * connection.
     *
     * This might be useful if you want to execute some additional
     * queries while iteration through the active results of a select query.
     *
     * @return MSSQL_DBWorker
     * Returns the clone of this dbworker.
     *
     * @author Oleg Schildt
     */
    public function create_clone(): MSSQL_DBWorker
    {
        $cln = new MSSQL_DBWorker();

        $cln->is_clone = true;

        $cln->db_server = $this->db_server;
        $cln->db_port = $this->db_port;
        $cln->db_name = $this->db_name;
        $cln->db_user = $this->db_user;
        $cln->db_password = $this->db_password;

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
            $this->db_server = $parameters["db_port"];
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
    } // init

    /**
     * Checks whether the extension (sqlsrv) is installed which is required for work with
     * the MySQL database.
     *
     * @return bool
     * The method should return true if the extension is installed, otherwise false.
     *
     * @see MSSQL_DBWorker::get_extension_name()
     *
     * @author Oleg Schildt
     */
    public function is_extension_installed(): bool
    {
        return function_exists("sqlsrv_connect");
    } // is_extension_installed

    /**
     * Returns the name of the required PHP extension - "sqlsrv".
     *
     * @return string
     * Returns the name of the required PHP extension - "sqlsrv".
     *
     * @see MSSQL_DBWorker::is_extension_installed()
     *
     * @author Oleg Schildt
     */
    public function get_extension_name(): string
    {
        return "sqlsrv";
    } // get_extension_name

    /**
     * Returns the name of the supported database - "Microsoft SQL Server".
     *
     * @return string
     * Returns the name of the supported database - "Microsoft SQL Server".
     *
     * @author Oleg Schildt
     */
    public function get_rdbms_name(): string
    {
        return "Microsoft SQL Server";
    } // get_rdbms_name

    /**
     * Returns the connection state.
     *
     * @return bool
     * Returns true if the connection is open, otherwise false.
     *
     * @see DBWorker::check_connection()
     * @see MSSQL_DBWorker::connect()
     * @see MSSQL_DBWorker::close_connection()
     *
     * @author Oleg Schildt
     */
    public function is_connected(): bool
    {
        return (!empty($this->connection) && is_resource($this->connection));
    } // is_connected

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
     * @see MSSQL_DBWorker::is_connected()
     * @see MSSQL_DBWorker::close_connection()
     *
     * @author Oleg Schildt
     */
    public function connect(): void
    {
        if ($this->is_connected()) {
            return;
        }

        sqlsrv_configure("WarningsReturnAsErrors", 0);

        $this->last_query = null;

        if (!isset($this->connection) || !$this->connection) {
            if (empty($this->db_server) || empty($this->db_user) || empty($this->db_password)) {
                throw new DBWorkerException("Connection data is incomplete", DBWorker::ERR_CONNECTION_DATA_INCOMPLETE);
            }

            /*
            This new MSSQL dirver has a BIG problem. It sticks to the encoding
            of the Windows and ignores the database and server collation.
      
            If you use the UTF-8, you have two options:
      
            1. Use NCHAR etc. types
            2. Store the UTF-8 strings as is into the 1-byte fields like varchar etc.
      
            The variant 1 might be undesirable because of performance.
      
            The variant 2 might cause problems in contrast to the old MSSQL drivers.
            E.g. if you have the Russian locale on your Windows, but the Latin collation
            in the database, you have no chance for the variant 2. The driver will convert
            the data, although it should not do that but send this as is!
      
            There is the option SQLSRV_ENC_BINARY that prevents any conversion, but it cannot
            be used globally and it cannot be used for simple queries. It can be used only for
            prepared queries. It would be so easy just to allow setting SQLSRV_ENC_BINARY
            on connection level, but they did not do this!
      
            Thus, you have only the following choices:
      
            1. Store the unicode texts in NCHAR fields and use CharacterSet = UTF-8.
            2. Store the unicode texts in normal fields and use only parametrized queries.
               You have always to specify the type SQLSRV_ENC_BINARY for each paramter
               explicitly.
            3. Store the unicode texts in normal fields and ensure that the Windows locale
               and the SQL Server are identical.
      
            Beacause of universality of the DBWorker independent of the database type,
            I use the choise 3.
            */

            $config = [
                "UID" => $this->db_user,
                "PWD" => $this->db_password,
                //"CharacterSet" => "UTF-8",
                "ReturnDatesAsStrings" => true,
                "MultipleActiveResultSets" => false
            ];

            $this->connection = @sqlsrv_connect($this->db_server, $config);
        }

        if (!$this->connection) {
            $err = $this->sys_get_errors();
            $this->connection = null;

            throw new DBWorkerException($err, DBWorker::ERR_CONNECTION_FAILED);
        }

        if (!empty($this->db_name)) {
            try {
                $this->use_database($this->db_name);
            } catch (\Throwable $ex) {
                $this->connection = null;
                throw $ex;
            }
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

        $db_name = $this->escape($db_name);

        $this->execute_query("USE " . $db_name);
    } // use_database

    /**
     * Returns the name of the database schema if applicable.
     *
     * @return string
     * Returns the name of the database schema if applicable, or an empty string.
     *
     * @see MSSQL_DBWorker::qualify_name_with_schema()
     *
     * @author Oleg Schildt
     */
    public function get_schema(): string
    {
        return "dbo";
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
     * @see MSSQL_DBWorker::get_schema()
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

        $options = [];

        /*
        If the cursor is SQLSRV_CURSOR_STATIC or other than
        SQLSRV_CURSOR_FORWARD, retrieving of the data
        has sometimes very poor performance. Not the query execution,
        but the data retrieving!
    
        The default is SQLSRV_CURSOR_FORWARD, but it is not possible
        to get fetched_count by this type of cursor. So we sacrifice
        the possibility to get fetched_count for the performance.
        The performance is more important.
    
        no more relevant in new version of driver
        */

        if (preg_match("/\s*SELECT/i", $query_string)) {
            $options = ["Scrollable" => SQLSRV_CURSOR_STATIC];
        }

        $this->statement = @sqlsrv_query($this->connection, $query_string, [], $options);
        if (!$this->statement) {
            $err = $this->sys_get_errors();

            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
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
     * @see MSSQL_DBWorker::execute_prepared_query()
     * @see MSSQL_DBWorker::free_prepared_query()
     *
     * @author Oleg Schildt
     */
    public function prepare_query(string $query_string): void
    {
        $this->check_connection();

        if ($this->statement) {
            if (is_resource($this->statement)) {
                @sqlsrv_free_stmt($this->statement);
            }
        }

        $this->last_query = $query_string;
        $this->prepared_query = $query_string;

        $params = [];
        $this->query_parameters = [];

        $cnt = preg_match_all("/\\?/", $query_string);

        for ($i = 0; $i < $cnt; $i++) {
            $this->query_parameters[$i] = null;
            $params[$i] = &$this->query_parameters[$i];
        }

        $query_appendix = "";

        // to be able to get the insert id from prepared query
        // we have to add this appendix

        $this->last_query_is_insert = false;
        if (preg_match("/\s*INSERT/i", $query_string)) {
            $this->last_query_is_insert = true;

            $query_appendix = "; SELECT SCOPE_IDENTITY() AS IID";
        }

        $options = [];
        /*
        If the cursor is SQLSRV_CURSOR_STATIC or other than
        SQLSRV_CURSOR_FORWARD, retrieving of the data
        has sometimes very poor performance. Not the query execution,
        but the data retrieving!
    
        The default is SQLSRV_CURSOR_FORWARD, but it is not possible
        to get fetched_count by this type of cursor. So we sacrifice
        the possibility to get fetched_count for the performance.
        The performance is more important.
    
        if(preg_match("/\s*SELECT/i", $query_string))
        {
          $options = ["Scrollable" => SQLSRV_CURSOR_STATIC];
        }
        */

        $this->statement = @sqlsrv_prepare($this->connection, $query_string . $query_appendix, $params, $options);
        if (!$this->statement) {
            $err = $this->sys_get_errors();

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
            if (is_resource($this->statement)) {
                @sqlsrv_free_stmt($this->statement);
            }
        }

        $this->last_query = $query_string;
        $this->prepared_query = $query_string;

        $params = array(&$stream);

        $options = array("SendStreamParamsAtExec" => 0);

        $this->statement = @sqlsrv_prepare($this->connection, $query_string, $params, $options);
        if (!$this->statement) {
            fclose($stream);

            $err = $this->sys_get_errors();

            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        if (!@sqlsrv_execute($this->statement)) {
            $err = $this->sys_get_errors();

            if (is_resource($this->statement)) {
                @sqlsrv_free_stmt($this->statement);
            }
            $this->statement = null;
            fclose($stream);

            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        // Send up to 8K of parameter data to the server
        // with each call to sqlsrv_send_stream_data.
        while (sqlsrv_send_stream_data($this->statement)) {
        }

        if (is_resource($this->statement)) {
            @sqlsrv_free_stmt($this->statement);
        }
        $this->statement = null;
        fclose($stream);
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
     * @see MSSQL_DBWorker::prepare_query()
     * @see MSSQL_DBWorker::free_prepared_query()
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

        $counter = 0;
        foreach ($args as $argval) {
            $this->query_parameters[$counter] = $argval;
            if ($argval === null) {
                $this->last_query = preg_replace("/\\?/", "null", $this->last_query, 1);
            } elseif (is_int($argval)) {
                $this->last_query = preg_replace("/\\?/", $argval, $this->last_query, 1);
            } elseif (is_float($argval)) {
                $this->last_query = preg_replace("/\\?/", $argval, $this->last_query, 1);
            } else {
                $this->last_query = preg_replace("/\\?/", \SmartFactory\preg_r_escape("'" . $this->escape($argval) . "'"), $this->last_query, 1);
            }

            $counter++;
        }

        if ($this->logging) {
            debugger()->debugMessage($this->last_query, "sql.log");
        }

        if (!@sqlsrv_execute($this->statement)) {
            $err = $this->sys_get_errors();

            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
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

            $this->last_query = trim("EXEC $proc_name $arg_list");
        }

        $this->execute_query($this->last_query);

        // A stored procedure may generate many result objects.
        // We clear them, to prevent errors by subsequent select requests.

        if ($this->statement && is_resource($this->statement)) {
            @sqlsrv_cancel($this->statement);
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
     * @see MSSQL_DBWorker::prepare_query()
     * @see MSSQL_DBWorker::execute_prepared_query()
     *
     * @author Oleg Schildt
     */
    public function free_prepared_query(): void
    {
        $this->check_connection();

        if ($this->statement && is_resource($this->statement)) {
            @sqlsrv_free_stmt($this->statement);
        }

        $this->statement = null;
        $this->last_query = null;
        $this->prepared_query = null;
        $this->query_parameters = null;

        $this->last_query_is_insert = false;
    } // free_prepared_query

    /**
     * Closes the currently opened connection.
     *
     * @return void
     *
     * @see MSSQL_DBWorker::is_connected()
     * @see MSSQL_DBWorker::connect()
     *
     * @author Oleg Schildt
     */
    public function close_connection(): void
    {
        $this->last_query = null;
        $this->row = null;
        $this->field_names = null;

        if (is_resource($this->connection)) {
            @sqlsrv_close($this->connection);
        }

        $this->statement = null;
        $this->connection = null;
        $this->query_parameters = null;
    } // close_connection

    /**
     * Starts the translation.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see MSSQL_DBWorker::commit_transaction()
     * @see MSSQL_DBWorker::rollback_transaction()
     *
     * @author Oleg Schildt
     */
    public function start_transaction(): void
    {
        $this->check_connection();

        if (!@sqlsrv_begin_transaction($this->connection)) {
            $err = $this->sys_get_errors();

            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED);
        }
    } // start_transaction

    /**
     * Commits the translation.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see MSSQL_DBWorker::start_transaction()
     * @see MSSQL_DBWorker::rollback_transaction()
     *
     * @author Oleg Schildt
     */
    public function commit_transaction(): void
    {
        $this->check_connection();

        if (!@sqlsrv_commit($this->connection)) {
            $err = $this->sys_get_errors();

            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED);
        }
    } // commit_transaction

    /**
     * Rolls back the translation.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see MSSQL_DBWorker::start_transaction()
     * @see MSSQL_DBWorker::commit_transaction()
     *
     * @author Oleg Schildt
     */
    public function rollback_transaction(): void
    {
        $this->check_connection();

        if (!@sqlsrv_rollback($this->connection)) {
            $err = $this->sys_get_errors();

            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED);
        }
    } // rollback_transaction

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
    public function free_result(): void
    {
        $this->check_connection();

        if ($this->statement && is_resource($this->statement)) {
            @sqlsrv_cancel($this->statement);
        }

        $this->row = null;
        $this->field_names = null;
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

        if ($this->last_query_is_insert) {
            if (!$this->statement) {
                return null;
            }

            if (!sqlsrv_next_result($this->statement)) {
                $err = $this->sys_get_errors();

                throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED);
            }

            $id = null;

            if (@sqlsrv_fetch($this->statement)) {
                $id = sqlsrv_get_field($this->statement, 0);
            }

            return $id;
        }

        $this->statement = @sqlsrv_query($this->connection, "SELECT SCOPE_IDENTITY() AS IID");
        if (!$this->statement) {
            $err = $this->sys_get_errors();

            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED);
        }

        if (!$this->fetch_row()) {
            $err = "Identity field cannot be retrieved";

            $this->free_result();

            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED);
        }

        $id = $this->field_by_name("IID");

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

        if (!$this->statement || !is_resource($this->statement)) {
            $err = "Result fetch error";
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        $this->row = @sqlsrv_fetch_array($this->statement, SQLSRV_FETCH_ASSOC);
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
     * rows[n] = ["col1" => "val1", "col2" => "val2", , "col3" => "val3", ...]
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

        if (!$this->statement || !is_resource($this->statement)) {
            $err = "Result fetch error";
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        $rows = [];

        if (!empty($dimension_keys)) {
            $dimension_keys = array_flip($dimension_keys);
        }

        $counter = 0;
        while ($row = @sqlsrv_fetch_array($this->statement, SQLSRV_FETCH_ASSOC)) {
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

        if (!$this->statement || !is_resource($this->statement)) {
            $err = "Result fetch error";
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        /*
        If the cursor is SQLSRV_CURSOR_STATIC or other than
        SQLSRV_CURSOR_FORWARD, retrieving of the data
        has sometimes very poor performance. Not the query execution,
        but the data retrieving!

        The default is SQLSRV_CURSOR_FORWARD, but it is not possible
        to get fetched_count by this type of cursor. So we sacrifice
        the possibility to get fetched_count for the performance.
        The performance is more important.

        no more relevant in new version of driver
        */

        return @sqlsrv_num_rows($this->statement);
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

        if (!$this->statement) {
            $err = "Result fetch error";
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        return @sqlsrv_rows_affected($this->statement);
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

        if (!$this->statement) {
            $err = "Result fetch error";
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        return @sqlsrv_num_fields($this->statement);
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
     * @see MSSQL_DBWorker::field_by_num()
     * @see MSSQL_DBWorker::field_name()
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
     * @see MSSQL_DBWorker::field_by_name()
     * @see MSSQL_DBWorker::field_info_by_num()
     * @see MSSQL_DBWorker::field_name()
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
     * Returns the meta information about the field as an object with properties.
     *
     * @param int $num
     * The number of the field.
     *
     * @return ?array
     * Returns the associative array with properties.
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
     * @see MSSQL_DBWorker::field_by_num()
     * @see MSSQL_DBWorker::field_name()
     *
     * @author Oleg Schildt
     */
    public function field_info_by_num(int $num): ?array
    {
        $this->check_connection();

        if (!$this->statement) {
            $err = "Result fetch error";
            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        $info = @sqlsrv_field_metadata($this->statement);
        if (!$info) {
            $err = $this->sys_get_errors();

            throw new DBWorkerException($err, DBWorker::ERR_QUERY_FAILED, "", [], $this->get_last_query());
        }

        if (empty($info[$num])) {
            return null;
        }

        $sqlsrv_type = [];

        $sqlsrv_type[-5] = "bigint";
        $sqlsrv_type[-2] = "binary";
        $sqlsrv_type[-7] = "bit";
        $sqlsrv_type[1] = "char";
        $sqlsrv_type[91] = "date";
        $sqlsrv_type[93] = "datetime";

        $sqlsrv_type[-155] = "datetimeoffset";
        $sqlsrv_type[3] = "decimal";
        $sqlsrv_type[6] = "float";
        $sqlsrv_type[-4] = "image";
        $sqlsrv_type[4] = "int";
        $sqlsrv_type[-8] = "nchar";

        $sqlsrv_type[-10] = "ntext";
        $sqlsrv_type[2] = "numeric";
        $sqlsrv_type[-9] = "nvarchar";
        $sqlsrv_type[7] = "real";
        $sqlsrv_type[5] = "smallint";
        $sqlsrv_type[-1] = "text";

        $sqlsrv_type[-154] = "time";
        $sqlsrv_type[-6] = "tinyint";
        $sqlsrv_type[-151] = "udt";
        $sqlsrv_type[-11] = "uniqueidentifier";
        $sqlsrv_type[-3] = "varbinary";
        $sqlsrv_type[12] = "varchar";
        $sqlsrv_type[-152] = "xml";

        $field_info = [];

        $field_info["name"] = $info[$num]["Name"];
        $field_info["type"] = $info[$num]["Type"];
        $field_info["size"] = $info[$num]["Size"];

        $field_info["binary"] = 0;
        $field_info["numeric"] = 0;

        if (!empty($field_info["type"])) {
            if (in_array($field_info["type"], [-5, 3, 6, 4, 2, 7, 5, -6])) {
                $field_info["numeric"] = 1;
            }
            elseif (in_array($field_info["type"], [-2, -4, -3])) {
                $field_info["binary"] = 1;
            }
            elseif (in_array($field_info["type"], [91, 93])) {
                $field_info["datetime"] = 1;
            }
            elseif (in_array($field_info["type"], [1, -8, -10, -9, -1, 12])) {
                $field_info["string"] = 1;
            }

            if (isset($sqlsrv_type[$field_info["type"]])) {
                $field_info["type"] = $sqlsrv_type[$field_info["type"]];
            } else {
                $field_info["type"] = "undefined";
            }
        }

        return $field_info;
    } // field_info_by_num

    /**
     * Returns the name of the field by number.
     *
     * @param int $num
     * The number of the field.
     *
     * @return ?string
     * Returns the name of the field by number.
     *
     * @throws DBWorkerException
     * It throws an exception in the case of any errors.
     *
     * @see MSSQL_DBWorker::field_by_num()
     * @see MSSQL_DBWorker::field_info_by_num()
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
     * Escapes the string so that it can be used in the query without causing an error.
     *
     * @param string $str
     * The string to be escaped.
     *
     * @return string
     * Returns the escaped string.
     *
     * @see MSSQL_DBWorker::format_date()
     * @see MSSQL_DBWorker::format_datetime()
     *
     * @author Oleg Schildt
     */
    public function escape(string $str): string
    {
        return str_replace("'", "''", "$str");
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
     * @see MSSQL_DBWorker::escape()
     * @see MSSQL_DBWorker::format_datetime()
     *
     * @author Oleg Schildt
     */
    public function format_date(int $date): string
    {
        if (empty($date)) {
            return $this->quotes_or_null("");
        }

        return $this->quotes_or_null(date("Ymd", $date));
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
     * @see MSSQL_DBWorker::escape()
     * @see MSSQL_DBWorker::format_date()
     *
     * @author Oleg Schildt
     */
    public function format_datetime(int $datetime): string
    {
        if (empty($datetime)) {
            return $this->quotes_or_null("");
        }

        return $this->quotes_or_null(date("Ymd H:i:s", $datetime));
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

        if (!empty($limit)) {
            $query .= "top " . $limit . "\n";
        }

        $query .= implode(", ", $fields) . "\n";

        $query .= "from " . $table . "\n";

        if (!empty($where_clause)) {
            $query .= $where_clause . "\n";
        }

        if (!empty($where_clause)) {
            $query .= $order_clause . "\n";
        }

        return $query;
    } // build_select_query
} // MSSQL_DBWorker
