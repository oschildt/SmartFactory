<?php
/**
 * This file contains the definition of the factory methods
 * and mapping of the implementing classes to the interfaces.
 *
 * @author Oleg Schildt
 *
 * @package Factory
 */

namespace SmartFactory;

use SmartFactory\Interfaces\IMessageManager;
use SmartFactory\Interfaces\ILanguageManager;
use SmartFactory\Interfaces\ISessionManager;
use SmartFactory\Interfaces\IErrorHandler;
use SmartFactory\Interfaces\IDebugProfiler;
use SmartFactory\Interfaces\IEventManager;
use SmartFactory\Interfaces\IRecordsetManager;

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
 * @see instance
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
 * @see singleton
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
 * Example:
 * ```
 * $dbw = dbworker(["db_type" => "MySQL",
 *                  "db_server" => "localhost",
 *                  "db_name" => "framework_demo", 
 *                  "db_user" => "root", 
 *                  "db_password" => "root",
 *                  "autoconnect" => true
 *                 ]);
 * ```
 *
 * @return \SmartFactory\DatabaseWorkers\DBWorker|null
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
    $msgmanager->setError($lngmanager->text("ErrDatabaseTypeEmpty"));
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
    $msgmanager->setError(sprintf($lngmanager->text("ErrDbExtenstionNotInstalled"), $dbworker->get_extension_name(), $dbworker->get_rdbms_name()));
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
  if($dbworker->get_last_error_id() != "") return false;

  $dbworker->init($parameters);

  if($dbworker->connect())
  {
    return $dbworker;
  }

  if($dbworker->get_last_error_id() == "conn_data_err")
  {
    $msgmanager->setError($lngmanager->text("ErrNoDBConnectionData"));
  }
  elseif($dbworker->get_last_error_id() == "conn_err")
  {
    $msgmanager->setError($lngmanager->text("ErrDbInaccessible"),
                             sprintf($lngmanager->text("ErrDbConnNoAccess"), checkempty($parameters["db_server"]), checkempty($parameters["db_user"]))
                            );
  }
  elseif($dbworker->get_last_error_id() == "db_err")
  {
    $msgmanager->setError($lngmanager->text("ErrDbInaccessible"),
                             sprintf($lngmanager->text("ErrDbConnNoDB"), checkempty($parameters["db_name"]), checkempty($parameters["db_user"]))
                            );
  }
  
  return false;
} // instance




//-------------------------------------------------------------------
// Class binding
//-------------------------------------------------------------------
FactoryBuilder::bindClass(ISessionManager::class, SessionManager::class);
//-------------------------------------------------------------------
FactoryBuilder::bindClass(IDebugProfiler::class, DebugProfiler::class);
//-------------------------------------------------------------------
FactoryBuilder::bindClass(IErrorHandler::class, ErrorHandler::class);
//-------------------------------------------------------------------
FactoryBuilder::bindClass(IEventManager::class, EventManager::class);
//-------------------------------------------------------------------
FactoryBuilder::bindClass(IMessageManager::class, MessageManager::class, function($instance) {
  $instance->init(["auto_hide_time" => 3]);
});
//-------------------------------------------------------------------
FactoryBuilder::bindClass(ILanguageManager::class, LanguageManager::class, function($instance) {
  $instance->detectLanguage();
});
//-------------------------------------------------------------------
FactoryBuilder::bindClass(JsonApiRequestHandler::class, JsonApiRequestHandler::class);
//-------------------------------------------------------------------
FactoryBuilder::bindClass(MySQL_DBWorker::class, MySQL_DBWorker::class);
//-------------------------------------------------------------------
FactoryBuilder::bindClass(MSSQL_DBWorker::class, MSSQL_DBWorker::class);
//-------------------------------------------------------------------
FactoryBuilder::bindClass(IRecordsetManager::class, RecordsetManager::class, function($instance) {
  $instance->setDBWorker(dbworker());
});
//-------------------------------------------------------------------
?>