<?php
/**
 * This file contains the definition of the factory functions.
 *
 * @author Oleg Schildt
 *
 * @package Factory
 */

namespace SmartFactory;

use \SmartFactory\DatabaseWorkers\DBWorker;

/**
 * The method singleton creates an object that supports the specified interface and ensures
 * that only one instance of this object exists.
 *
 * The singleton is a usual pattern for the action objects like SessionManager, EventManager,
 * DBWorker etc. It makes no sense to produce many instances of such classes,
 * it wastes the computer resources and might cause errors.
 *
 * @param string|object $interface_or_class
 * Name of the class/interface as string or the class/interface.
 *
 * @param string $context
 * The context of binding. You can introduce a different context, and bind another implementation.
 * Then, you can request another instance specifying the context explicitly.
 *
 * @return object
 * Returns object of the class bound to the interfase.
 *
 * @throws \Exception
 * It might throw the following exceptions in the case of any errors:
 *
 * - if the interface or class is not specified.
 * - if the interface or class does not exist.
 * - if the check of the classes and interfaces fails.
 *
 * @see \SmartFactory\instance()
 *
 * @author Oleg Schildt
 */
function singleton(string|object $interface_or_class, string $context = "default"): object
{
    return ObjectFactory::getInstance($interface_or_class, true, $context);
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
 * @param string $context
 * The context of binding. You can introduce a different context, and bind another implementation.
 * Then, you can request another instance specifying the context explicitly.
 *
 * @return object
 * Returns object of the class bound to the interface.
 *
 * @throws \Exception
 * It might throw the following exceptions in the case of any errors:
 *
 * - if the interface or class is not specified.
 * - if the interface or class does not exist.
 * - if the check of the classes and interfaces fails.
 *
 * @see \SmartFactory\singleton()
 *
 * @author Oleg Schildt
 */
function instance(string|object $interface_or_class, string  $context = "default"): object
{
    return ObjectFactory::getInstance($interface_or_class, false, $context);
} // instance

/**
 * The method dbworker provides the DBWorker object for working with the database.
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
 * @param bool $singleton
 * If the parameter is true, it ensures that only one instance of this object exists.
 *
 * @return ?DatabaseWorkers\DBWorker
 * returns DBWorker object or null if the object could not be created.
 *
 * @throws \Exception
 * It might throw the following exceptions in the case of any errors.
 *
 * @author Oleg Schildt
 */
function dbworker(array $parameters = [], bool $singleton = true) : ?DatabaseWorkers\DBWorker
{
    if (empty($parameters["db_type"])) {
        throw new \Exception("Database type is not specified!");
    }

    $class_name = "SmartFactory\\DatabaseWorkers\\" . $parameters["db_type"] . "_DBWorker";

    $dbworker = ObjectFactory::getInstance($class_name, $singleton);

    if (!$dbworker->is_extension_installed()) {
        throw new \Exception(sprintf("PHP extension '%s' is not installed or is too old. Work with the database '%s' is not possible!", $dbworker->get_extension_name(), $dbworker->get_rdbms_name()));
    }

    // instance already connected
    if ($dbworker->is_connected()) {
        return $dbworker;
    }

    $dbworker->init($parameters);

    // do not connect, only object required
    // user will do connect by itself
    if (empty($parameters["autoconnect"])) {
        return $dbworker;
    }

    $dbworker->connect();

    return $dbworker;
} // dbworker

