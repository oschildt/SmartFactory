<?php
/**
 * This file contains the definition of the factory functions.
 *
 * @author Oleg Schildt
 *
 * @package Factory
 */

namespace SmartFactory;

use SmartFactory\Interfaces\IMessageManager;
use SmartFactory\Interfaces\ILanguageManager;

use SmartFactory\DatabaseWorkers\MySQL_DBWorker;
use SmartFactory\DatabaseWorkers\MSSQL_DBWorker;

/**
 * The method singleton creates an object that support the specified interface and ensures 
 * that only one instance of this object exists.
 *
 * The singleton is a usual pattern for the action objects like SessionManager, EventManager, 
 * DBWorker etc. It makes no sense to produce many instances of such classes, 
 * it wastes the computer resources and might cause errors.
 *
 * @param string|object $interface_or_class 
 * Name of the class/interface as string or the class/interface.
 *
 * @return object
 * Returns object of the class bound to the interfase.
 *
 * @throws \Exception
 * - If the interface or class is not specified.
 * - If the interface or class does not exist.
 * - If the interface or class has no bound class.
 *
 * @see \SmartFactory\instance()
 *
 * @author Oleg Schildt
 */
function singleton($interface_or_class)
{
  return FactoryBuilder::getInstance($interface_or_class, true);
} // singleton

/**
 * The method instance creates an object that support the specified interface. 
 * 
 * By each request, a new object is created. If you request data objects like User, 
 * a separate instance must be created for each item.
 *
 * @param string|object $interface_or_class 
 * Name of the class/interface as string or the class/interface.
 *
 * @return object
 * Returns object of the class bound to the interface.
 *
 * @throws \Exception
 * - If the interface or class is not specified.
 * - If the interface or class does not exist.
 * - If the interface or class has no bound class.
 *
 * @see \SmartFactory\singleton()
 *
 * @author Oleg Schildt
 */
function instance($interface_or_class)
{
  return FactoryBuilder::getInstance($interface_or_class, false);
} // instance

/**
 * The method dbworker provides the DBWorker object for working with the database. 
 * 
 * If the parameters are omitted, the system takes the parameters from the configuration 
 * settings and reuses the single instance of the DBWorker for all requests. 
 * If the user passes the parameters explicitly, a new instance of the DBWorker is created upon each new request.
 *
 * Currently supported: MySQL und MS SQL.
 *
 * @param array $parameters
 * Connection settings as an associative array in the form key => value:
 *
 * - $parameters["db_type"] - type of the database (MySQL or MSSQL)
 *
 * - $parameters["db_server"] - server address
 *
 * - $parameters["db_name"] - database name
 *
 * - $parameters["db_user"] - user name
 *
 * - $parameters["db_password"] - user password
 *
 * - $parameters["autoconnect"] - should true if the connection should be established automatically
 *                 upon creation.
 *
 * - $parameters["read_only"] - this paramter sets the connection to the read only mode.
 *
 * Example:
 * ```php
 * $dbw = dbworker(["db_type" => "MySQL",
 *                  "db_server" => "localhost",
 *                  "db_name" => "framework_demo", 
 *                  "db_user" => "root", 
 *                  "db_password" => "root",
 *                  "autoconnect" => true
 *                 ]);
 * ```
 *
 * @return DatabaseWorkers\DBWorker|null
 * returns DBWorker object or null if the object could not be created.
 *
 * @author Oleg Schildt
 */
function dbworker($parameters = null)
{
  $msgmanager = singleton(IMessageManager::class);
  $lngmanager = singleton(ILanguageManager::class);

  $from_settings = false;
  
  if(empty($parameters))
  {
    $parameters["db_type"] = config_settings()->getParameter("db_type");
    $parameters["db_server"] = config_settings()->getParameter("db_server");
    $parameters["db_name"] = config_settings()->getParameter("db_name");
    $parameters["db_user"] = config_settings()->getParameter("db_user");
    $parameters["db_password"] = config_settings()->getParameter("db_password");
    $parameters["autoconnect"] = true;
    
    $from_settings = true;
  }
  
  if(empty($parameters["db_type"]))
  {
    $msgmanager->setError($lngmanager->text("ErrDatabaseTypeEmpty", "", false, "Database type is not specified!"));
    return null;
  }
  
  $class_name = "SmartFactory\\DatabaseWorkers\\" . $parameters["db_type"] . "_DBWorker";
  
  if($from_settings)
  {
    // if the dbworker is requested from the conenction settings, it should be a singleton.
    $dbworker = FactoryBuilder::getInstance($class_name, true);
  }
  else
  {
    // if the user passes the connection settings manually, create new instance each time.
    $dbworker = FactoryBuilder::getInstance($class_name, false);
  }
  
  if(!$dbworker->is_extension_installed())
  {
    $msgmanager->setError(sprintf($lngmanager->text("ErrDbExtenstionNotInstalled", "", false, "PHP extension '%s' is not installed or is too old. Work with the database '%s' is not possible!"), $dbworker->get_extension_name(), $dbworker->get_rdbms_name()));
    return null;
  }
  
  // do not connect, only object required
  // user will do connect by itself
  if(empty($parameters["autoconnect"]))
  {
    $dbworker->clear_messages();
    return $dbworker;
  }

  // instance already connected
  if($dbworker->is_connected())
  {
    $dbworker->clear_messages();
    return $dbworker;
  }
  
  // try to connect only if first time
  // if сonnection alredy tried and failed
  // do not try again within one request
  if($dbworker->get_last_error_id() != "") return null;

  $dbworker->init($parameters);

  if($dbworker->connect())
  {
    return $dbworker;
  }

  if($dbworker->get_last_error_id() == "conn_data_err")
  {
    $msgmanager->setError($lngmanager->text("ErrNoDBConnectionData", "", false, "No database connection information is available ot it is incomplete!"));
  }
  elseif($dbworker->get_last_error_id() == "conn_err")
  {
    $msgmanager->setError($lngmanager->text("ErrDbInaccessible", "", false, "The database cannot be connected!"),
                             sprintf($lngmanager->text("ErrDbConnNoAccess", "", false, "The server '%s' is unreachable or the user login '%s' or password are invalid!"), checkempty($parameters["db_server"]), checkempty($parameters["db_user"]))
                            );
  }
  elseif($dbworker->get_last_error_id() == "db_err")
  {
    $msgmanager->setError($lngmanager->text("ErrDbInaccessible", "", false, "The database cannot be connected!"),
                             sprintf($lngmanager->text("ErrDbConnNoDB", "", false, "The database '%s' does not exist or is not accessible for this database user '%s'!"), checkempty($parameters["db_name"]), checkempty($parameters["db_user"]))
                            );
  }
  
  return null;
} // instance

