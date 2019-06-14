<?php
/**
 * Definition of the short functions for the more confortable programming
 * and code elegance.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\Interfaces\ILanguageManager;
use SmartFactory\Interfaces\IMessageManager;
use SmartFactory\Interfaces\ISessionManager;
use SmartFactory\Interfaces\IDebugProfiler;
use SmartFactory\Interfaces\IEventManager;
use SmartFactory\Interfaces\IShardManager;

/**
 * Short function that provides the text translation for
 * the text ID for the given langauge.
 *
 * @param string $text_id
 * Text ID
 *
 * @param string $lng
 * The langauge. If it is not specified,
 * the default langauge is used.
 *
 * @param boolean $warn_missing
 * If it is set to true,
 * the E_USER_NOTICE is triggered in the case of missing
 * translations.
 *
 * @param string $default_text
 * The default text to be used if there is no translation.
 *
 * @return string
 * Returns the translation text or the $text_id if no translation
 * is found.
 *
 * @throws \Exception
 * It might throw the following exceptions in the case of any errors:
 *
 * - if the interface or class is not specified.
 * - if the interface or class does not exist.
 * - if the check of the classes and interfaces fails.
 *
 * @author Oleg Schildt
 */
function text($text_id, $lng = "", $warn_missing = true, $default_text = "")
{
    return singleton(ILanguageManager::class)->text($text_id, $lng, $warn_missing, $default_text);
} // text

/**
 * Short function for getting the instance of the IMessageManager.
 *
 * @return IMessageManager
 * Returns the instance of the IMessageManager.
 *
 * @throws \Exception
 * It might throw the following exceptions in the case of any errors:
 *
 * - if the interface or class is not specified.
 * - if the interface or class does not exist.
 * - if the check of the classes and interfaces fails.
 *
 * @author Oleg Schildt
 */
function messenger()
{
    return singleton(IMessageManager::class);
} // messenger

/**
 * Short function for getting the instance of the ISessionManager.
 *
 * @return ISessionManager
 * Returns the instance of the ISessionManager.
 *
 * @throws \Exception
 * It might throw the following exceptions in the case of any errors:
 *
 * - if the interface or class is not specified.
 * - if the interface or class does not exist.
 * - if the check of the classes and interfaces fails.
 *
 * @author Oleg Schildt
 */
function session()
{
    return singleton(ISessionManager::class);
} // session

/**
 * Short function for getting the instance of the IDebugProfiler.
 *
 * @return IDebugProfiler
 * Returns the instance of the IDebugProfiler.
 *
 * @throws \Exception
 * It might throw the following exceptions in the case of any errors:
 *
 * - if the interface or class is not specified.
 * - if the interface or class does not exist.
 * - if the check of the classes and interfaces fails.
 *
 * @author Oleg Schildt
 */
function debugger()
{
    return singleton(IDebugProfiler::class);
} // debugger

/**
 * Short function for writing debug messages to the log.
 *
 * @param string $msg
 * The message to be logged.
 *
 * @return boolean
 * Returns true if the message has been successfully logged, otherwise false.
 *
 * @throws \Exception
 * It might throw the following exceptions in the case of any errors:
 *
 * - if the interface or class is not specified.
 * - if the interface or class does not exist.
 * - if the check of the classes and interfaces fails.
 * - if the debug file is not writable.
 *
 * @author Oleg Schildt
 */
function debug_message($msg)
{
    return singleton(IDebugProfiler::class)->debugMessage($msg);
} // debug_message

/**
 * Short function for getting the instance of the IEventManager.
 *
 * @return IEventManager
 * Returns the instance of the IEventManager.
 *
 * @throws \Exception
 * It might throw the following exceptions in the case of any errors:
 *
 * - if the interface or class is not specified.
 * - if the interface or class does not exist.
 * - if the check of the classes and interfaces fails.
 *
 * @author Oleg Schildt
 */
function event()
{
    return singleton(IEventManager::class);
} // event

/**
 * Short function for getting the instance of the ConfigSettingsManager.
 *
 * @return ConfigSettingsManager
 * Returns the instance of the ConfigSettingsManager.
 *
 * @throws \Exception
 * It might throw the following exceptions in the case of any errors:
 *
 * - if the interface or class is not specified.
 * - if the interface or class does not exist.
 * - if the check of the classes and interfaces fails.
 *
 * @author Oleg Schildt
 */
function config_settings()
{
    return singleton(ConfigSettingsManager::class);
} // config_settings

/**
 * Short function for getting the instance of the RuntimeSettingsManager.
 *
 * @return RuntimeSettingsManager
 * Returns the instance of the RuntimeSettingsManager.
 *
 * @throws \Exception
 * It might throw the following exceptions in the case of any errors:
 *
 * - if the interface or class is not specified.
 * - if the interface or class does not exist.
 * - if the check of the classes and interfaces fails.
 *
 * @author Oleg Schildt
 */
function runtime_settings()
{
    return singleton(RuntimeSettingsManager::class);
} // runtime_settings

/**
 * Short function for getting the instance of the UserSettingsManager.
 *
 * @return UserSettingsManager
 * Returns the instance of the UserSettingsManager.
 *
 * @throws \Exception
 * It might throw the following exceptions in the case of any errors:
 *
 * - if the interface or class is not specified.
 * - if the interface or class does not exist.
 * - if the check of the classes and interfaces fails.
 *
 * @author Oleg Schildt
 */
function user_settings()
{
    return singleton(UserSettingsManager::class);
} // user_settings

/**
 * Short function for requesting the dbworker connected to the specified shard.
 *
 * @param string $shard_name
 * The name of the shard.
 *
 * @return \SmartFactory\DatabaseWorkers\DBWorker|null
 * returns DBWorker object or null if the object could not be created.
 *
 * @throws \Exception
 * It might throw the following exceptions in the case of any errors:
 *
 * - if the interface or class does not exist.
 * - if the shard was not found.
 * - if the check of the classes and interfaces fails.
 * - if the php extension is not installed.
 * - db_missing_type_error - if the database type is not specified.
 * - db_conn_data_error - if the connection parameters are incomplete.
 * - db_server_conn_error - if the database server cannot be connected.
 * - db_not_exists_error - if database does not exists od inaccesible to the user.
 *
 * @author Oleg Schildt
 */
function dbshard($shard_name)
{
    return singleton(IShardManager::class)->dbshard($shard_name);
} // dbshard

/**
 * Short function for requesting the dbworker connected to the specified shard,
 * that is chosen randomly for load balancing reason.
 *
 * @param string $load_balancing_group
 * The name of the shard.
 *
 * @return \SmartFactory\DatabaseWorkers\DBWorker|null
 * returns DBWorker object or null if the object could not be created.
 *
 * @throws \Exception
 * It might throw the following exceptions in the case of any errors:
 *
 * - if the load balancing group was not found.
 * - db_server_conn_error - if the database server cannot be connected.
 * - db_not_exists_error - if database does not exists od inaccesible to the user.
 *
 * @author Oleg Schildt
 */
function randomDBShard($load_balancing_group)
{
    return singleton(IShardManager::class)->randomDBShard($load_balancing_group);
} // randomDBShard
