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

use SmartFactory\Interfaces\IInitable;

/**
 * This is the abstract base class for all dbworkers for different databases.
 *
 * This is a wrapper around the database connectivity. It offers an universal 
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
   * The constant for the number type.
   */
  const db_number = 1;
  /**
   * The constant for the string type.
   */
  const db_string = 2;
  /**
   * The constant for the date type.
   */
  const db_date = 3;
  /**
   * The constant for the date/time type.
   */
  const db_datetime = 4;

  /**
   * @var string $db_server
   * Name or IP address of the server.
   *
   * @author Oleg Schildt 
   */
  protected $db_server;
  
  /**
   * @var string $db_name
   * Name of the database.
   *
   * @author Oleg Schildt 
   */
  protected $db_name;
  
  /**
   * @var string $db_user
   * Name of the database user.
   *
   * @author Oleg Schildt 
   */
  protected $db_user;
  
  /**
   * @var string $db_password
   * Password of the database user.
   *
   * @author Oleg Schildt 
   */
  protected $db_password;

  /**
   * @var string $last_error
   * This variable stores the last occured error.
   *
   * @author Oleg Schildt 
   */
  protected $last_error = NULL;

  /**
   * @var string $last_error_id
   * This variable stores the ID of the last occured error.
   *
   * @author Oleg Schildt 
   */
  protected $last_error_id = NULL;

  /**
   * @var string $last_query
   * This variable stores the last executed query.
   *
   * @author Oleg Schildt 
   */
  protected $last_query = NULL;

  /**
   * @var boolean $is_clone
   * Flag property for storing the state whether it is clone or not.
   *
   * @author Oleg Schildt 
   */
  protected $is_clone = false;

  /**
   * Initializes the dbworker with connection paramters.
   * 
   * @param array $parameters 
   * The parameters may vary for each database.
   *
   * @return boolean 
   * The method should return true upon successful initialization, otherwise false.   
   *
   * @author Oleg Schildt 
   */
  abstract public function init($parameters);
  
  /**
   * Checks whether the extension is installed which is required for work with 
   * the corresponding database.
   * 
   * @return boolean 
   * The method should return true if the extension is installed, otherwise false.   
   *
   * @see get_extension_name()
   *
   * @author Oleg Schildt 
   */
  abstract public function is_extension_installed();
  
  /**
   * Returns the name of the required PHP extension.
   * 
   * @return string 
   * Returns the name of the required PHP extension.
   *
   * @see is_extension_installed()
   *
   * @author Oleg Schildt 
   */
  abstract public function get_extension_name();
  
  /**
   * Returns the name of the supported database.
   * 
   * @return string 
   * Returns the name of the supported database.
   *
   * @author Oleg Schildt 
   */
  abstract public function get_rdbms_name();
  
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
  abstract public function create_clone();
  
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
  abstract public function is_connected();
  
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
  abstract public function connect();
  
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
  abstract public function use_database($db_name);
  
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
  abstract public function get_schema();
  
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
  abstract public function qualify_name_with_schema($name);
  
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
  abstract public function execute_query($query_string);
  
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
  abstract public function execute_procedure($procedure /* arg list */);
  
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
  abstract public function execute_prepared_query($query_string /* arg list */);
  
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
  abstract public function prepare_query($query_string);
  
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
   * ```
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
  abstract public function stream_long_data($query_string, &$stream);
  
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
  abstract public function close_connection();

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
  abstract public function start_transaction();

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
  abstract public function commit_transaction();

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
  abstract public function rollback_transaction();

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
  abstract public function free_result();

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
  abstract public function free_prepared_query();
  
  /**
   * Fetches the next row of data from the result of the execution of the retrieving query.
   * 
   * @return boolean 
   * Returns true if the next row exists and has been successfully fetched, otherwise false.
   *
   * Example:
   * ```
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
  abstract public function fetch_row();

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
   * ```
   * $rows = [];
   * $dbw->fetch_array($rows);
   *
   * rows[n] = ["col1" => "val1", "col2" => "val2", , "col3" => "val3", ...]
   * ```
   * If the dimension columns are specified, their values are used for the dimensions.
   *
   * Example:
   * ```
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
  abstract public function fetch_array(&$rows, $dimension_keys = null);
  
  /**
   * Returns the number of the rows fetched by the last retrieving query.
   * 
   * @return int|false 
   * Returns the number of the rows fetched by the last retrieving query. In the case
   * of any error returns false.
   *
   * @author Oleg Schildt 
   */
  abstract public function fetched_count();
  
  /**
   * Returns the number of the rows affected by the last modification query.
   * 
   * @return int|false 
   * Returns the number of the rows affected by the last modification query. In the case
   * of any error returns false.
   *
   * @author Oleg Schildt 
   */
  abstract public function affected_count();
  
  /**
   * Returns the number of the fields in the result of the last retrieving query.
   * 
   * @return int|false 
   * Returns the number of the fields in the result of the last retrieving query. In the case
   * of any error returns false.
   *
   * @author Oleg Schildt 
   */
  abstract public function field_count();

  /**
   * Returns the value of the auto increment field by the last insertion.
   * 
   * @return int|false 
   * Returns the value of the auto increment field by the last insertion. In the case
   * of any error returns false.
   *
   * @author Oleg Schildt 
   */
  abstract public function insert_id();

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
  abstract public function field_by_name($name);

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
  abstract public function field_by_num($num);

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
  abstract public function field_info_by_num($num);

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
   * @see field_by_num()
   * @see field_info_by_num()
   *
   * @author Oleg Schildt 
   */
  abstract public function field_name($num);

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
  abstract public function escape($str);

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
  abstract public function format_date($date);

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
  abstract public function format_datetime($datetime);

  /**
   * Returns the last occured error.
   * 
   * @return string 
   * Returns the last occured error.
   *
   * @author Oleg Schildt 
   */
  function get_last_error()
  {
    return trim($this->last_error);
  } // get_last_error

  /**
   * Returns the last occured error ID.
   * 
   * @return string 
   * Returns the last occured error ID.
   *
   * @author Oleg Schildt 
   */
  function get_last_error_id()
  {
    return trim($this->last_error_id);
  } // get_last_error_id

  /**
   * Returns the last executed query.
   * 
   * @return string 
   * Returns the last executed query.
   *
   * @author Oleg Schildt 
   */
  function get_last_query()
  {
    return trim($this->last_query);
  } // get_last_query

  /**
   * Clears the last stored occured error, its ID and the last executed query.
   * 
   * @return void 
   *
   * @author Oleg Schildt 
   */
  function clear_messages()
  {
    $this->last_error = NULL;
    $this->last_error_id = NULL;
    $this->last_query = NULL;
  } // clear_messages
} // class DBWorker
