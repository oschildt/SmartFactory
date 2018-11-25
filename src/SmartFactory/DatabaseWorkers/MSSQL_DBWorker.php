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

/**
 * This is the class for the MS SQL database using the extension sqlsrv.
 *
 * This is a wrapper around the database connectivity. It offers an universal
 * common way for working with databases of different types. Currently, MySQL and
 * MS SQL are supported. If in the future, there will be a better solution, or
 * the current solution turns out to be inefficient in a new version of PHP,
 * we can easily reimplement the DB wrapper without touching the business logic code.
 * Adding support for new database types is also much easier with this wrapping approach.
 *
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
     * @var string
     *
     * @author Oleg Schildt
     */
    protected $prepared_query = null;
    
    /**
     * Stores the state whether the last query was an insert or not.
     *
     * @var boolean
     *
     * @author Oleg Schildt
     */
    protected $last_query_is_insert = false;
    
    /**
     * Internal variable for storing of the current fetched row
     * from the result of the last retrieving query.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $row = null;
    
    /**
     * Internal variable for storing of the column names
     * from the result of the last retrieving query.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $field_names = null;
    
    /**
     * Internal variable for storing of the parameters
     * of the last prepared query.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $query_parameters = null;
    
    /**
     * Retrieves the last errors ocurend while execution of the query.
     *
     * @return string
     * Returns the string of errors separated by the new line symbol.
     *
     * @author Oleg Schildt
     */
    protected function sys_get_errors()
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
    public function create_clone()
    {
        $cln = new MSSQL_DBWorker();
        
        $cln->is_clone = true;
        
        $cln->db_server = $this->db_server;
        $cln->db_name = $this->db_name;
        $cln->db_user = $this->db_user;
        $cln->db_password = $this->db_password;
        $cln->connection = $this->connection;
        
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
     * Initializes the dbworker with connection paramters.
     *
     * @param array $parameters
     * Connection settings as an associative array in the form key => value:
     *
     * - $parameters["db_server"] - server address.
     *
     * - $parameters["db_name"] - database name.
     *
     * - $parameters["db_user"] - user name.
     *
     * - $parameters["db_password"] - user password.
     *
     * @return boolean
     * Returns true upon successful initialization, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function init($parameters)
    {
        if (!empty($parameters["db_server"])) {
            $this->db_server = $parameters["db_server"];
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
        
        return true;
    } // init
    
    /**
     * Checks whether the extension (sqlsrv) is installed which is required for work with
     * the MySQL database.
     *
     * @return boolean
     * The method should return true if the extension is installed, otherwise false.
     *
     * @see get_extension_name()
     *
     * @author Oleg Schildt
     */
    public function is_extension_installed()
    {
        return function_exists("sqlsrv_connect");
    } // is_extension_installed
    
    /**
     * Returns the name of the required PHP extension - "sqlsrv".
     *
     * @return string
     * Returns the name of the required PHP extension - "sqlsrv".
     *
     * @see is_extension_installed()
     *
     * @author Oleg Schildt
     */
    public function get_extension_name()
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
    public function get_rdbms_name()
    {
        return "Microsoft SQL Server";
    } // get_rdbms_name
    
    /**
     * Returns the connection state.
     *
     * @return boolean
     * Returns true if the connection is open, otherwise false.
     *
     * @see connect()
     * @see close_connection()
     *
     * @author Oleg Schildt
     */
    public function is_connected()
    {
        return (!empty($this->connection) && is_resource($this->connection));
    } // is_connected
    
    /**
     * Establishes the connection to the database using the connection
     * settings parameters specified by the initialization.
     *
     * @return boolean
     * Returns true if the connection has been successfully established, otherwise false.
     *
     * @see is_connected()
     * @see close_connection()
     *
     * @author Oleg Schildt
     */
    public function connect()
    {
        sqlsrv_configure("WarningsReturnAsErrors", 0);
        
        $this->last_error = null;
        $this->last_error_id = null;
        $this->last_query = null;
        
        if (!isset($this->connection) || !$this->connection) {
            if (empty($this->db_server) || empty($this->db_user) || empty($this->db_password)) {
                $this->last_error = "No connection data available";
                $this->last_error_id = "conn_data_err";
                return false;
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
            
            if ($this->connection && !empty($this->db_name) && !$this->use_database($this->db_name)) {
                $this->connection = null;
                return false;
            }
        }
        
        if (!$this->connection) {
            $this->connection = null;
            $this->last_error = $this->sys_get_errors();
            $this->last_error_id = "conn_err";
            
            trigger_error($this->last_error, E_USER_ERROR);
            return false;
        }
        
        return true;
    } // connect
    
    /**
     * Sets the a database as working database.
     *
     * @param string $db_name
     * The name of the database to be set as working database.
     *
     * @return boolean
     * Returns true if the database has been successfully set as working database, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function use_database($db_name)
    {
        if (!$this->connection) {
            $this->last_error_id = "conn_err";
            return false;
        }
        
        $this->db_name = $db_name;
        
        $db_name = $this->escape($db_name);
        
        if (!$this->execute_query("USE " . $db_name)) {
            $this->last_error = $this->sys_get_errors();
            $this->last_error_id = "db_err";
            return false;
        }
        
        return true;
    } // use_database
    
    /**
     * Returns the name of the database schema if applicable.
     *
     * @return string
     * Returns the name of the database schema if applicable, or an empty string.
     *
     * @see qualify_name_with_schema()
     *
     * @author Oleg Schildt
     */
    public function get_schema()
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
     * @see get_schema()
     *
     * @author Oleg Schildt
     */
    public function qualify_name_with_schema($name)
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
     * @return boolean
     * Returns true if the query has been successfully executed, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function execute_query($query_string)
    {
        $this->last_query = $query_string;
        
        if (!$this->connection) {
            $this->last_error_id = "conn_err";
            return false;
        }
        
        $options = [];
        
        /*
        If the cursor is SQLSRV_CURSOR_STATIC or other than
        SQLSRV_CURSOR_FORWARD, retreiving of the data
        has sometimes very poor performance. Not the query execution,
        but the data retrieving!
    
        The default is SQLSRV_CURSOR_FORWARD, but it is not possible
        to get fetched_count by this type of cursor. So we sacrifice
        the possibility to get fetched_count for the preformance.
        The preformance is more important.
    
        no more relevant in new version of driver
        */
        
        if (preg_match("/\s*SELECT/i", $query_string)) {
            $options = ["Scrollable" => SQLSRV_CURSOR_STATIC];
        }
        
        $this->statement = @sqlsrv_query($this->connection, $query_string, [], $options);
        if (!$this->statement) {
            $this->last_error = $this->sys_get_errors();
            $this->last_error_id = "query_err";
            
            trigger_error($this->last_error . "\n\n" . $this->last_query, E_USER_ERROR);
            return false;
        }
        
        return true;
    } // execute_query
    
    /**
     * Prepares the SQL query with bindable variables.
     *
     * @param string $query_string
     * The SQL query to be prepared.
     *
     * @return boolean
     * Returns true if the SQL query has been successfully prepared, otherwise false.
     *
     * @see execute_prepared_query()
     * @see free_prepared_query()
     *
     * @author Oleg Schildt
     */
    public function prepare_query($query_string)
    {
        if (!$this->connection) {
            $this->last_error_id = "conn_err";
            return false;
        }
        
        if ($this->statement) {
            if (is_resource($this->statement)) {
                @sqlsrv_free_stmt($this->statement);
            }
        }
        
        $this->last_query = $query_string;
        $this->prepared_query = $query_string;
        
        $params = [];
        $this->query_parameters = [];
        
        $cnt = preg_match_all("/\\?/", $query_string, $matches);
        
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
        SQLSRV_CURSOR_FORWARD, retreiving of the data
        has sometimes very poor performance. Not the query execution,
        but the data retrieving!
    
        The default is SQLSRV_CURSOR_FORWARD, but it is not possible
        to get fetched_count by this type of cursor. So we sacrifice
        the possibility to get fetched_count for the preformance.
        The preformance is more important.
    
        if(preg_match("/\s*SELECT/i", $query_string))
        {
          $options = ["Scrollable" => SQLSRV_CURSOR_STATIC];
        }
        */
        
        $this->statement = @sqlsrv_prepare($this->connection, $query_string . $query_appendix, $params, $options);
        if (!$this->statement) {
            $this->last_error = $this->sys_get_errors();
            $this->last_error_id = "query_err";
            
            trigger_error($this->last_error . "\n\n" . $this->last_query, E_USER_ERROR);
            return false;
        }
        
        return true;
    } // prepare_query
    
    /**
     * Stores long data from a stream.
     *
     * @param string $query_string
     * The SQL query to be used for stroing the long data.
     *
     * @param resource $stream
     * The opened valid stream for reding the long data.
     *
     * Example:
     * ```php
     * $stream = fopen(".../large_binary.jpg", "rb");
     *
     * if(!$dbw->stream_long_data("UPDATE LARGE_DATA SET BLOB_DATA = ? WHERE ID = 1", $stream))
     * {
     *   return sql_error($dbw);
     * }
     * ```
     *
     * @return boolean
     * Returns true if the long data has been successfully stored, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function stream_long_data($query_string, &$stream)
    {
        if (!is_resource($stream)) {
            $this->last_error_id = "stream_err";
            fclose($stream);
            return false;
        }
        
        if (!$this->connection) {
            $this->last_error_id = "conn_err";
            fclose($stream);
            return false;
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
            $this->last_error = $this->sys_get_errors();
            $this->last_error_id = "query_err";
            
            trigger_error($this->last_error . "\n\n" . $this->last_query, E_USER_ERROR);
            
            fclose($stream);
            
            return false;
        }
        
        if (!@sqlsrv_execute($this->statement)) {
            $this->last_error = $this->sys_get_errors();
            $this->last_error_id = "query_err";
            
            trigger_error($this->last_error . "\n\n" . $this->last_query, E_USER_ERROR);
            
            if (is_resource($this->statement)) {
                @sqlsrv_free_stmt($this->statement);
            }
            $this->statement = null;
            fclose($stream);
            
            return false;
        }
        
        // Send up to 8K of parameter data to the server
        // with each call to sqlsrv_send_stream_data.
        while (sqlsrv_send_stream_data($this->statement)) {
            null;
        }
        
        if (is_resource($this->statement)) {
            @sqlsrv_free_stmt($this->statement);
        }
        $this->statement = null;
        fclose($stream);
        
        return true;
    } // stream_long_data
    
    /**
     * Executes the prepared SQL query.
     *
     * @param string $query_string
     * The SQL query to be executed.
     *
     * All subsequent parameters are the paramteres of the prepared SQL query
     *
     * @return boolean
     * Returns true if the prepared SQL query has been successfully executed, otherwise false.
     *
     * @see prepare_query()
     * @see free_prepared_query()
     *
     * @author Oleg Schildt
     */
    public function execute_prepared_query($query_string /* arg list */)
    {
        if (!$this->connection) {
            $this->last_error_id = "conn_err";
            return false;
        }
        
        if (empty($this->prepared_query) || empty($this->statement)) {
            $this->last_error = "no prepared query defined";
            $this->last_error_id = "query_err";
            return false;
        }
        
        $args = func_get_args();
        if (count($args) == 1 && is_array($args[0])) {
            $args = $args[0];
        }
        
        $this->last_query = $this->prepared_query;
        
        $counter = 0;
        foreach ($args as $argval) {
            if ($argval === null) {
                $this->query_parameters[$counter] = $argval;
                
                $this->last_query = preg_replace("/\\?/", "null", $this->last_query, 1);
            } elseif (is_int($argval)) {
                $this->query_parameters[$counter] = $argval;
                
                $this->last_query = preg_replace("/\\?/", $argval, $this->last_query, 1);
            } elseif (is_float($argval)) {
                $this->query_parameters[$counter] = $argval;
                
                $this->last_query = preg_replace("/\\?/", $argval, $this->last_query, 1);
            } else {
                $this->query_parameters[$counter] = $argval;
                
                $this->last_query = preg_replace("/\\?/",
                  \SmartFactory\preg_r_escape("'" . $this->escape($argval) . "'"), $this->last_query, 1);
            }
            
            $counter++;
        }
        
        if (!@sqlsrv_execute($this->statement)) {
            $this->last_error = $this->sys_get_errors();
            $this->last_error_id = "query_err";
            
            trigger_error($this->last_error . "\n\n" . $this->last_query, E_USER_ERROR);
            return false;
        }
        
        return true;
    } // execute_prepared_query
    
    /**
     * Executes the SQL stored procedure.
     *
     * @param string $procedure
     * The name of the SQL stored procedure.
     *
     * All subsequent parameters are the paramteres of the SQL stored procedure.
     *
     * @return boolean
     * Returns true if the stored procedure has been successfully executed, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function execute_procedure($procedure /* arg list */)
    {
        if (!$this->connection) {
            $this->last_error_id = "conn_err";
            return false;
        }
        
        $args = func_get_args();
        // prepare the arguments for placing in eval()
        // escape single quotes
        
        $this->last_query = "";
        
        if (count($args) > 0) {
            $proc_name = "";
            $arg_list = "";
            
            $first = true;
            foreach ($args as $argkey => $argval) {
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
            
            $this->last_query = trim("EXEC ${proc_name} ${arg_list}");
        }
        
        $result = $this->execute_query($this->last_query);
        
        if (!$result) {
            return $result;
        }
        
        // A stored procedure may generate many result objects.
        // If the procedure has a select statement at the end,
        // its data is in the last result.
        
        while (!sqlsrv_has_rows($this->statement)) {
            if (!sqlsrv_next_result($this->statement)) {
                break;
            }
        }
        
        return $result;
    } // execute_procedure
    
    /**
     * Closes the currently opened connection.
     *
     * @return boolean
     * Returns true if the connection has been successfully closed, otherwise false.
     *
     * @see is_connected()
     * @see connect()
     *
     * @author Oleg Schildt
     */
    public function close_connection()
    {
        $this->last_error = null;
        $this->last_error_id = null;
        $this->last_query = null;
        $this->row = null;
        $this->field_names = null;
        
        if (!$this->connection) {
            return true;
        }
        
        if (is_resource($this->connection)) {
            @sqlsrv_close($this->connection);
        }
        
        $this->connection = null;
        $this->query_parameters = null;
        $this->statement = null;
        $this->last_error = null;
        $this->last_error_id = null;
        
        return true;
    } // close_connection
    
    /**
     * Starts the transation.
     *
     * @return boolean
     * Returns true if the transaction has been successfully started, otherwise false.
     *
     * @see commit_transaction()
     * @see rollback_transaction()
     *
     * @author Oleg Schildt
     */
    public function start_transaction()
    {
        if (!$this->connection) {
            $this->last_error_id = "conn_err";
            return false;
        }
        
        return @sqlsrv_begin_transaction($this->connection);
    } // start_transaction
    
    /**
     * Commits the transation.
     *
     * @return boolean
     * Returns true if the transaction has been successfully committed, otherwise false.
     *
     * @see start_transaction()
     * @see rollback_transaction()
     *
     * @author Oleg Schildt
     */
    public function commit_transaction()
    {
        if (!$this->connection) {
            $this->last_error_id = "conn_err";
            return false;
        }
        
        return @sqlsrv_commit($this->connection);
    } // commit_transaction
    
    /**
     * Rolls back the transation.
     *
     * @return boolean
     * Returns true if the transaction has been successfully rolled back, otherwise false.
     *
     * @see start_transaction()
     * @see commit_transaction()
     *
     * @author Oleg Schildt
     */
    public function rollback_transaction()
    {
        if (!$this->connection) {
            $this->last_error_id = "conn_err";
            return false;
        }
        
        return @sqlsrv_rollback($this->connection);
    } // rollback_transaction
    
    /**
     * Frees the result of the previously executed retrieving query.
     *
     * It should be called only for the retrieving queries.
     *
     * @return boolean
     * Returns true if the result has been successfully freed, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function free_result()
    {
        if ($this->statement) {
            if (is_resource($this->statement)) {
                @sqlsrv_cancel($this->statement);
            }
        }
        
        $this->row = null;
        $this->field_names = null;
        
        return true;
    } // free_result
    
    /**
     * Frees the prepared query.
     *
     * It should be called after all executions of the prepared query.
     *
     * @return boolean
     * Returns true if the prepared query has been successfully freed, otherwise false.
     *
     * @see prepare_query()
     * @see execute_prepared_query()
     *
     * @author Oleg Schildt
     */
    public function free_prepared_query()
    {
        if ($this->statement) {
            if (is_resource($this->statement)) {
                @sqlsrv_free_stmt($this->statement);
            }
        }
        
        $this->statement = null;
        $this->last_query = null;
        $this->prepared_query = null;
        $this->query_parameters = null;
        
        $this->last_query_is_insert = false;
        
        return true;
    } // free_prepared_query
    
    /**
     * Fetches the next row of data from the result of the execution of the retrieving query.
     *
     * @return boolean
     * Returns true if the next row exists and has been successfully fetched, otherwise false.
     *
     * Example:
     * ```php
     * if(!$dbw->execute_query("SELECT FIRST_NAME, LAST_NAME FROM USERS"))
     * {
     *   return sql_error($dbw);
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
     * @author Oleg Schildt
     */
    public function fetch_row()
    {
        if (!$this->statement) {
            $this->last_error_id = "result_err";
            return false;
        }
        
        if (!is_resource($this->statement)) {
            return false;
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
     * @param array $rows
     * Target array for loading the results.
     *
     * @param array $dimension_keys
     * Array of the column names that should be used as dimensions.
     *
     * Per default, the rows are fetched as two dimensional array.
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
     * @return int|false
     * Returns the number of the fetched rows. It might be also 0. In the case of
     * any error returns false.
     *
     * @author Oleg Schildt
     */
    public function fetch_array(&$rows, $dimension_keys = null)
    {
        if (!$this->statement) {
            $this->last_error_id = "result_err";
            return false;
        }
        
        if (!is_resource($this->statement)) {
            return false;
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
                
                foreach ($dimensions as &$dval) {
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
     * @return int|false
     * Returns the number of the rows fetched by the last retrieving query. In the case
     * of any error returns false.
     *
     * @author Oleg Schildt
     */
    public function fetched_count()
    {
        if (!$this->statement) {
            $this->last_error_id = "result_err";
            return false;
        }
        
        if (!is_resource($this->statement)) {
            return false;
        }
        
        /*
        If the cursor is SQLSRV_CURSOR_STATIC or other than
        SQLSRV_CURSOR_FORWARD, retreiving of the data
        has sometimes very poor performance. Not the query execution,
        but the data retrieving!
    
        The default is SQLSRV_CURSOR_FORWARD, but it is not possible
        to get fetched_count by this type of cursor. So we sacrifice
        the possibility to get fetched_count for the preformance.
        The preformance is more important.
    
        no more relevant in new version of driver
        */
        
        return @sqlsrv_num_rows($this->statement);
    } // fetched_count
    
    /**
     * Returns the number of the rows affected by the last modification query.
     *
     * @return int|false
     * Returns the number of the rows affected by the last modification query. In the case
     * of any error returns false.
     *
     * @author Oleg Schildt
     */
    public function affected_count()
    {
        if (!$this->statement) {
            return false;
        }
        
        return @sqlsrv_rows_affected($this->statement);
    } // affected_count
    
    /**
     * Returns the number of the fields in the result of the last retrieving query.
     *
     * @return int|false
     * Returns the number of the fields in the result of the last retrieving query. In the case
     * of any error returns false.
     *
     * @author Oleg Schildt
     */
    public function field_count()
    {
        if (!$this->statement) {
            return false;
        }
        
        return @sqlsrv_num_fields($this->statement);
    } // field_count
    
    /**
     * Returns the value of the auto increment field by the last insertion.
     *
     * @return int|false
     * Returns the value of the auto increment field by the last insertion. In the case
     * of any error returns false.
     *
     * @author Oleg Schildt
     */
    public function insert_id()
    {
        if (!$this->connection) {
            $this->last_error_id = "conn_err";
            return false;
        }
        
        if ($this->last_query_is_insert) {
            if (!$this->statement) {
                return null;
            }
            
            if (!sqlsrv_next_result($this->statement)) {
                $this->last_error = $this->sys_get_errors();
                $this->last_error_id = "query_err";
                
                trigger_error($this->last_error, E_USER_ERROR);
                return false;
            }
            
            $id = null;
            
            if (@sqlsrv_fetch($this->statement)) {
                $id = sqlsrv_get_field($this->statement, 0);
            }
            
            return $id;
        }
        
        $this->statement = @sqlsrv_query($this->connection, "SELECT SCOPE_IDENTITY() AS IID");
        if (!$this->statement) {
            $this->last_error = $this->sys_get_errors();
            $this->last_error_id = "query_err";
            
            trigger_error($this->last_error, E_USER_ERROR);
            return false;
        }
        
        if (!$this->fetch_row()) {
            $this->last_error_id = "query_err";
            return false;
        }
        
        $id = $this->field_by_name("IID");
        
        $this->free_result();
        
        return $id;
    } // insert_id
    
    /**
     * Returns the value of a field specified by name.
     *
     * @param string $name
     * The name of the field.
     *
     * @return mixed|null
     * Returns the value of a field specified by name. In the case
     * of any error returns null.
     *
     * @see field_by_num()
     * @see field_name()
     *
     * @author Oleg Schildt
     */
    public function field_by_name($name)
    {
        if (!$this->row) {
            return null;
        }
        
        if (!array_key_exists($name, $this->row)) {
            trigger_error("Field with the name '$name' does not exist in the result set!", E_USER_ERROR);
            return null;
        }
        
        return $this->row[$name];
    } // field_by_name
    
    /**
     * Returns the value of a field specified by number.
     *
     * @param int $num
     * The number of the field.
     *
     * @return mixed|null
     * Returns the value of a field specified by number. In the case
     * of any error returns null.
     *
     * @see field_by_name()
     * @see field_info_by_num()
     * @see field_name()
     *
     * @author Oleg Schildt
     */
    public function field_by_num($num)
    {
        if (!$this->row) {
            return null;
        }
        
        if (!array_key_exists($num, $this->field_names)) {
            trigger_error("Field with the index $num does not exist in the result set!", E_USER_ERROR);
            return null;
        }
        
        return $this->row[$this->field_names[$num]];
    } // field_by_num
    
    /**
     * Returns the meta information about the field as an object with properties.
     *
     * @param int $num
     * The number of the field.
     *
     * @return array|false
     * Returns the associative array with properties. In the case
     * of any error returns null.
     *
     * $info["name"] - name of the field.
     *
     * $info["type"] - type of the field.
     *
     * $info["size"] - size of the field.
     *
     * $info["binary"] - whether the filed is binary.
     *
     * $info["numeric"] - whether the filed is numeric.
     *
     * @see field_by_num()
     * @see field_name()
     *
     * @author Oleg Schildt
     */
    public function field_info_by_num($num)
    {
        if (!$this->statement) {
            $this->last_error_id = "result_err";
            return false;
        }
        
        $info = @sqlsrv_field_metadata($this->statement);
        if (!$info) {
            $this->last_error = $this->sys_get_errors();
            $this->last_error_id = "result_err";
            
            trigger_error($this->last_error, E_USER_ERROR);
            return false;
        }
        
        if (empty($info[$num])) {
            return false;
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
            if (in_array($field_info["type"], [-2, -4, -3])) {
                $field_info["binary"] = 1;
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
     * @return string|null
     * Returns the name of the field by number.
     *
     * @see field_by_num()
     * @see field_info_by_num()
     *
     * @author Oleg Schildt
     */
    public function field_name($num)
    {
        $info = $this->field_info_by_num($num);
        if (!$info) {
            return null;
        }
        
        return \SmartFactory\checkempty($info["name"]);
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
     * @see format_date()
     * @see format_datetime()
     *
     * @author Oleg Schildt
     */
    public function escape($str)
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
     * @see escape()
     * @see format_datetime()
     *
     * @author Oleg Schildt
     */
    public function format_date($date)
    {
        return date("Ymd", $date);
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
     * @see escape()
     * @see format_date()
     *
     * @author Oleg Schildt
     */
    public function format_datetime($datetime)
    {
        return date("Ymd H:i:s", $datetime);
    } // format_datetime
} // MSSQL_DBWorker
//----------------------------------------------------------------------
