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

/**
 * This is the class for the MySQL database using the extension mysqli.
 *
 * This is a wrapper around the database connectivity. It offers an universal
 * common way for working with databases of different types. Currently, MySQL and
 * MS SQL are supported. If in the future, there will be a better solution, or
 * the current solution turns out to be inefficient in a new version of PHP,
 * we can easily reimplement the DB wrapper without touching the business logic code.
 * Adding support for new database types is also much easier with this wrapping approach.
 *
 * @see MSSQL_DBWorker
 *
 * @author Oleg Schildt
 */
class MySQL_DBWorker extends DBWorker
{
    /**
     * Flag for setting the connection to read only.
     *
     * @var boolean $read_only
     *
     * @author Oleg Schildt
     */
    protected $read_only = false;
    
    /**
     * Internal MySQLi object.
     *
     * @var \MySQLi
     *
     * @author Oleg Schildt
     */
    protected $mysqli = null;
    
    /**
     * Internal mysqli_result object.
     *
     * @var \mysqli_result
     *
     * @author Oleg Schildt
     */
    protected $mysqli_result = null;
    
    /**
     * Internal mysqli_stmt object.
     *
     * @var \mysqli_stmt
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
     * Creates a clone of the dbworker that is using the same open
     * connection.
     *
     * This might be useful if you want to execute some additional
     * queries while iteration through the active results of a select query.
     *
     * @return MySQL_DBWorker
     * Returns the clone of this dbworker.
     *
     * @author Oleg Schildt
     */
    public function create_clone()
    {
        $cln = new MySQL_DBWorker();
        
        $cln->is_clone = true;
        
        $cln->db_server = $this->db_server;
        $cln->db_name = $this->db_name;
        $cln->db_user = $this->db_user;
        $cln->db_password = $this->db_password;
        $cln->read_only = $this->read_only;
        $cln->mysqli = $this->mysqli;
        
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
     * - $parameters["db_name"] - database name.
     * - $parameters["db_user"] - user name.
     * - $parameters["db_password"] - user password.
     * - $parameters["read_only"] - this paramter sets the connection to the read only mode.
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
        if (!empty($parameters["read_only"])) {
            $this->read_only = $parameters["read_only"];
        }
        
        return true;
    } // init
    
    /**
     * Checks whether the extension (mysqli) is installed which is required for work with
     * the MySQL database.
     *
     * @return boolean
     * The method should return true if the extension is installed, otherwise false.
     *
     * @see MySQL_DBWorker::get_extension_name()
     *
     * @author Oleg Schildt
     */
    public function is_extension_installed()
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
    public function get_extension_name()
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
    public function get_rdbms_name()
    {
        return "MySQL Server";
    } // get_rdbms_name
    
    /**
     * Returns the connection state.
     *
     * @return boolean
     * Returns true if the connection is open, otherwise false.
     *
     * @see MySQL_DBWorker::connect()
     * @see MySQL_DBWorker::close_connection()
     *
     * @author Oleg Schildt
     */
    public function is_connected()
    {
        return (!empty($this->mysqli) && empty($this->mysqli->connect_error));
    } // is_connected
    
    /**
     * Establishes the connection to the database using the connection
     * settings parameters specified by the initialization.
     *
     * @return boolean
     * Returns true if the connection has been successfully established, otherwise false.
     *
     * @throws \Throwable
     * It throws an exception in the case of any errors.
     *
     * @see MySQL_DBWorker::is_connected()
     * @see MySQL_DBWorker::close_connection()
     *
     * @author Oleg Schildt
     */
    public function connect()
    {
        if (!$this->mysqli) {
            if (empty($this->db_server) || empty($this->db_user) || empty($this->db_password)) {
                throw new \Exception("Connection data is incomplete", DBWorker::ERR_CONNECTION_DATA_INCOMPLETE);
            }
            
            $this->mysqli = @new \MySQLi($this->db_server, $this->db_user, $this->db_password);
        }
        
        if ($this->mysqli->connect_error) {
            $err = $this->mysqli->connect_error;
            $this->mysqli = null;
    
            trigger_error($err, E_USER_ERROR);
            throw new \Exception($err, DBWorker::ERR_CONNECTION_FAILED);
        }
        
        if (!empty($this->db_name)) {
            try {
                $this->use_database($this->db_name);
            } catch (\Throwable $ex) {
                $this->mysqli = null;
                throw $ex;
            }
        }
        
        try {
            $this->execute_query("set charset utf8");
            
            if (!empty($this->read_only)) {
                $this->execute_query("set transaction read only");
                $this->execute_query("start transaction");
            }
        } catch (\Throwable $ex) {
            $this->mysqli = null;
            throw $ex;
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
     * @throws \Throwable
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function use_database($db_name)
    {
        if (!$this->is_connected()) {
            $this->connect();
        }
        
        $this->db_name = $db_name;
        
        if (!@$this->mysqli->select_db($this->db_name)) {
            trigger_error($this->mysqli->error, E_USER_ERROR);
            throw new \Exception($this->mysqli->error, DBWorker::ERR_DATABASE_NOT_FOUND);
        }
        
        return true;
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
    public function get_schema()
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
    public function qualify_name_with_schema($name)
    {
        return $name;
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
     * @throws \Exception
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function execute_query($query_string)
    {
        if (!$this->is_connected()) {
            $this->connect();
        }
        
        $this->last_query = $query_string;
        
        $this->mysqli_result = @$this->mysqli->query($query_string);
        if (!$this->mysqli_result) {
            trigger_error($this->mysqli->error . "\n\n" . $query_string, E_USER_ERROR);
            throw new \Exception($this->mysqli->error . "\n\n" . $query_string, DBWorker::ERR_QUERY_FAILED);
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
     * @throws \Throwable
     * It throws an exception in the case of any errors.
     *
     * @see MySQL_DBWorker::execute_prepared_query()
     * @see MySQL_DBWorker::free_prepared_query()
     *
     * @author Oleg Schildt
     */
    public function prepare_query($query_string)
    {
        if (!$this->is_connected()) {
            $this->connect();
        }
        
        if ($this->statement) {
            $this->statement->close();
            $this->statement = null;
        }
        
        $this->last_query = $query_string;
        $this->prepared_query = $query_string;
        
        $this->statement = $this->mysqli->prepare($query_string);
        if (!$this->statement) {
            $this->statement = null;
            trigger_error($this->mysqli->error . "\n\n" . $query_string, E_USER_ERROR);
            throw new \Exception($this->mysqli->error . "\n\n" . $query_string, DBWorker::ERR_QUERY_FAILED);
        }
        
        return true;
    } // prepare_query
    
    /**
     * Stores long data from a stream.
     *
     * @param string $query_string
     * The SQL query to be used for stroing the long data.
     *
     * @param resource &$stream
     * The opened valid stream for reding the long data.
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
     * @return boolean
     * Returns true if the long data has been successfully stored, otherwise false.
     *
     * @throws \Throwable
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function stream_long_data($query_string, &$stream)
    {
        if (!$this->is_connected()) {
            try {
                $this->connect();
            } catch (\Throwable $ex) {
                fclose($stream);
                throw $ex;
            }
        }
        
        if (!is_resource($stream)) {
            fclose($stream);
            
            throw new \Exception("Stream is invalid", DBWorker::ERR_STREAM_ERROR);
        }
        
        if ($this->statement) {
            $this->statement->close();
            $this->statement = null;
        }
        
        $this->last_query = $query_string;
        $this->prepared_query = $query_string;
        
        $this->statement = $this->mysqli->prepare($query_string);
        if (!$this->statement) {
            fclose($stream);

            trigger_error($this->mysqli->error . "\n\n" . $this->last_query, E_USER_ERROR);
            throw new \Exception($this->mysqli->error . "\n\n" . $this->last_query, DBWorker::ERR_QUERY_FAILED);
        }
        
        $null = null;
        $this->statement->bind_param("b", $null);
        
        while (!feof($stream)) {
            if ($this->statement->send_long_data(0, fread($stream, 8192)) === false) {
                $err = $this->statement->error;
                
                $this->statement->close();
                $this->statement = null;
                fclose($stream);
    
                trigger_error($err . "\n\n" . $query_string, E_USER_ERROR);
                throw new \Exception($err, DBWorker::ERR_QUERY_FAILED);
            }
        }
        
        fclose($stream);
        
        if (!$this->statement->execute()) {
            $err = $this->statement->error;

            $this->statement->close();
            $this->statement = null;
    
            trigger_error($err . "\n\n" . $this->last_query, E_USER_ERROR);
            throw new \Exception($err . "\n\n" . $this->last_query, DBWorker::ERR_QUERY_FAILED);
        }
        
        $this->statement->close();
        $this->statement = null;
        
        return true;
    } // stream_long_data
    
    /**
     * Executes the prepared SQL query.
     *
     * @param mixed ...$args
     * The number of parameters may vary and be zero. An array can also be passed.
     * These are paremeters of the prepared query.
     *
     * @return boolean
     * Returns true if the prepared SQL query has been successfully executed, otherwise false.
     *
     * @throws \Throwable
     * It throws an exception in the case of any errors.
     *
     * @see MySQL_DBWorker::prepare_query()
     * @see MySQL_DBWorker::free_prepared_query()
     *
     * @author Oleg Schildt
     */
    public function execute_prepared_query(...$args)
    {
        if (!$this->is_connected()) {
            $this->connect();
        }
        
        if (empty($this->prepared_query) || empty($this->statement)) {
            throw new \Exception("No prepared query defined", DBWorker::ERR_QUERY_FAILED);
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
        
        if (count($args) > 0 && !call_user_func_array([$this->statement, 'bind_param'], $parameters)) {
           $err = "Number of elements in type definition string doesn't match number of bind variables.";
            trigger_error($err . "\n\n" . $this->last_query, E_USER_ERROR);
            throw new \Exception($err . "\n\n" . $this->last_query, DBWorker::ERR_QUERY_FAILED);
        }
        
        if (!$this->statement->execute()) {
            trigger_error($this->statement->error . "\n\n" . $this->last_query, E_USER_ERROR);
            throw new \Exception($this->statement->error . "\n\n" . $this->last_query, DBWorker::ERR_QUERY_FAILED);
        }
        
        if (!$this->statement->store_result()) {
            trigger_error($this->statement->error . "\n\n" . $this->last_query, E_USER_ERROR);
            throw new \Exception($this->statement->error . "\n\n" . $this->last_query, DBWorker::ERR_QUERY_FAILED);
        }
    
        $this->mysqli_result = $this->statement->result_metadata();
        
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
     * @throws \Exception
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function execute_procedure($procedure /* arg list */)
    {
        if (!$this->is_connected()) {
            $this->connect();
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
            
            $this->last_query = "CALL ${proc_name}(${arg_list});";
        }
        
        return $this->execute_query($this->last_query);
    } // execute_procedure
    
    /**
     * Frees the prepared query.
     *
     * It should be called after all executions of the prepared query.
     *
     * @return boolean
     * Returns true if the prepared query has been successfully freed, otherwise false.
     *
     * @throws \Exception
     * It throws an exception in the case of any errors.
     *
     * @see MySQL_DBWorker::prepare_query()
     * @see MySQL_DBWorker::execute_prepared_query()
     *
     * @author Oleg Schildt
     */
    public function free_prepared_query()
    {
        if ($this->statement) {
            $this->statement->close();
        }
        
        $this->statement = null;
        $this->last_query = null;
        $this->prepared_query = null;
        
        return true;
    } // free_prepared_query
    
    /**
     * Closes the currently opened connection.
     *
     * @return boolean
     * Returns true if the connection has been successfully closed, otherwise false.
     *
     * @see MySQL_DBWorker::is_connected()
     * @see MySQL_DBWorker::connect()
     *
     * @author Oleg Schildt
     */
    public function close_connection()
    {
        $this->last_query = null;
        $this->prepared_query = null;
        $this->row = null;
        $this->field_names = null;
        
        if ($this->mysqli) {
            @$this->mysqli->close();
        }
        
        if ($this->statement) {
            @$this->statement->close();
        }
    
        $this->statement = null;
        $this->mysqli = null;
        $this->mysqli_result = null;
        
        return true;
    } // close_connection
    
    /**
     * Starts the transation.
     *
     * @return boolean
     * Returns true if the transaction has been successfully started, otherwise false.
     *
     * @throws \Exception
     * It throws an exception in the case of any errors.
     *
     * @see MySQL_DBWorker::commit_transaction()
     * @see MySQL_DBWorker::rollback_transaction()
     *
     * @author Oleg Schildt
     */
    public function start_transaction()
    {
        return $this->execute_query("BEGIN");
    } // start_transaction
    
    /**
     * Commits the transation.
     *
     * @return boolean
     * Returns true if the transaction has been successfully committed, otherwise false.
     *
     * @throws \Exception
     * It throws an exception in the case of any errors.
     *
     * @see MySQL_DBWorker::start_transaction()
     * @see MySQL_DBWorker::rollback_transaction()
     *
     * @author Oleg Schildt
     */
    public function commit_transaction()
    {
        return $this->execute_query("COMMIT");
    } // commit_transaction
    
    /**
     * Rolls back the transation.
     *
     * @return boolean
     * Returns true if the transaction has been successfully rolled back, otherwise false.
     *
     * @throws \Exception
     * It throws an exception in the case of any errors.
     *
     * @see MySQL_DBWorker::start_transaction()
     * @see MySQL_DBWorker::commit_transaction()
     *
     * @author Oleg Schildt
     */
    public function rollback_transaction()
    {
        return $this->execute_query("ROLLBACK");
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
        if ($this->mysqli_result) {
            $this->mysqli_result->free_result();
            
            $this->mysqli_result = null;
        }
        
        if ($this->statement) {
            $this->statement->free_result();
        }
        
        $this->row = null;
        $this->field_names = null;
        $this->last_query = null;
        
        return true;
    } // free_result
    
    /**
     * Returns the value of the auto increment field by the last insertion.
     *
     * @return int
     * Returns the value of the auto increment field by the last insertion.
     *
     * @throws \Exception
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function insert_id()
    {
        if (!$this->is_connected()) {
            $this->connect();
        }
        
        return $this->mysqli->insert_id;
    } // insert_id
    
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
     * @throws \Exception
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function fetch_row()
    {
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
                
                if (!call_user_func_array([$this->statement, 'bind_result'], $params)) {
                    trigger_error($this->statement->error . "\n\n" . $this->last_query, E_USER_ERROR);
                    throw new \Exception($this->statement->error . "\n\n" . $this->last_query, DBWorker::ERR_QUERY_FAILED);
                }
                
                unset($params);
            }
            
            $result = $this->statement->fetch();
            if (!$result) {
                $this->row = null;
            }
            
            return $result;
        } // prepared query
    
        if (!$this->mysqli_result) {
            $err = "Result fetch error1";
            trigger_error($err . "\n\n" . $this->last_query, E_USER_ERROR);
            throw new \Exception($err . "\n\n" . $this->last_query, DBWorker::ERR_QUERY_FAILED);
        }
    
        $this->row = @$this->mysqli_result->fetch_assoc();
        
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
     * @return int
     * Returns the number of the fetched rows. It might be also 0.
     *
     * @throws \Exception
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function fetch_array(&$rows, $dimension_keys = null)
    {
        $rows = [];
        
        if (!empty($dimension_keys)) {
            $dimension_keys = array_flip($dimension_keys);
        }
        
        $counter = 0;
        $row = [];
        
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
            
            if (!call_user_func_array([$this->statement, 'bind_result'], $params)) {
                trigger_error($this->statement->error . "\n\n" . $this->last_query, E_USER_ERROR);
                throw new \Exception($this->statement->error . "\n\n" . $this->last_query, DBWorker::ERR_QUERY_FAILED);
            }
            
            unset($params);
            
            while ($this->statement->fetch()) {
                $counter++;
                
                $rowcpy = [];
                
                // we must create a copy from $row, because its
                // items are bound per reference
                foreach ($row as $key => $val) {
                    $rowcpy[$key] = $val;
                }
                
                if (empty($dimension_keys)) {
                    $rows[] = $rowcpy;
                } else {
                    $dimensions = array_intersect_key($rowcpy, $dimension_keys);
                    $rowcpy = array_diff_key($rowcpy, $dimension_keys);
                    
                    $reference = &$rows;
                    
                    foreach ($dimensions as &$dval) {
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
            $err = "Result fetch error";
            trigger_error($err . "\n\n" . $this->last_query, E_USER_ERROR);
            throw new \Exception($err . "\n\n" . $this->last_query, DBWorker::ERR_QUERY_FAILED);
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
     * Returns the number of the rows fetched by the last retrieving query.
     *
     * @throws \Exception
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function fetched_count()
    {
        if ($this->statement) {
            return $this->statement->num_rows;
        }
        
        if (!$this->mysqli_result || !is_object($this->mysqli_result)) {
            $err = "Result fetch error";
            trigger_error($err . "\n\n" . $this->last_query, E_USER_ERROR);
            throw new \Exception($err . "\n\n" . $this->last_query, DBWorker::ERR_QUERY_FAILED);
        }
        
        return $this->mysqli_result->num_rows;
    } // fetched_count
    
    /**
     * Returns the number of the rows affected by the last modification query.
     *
     * @return int
     * Returns the number of the rows affected by the last modification query.
     *
     * @throws \Exception
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function affected_count()
    {
        if (!$this->mysqli_result) {
            $err = "Result fetch error";
            trigger_error($err . "\n\n" . $this->last_query, E_USER_ERROR);
            throw new \Exception($err . "\n\n" . $this->last_query, DBWorker::ERR_QUERY_FAILED);
        }
        
        return $this->mysqli->affected_rows;
    } // affected_count
    
    /**
     * Returns the number of the fields in the result of the last retrieving query.
     *
     * @return int|false
     * Returns the number of the fields in the result of the last retrieving query. In the case
     * of any error returns false.
     *
     * @throws \Exception
     * It throws an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function field_count()
    {
        if ($this->statement) {
            return $this->statement->field_count;
        }
        
        if (!$this->mysqli_result) {
            $err = "Result fetch error";
            trigger_error($err . "\n\n" . $this->last_query, E_USER_ERROR);
            throw new \Exception($err . "\n\n" . $this->last_query, DBWorker::ERR_QUERY_FAILED);
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
     * @return mixed|null
     * Returns the value of a field specified by name. In the case
     * of any error returns null.
     *
     * @see MySQL_DBWorker::field_by_num()
     * @see MySQL_DBWorker::field_name()
     *
     * @author Oleg Schildt
     */
    public function field_by_name($name, $type = self::DB_STRING)
    {
        if (!$this->row) {
            return null;
        }
        
        if (!array_key_exists($name, $this->row)) {
            trigger_error("Field with the name '$name' does not exist in the result set!", E_USER_ERROR);
            return null;
        }
        
        if(($type == DBWorker::DB_DATE || $type == DBWorker::DB_DATETIME) && !empty($this->row[$name])) {
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
     * @return mixed|null
     * Returns the value of a field specified by number. In the case
     * of any error returns null.
     *
     * @see MySQL_DBWorker::field_by_name()
     * @see MySQL_DBWorker::field_info_by_num()
     * @see MySQL_DBWorker::field_name()
     *
     * @author Oleg Schildt
     */
    public function field_by_num($num, $type = self::DB_STRING)
    {
        if (!$this->row) {
            return null;
        }
        
        if (!array_key_exists($num, $this->field_names)) {
            trigger_error("Field with the index $num does not exist in the result set!", E_USER_ERROR);
            return null;
        }
    
        if(($type == DBWorker::DB_DATE || $type == DBWorker::DB_DATETIME) && !empty($this->row[$this->field_names[$num]])) {
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
     * @return object|null
     * Returns the value of a field specified by number as an object with properties. In the case
     * of any error returns null.
     *
     * @throws \Exception
     * It throws an exception in the case of any errors.
     *
     * @see MySQL_DBWorker::field_by_num()
     * @see MySQL_DBWorker::field_info_by_num()
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
     * Returns the meta information about the field as an object with properties.
     *
     * @param int $num
     * The number of the field.
     *
     * @return array
     * Returns the associative array with properties. In the case
     * of any error returns null.
     *
     * @throws \Exception
     * It throws an exception in the case of any errors.
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
     * @see MySQL_DBWorker::field_by_num()
     * @see MySQL_DBWorker::field_name()
     *
     * @author Oleg Schildt
     */
    public function field_info_by_num($num)
    {
        if (!$this->mysqli_result) {
            $err = "Result fetch error";
            trigger_error($err . "\n\n" . $this->last_query, E_USER_ERROR);
            throw new \Exception($err . "\n\n" . $this->last_query, DBWorker::ERR_QUERY_FAILED);
        }
        
        $res = @$this->mysqli_result->fetch_field_direct($num);
        if (!$res) {
            trigger_error($this->mysqli->error . "\n\n" . $this->last_query, E_USER_ERROR);
            throw new \Exception($this->mysqli->error . "\n\n" . $this->last_query, DBWorker::ERR_QUERY_FAILED);
        }
        
        // some corrections for compatible formats
        
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
            if (in_array($field_info["type"], [249, 250, 251, 252])) {
                $field_info["binary"] = 1;
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
    public function escape($str)
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
    public function format_date($date)
    {
        return date("Y-m-d", $date);
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
    public function format_datetime($datetime)
    {
        return date("Y-m-d H:i:s", $datetime);
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
     * @author Oleg Schildt
     */
    function prepare_for_query($value, $type)
    {
        if (empty($value) && (string)$value != "0") {
            return "NULL";
        } else switch ($type) {
            case DBWorker::DB_NUMBER:
                return $this->escape($value);
        
            case DBWorker::DB_DATETIME:
                return "'" . $this->format_datetime($value) . "'";
        
            case DBWorker::DB_DATE:
                return "'" . $this->format_date($value) . "'";
        
            case DBWorker::DB_GEOMETRY:
                return "ST_GeomFromText('" . $this->escape($value) . "')";
        
            case DBWorker::DB_GEOMETRY_4326:
                return "ST_GeomFromText('" . $this->escape($value) . "', 4326, 'axis-order=lat-long')";

            default:
                return "'" . $this->escape($value) . "'";
        }
    } // prepare_for_query
} // MySQL_DBWorker
