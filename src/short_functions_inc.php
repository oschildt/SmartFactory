<?php
/**
 * Provides concise helpers for shared services and explicit database LOB values.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\Interfaces\ILanguageManager;
use SmartFactory\Interfaces\IMessageManager;
use SmartFactory\Interfaces\IDebugProfiler;
use SmartFactory\Interfaces\IEventManager;
use SmartFactory\Interfaces\IShardManager;

/**
 * Creates an explicit UTF-8 CLOB bind value without consuming its source.
 *
 * @param string|resource $value
 * The UTF-8 string or open caller-owned stream. The stream is not read or closed.
 *
 * @return DatabaseWorkers\ClobValue
 * Returns a wrapper retaining the original string or stream.
 *
 * @throws DatabaseWorkers\DBWorkerException
 * If the value is neither a string nor an open stream.
 *
 * @author Oleg Schildt
 */
function clob(mixed $value): DatabaseWorkers\ClobValue
{
    return new DatabaseWorkers\ClobValue($value);
} // clob

/**
 * Creates an explicit binary BLOB bind value without consuming its source.
 *
 * @param string|resource $value
 * The byte string or open caller-owned stream. The stream is not read or closed.
 *
 * @return DatabaseWorkers\BlobValue
 * Returns a wrapper retaining the original string or stream.
 *
 * @throws DatabaseWorkers\DBWorkerException
 * If the value is neither a string nor an open stream.
 *
 * @author Oleg Schildt
 */
function blob(mixed $value): DatabaseWorkers\BlobValue
{
    return new DatabaseWorkers\BlobValue($value);
} // blob

/**
 * Short function that provides the text translation for
 * the text ID for the given language.
 *
 * @param string $text_id
 * Text ID
 *
 * @param string $lng
 * The language. If it is not specified,
 * the default language is used.
 *
 * @param string $default_text
 * The default text to be used if there is no translation.
 *
 * @return string
 * Returns the translation text or the $default_text/$text_id if no translation
 * is found.
 *
 * @throws ObjectFactoryException
 * If the service binding is missing, the contract is unknown, a circular dependency
 * is detected, or the factory result does not satisfy the requested contract.
 *
 * @throws \Throwable
 * If creating the language manager or resolving the requested translation throws.
 *
 * @author Oleg Schildt
 */
function text(string $text_id, string $lng = "", string $default_text = ""): string
{
    return singleton(ILanguageManager::class)->text($text_id, $lng, $default_text);
} // text

/**
 * Short function for getting the instance of the IMessageManager.
 *
 * @return IMessageManager
 * Returns the instance of the IMessageManager.
 *
 * @throws ObjectFactoryException
 * If the service binding is missing, the contract is unknown, a circular dependency
 * is detected, or the factory result does not satisfy the requested contract.
 *
 * @throws \Throwable
 * If autoloading the service contract or executing its creation function throws.
 *
 * @author Oleg Schildt
 */
function messenger(): IMessageManager
{
    return singleton(IMessageManager::class);
} // messenger

/**
 * Short function for getting the instance of the IDebugProfiler.
 *
 * @return IDebugProfiler
 * Returns the instance of the IDebugProfiler.
 *
 * @throws ObjectFactoryException
 * If the service binding is missing, the contract is unknown, a circular dependency
 * is detected, or the factory result does not satisfy the requested contract.
 *
 * @throws \Throwable
 * If autoloading the service contract or executing its creation function throws.
 *
 * @author Oleg Schildt
 */
function debugger(): IDebugProfiler
{
    return singleton(IDebugProfiler::class);
} // debugger

/**
 * Short function for writing debug messages to the log.
 *
 * @param string $msg
 * The message to be logged.
 *
 * @return void
 * Writes the message through the shared profiler.
 *
 * @throws ObjectFactoryException
 * If the service binding is missing, the contract is unknown, a circular dependency
 * is detected, or the factory result does not satisfy the requested contract.
 *
 * @throws \Throwable
 * If creating the profiler or writing its debug message fails, including an unwritable log file.
 *
 * @author Oleg Schildt
 */
function debug_message(string $msg): void
{
    singleton(IDebugProfiler::class)->debugMessage($msg);
} // debug_message

/**
 * Short function for getting the instance of the IEventManager.
 *
 * @return IEventManager
 * Returns the instance of the IEventManager.
 *
 * @throws ObjectFactoryException
 * If the service binding is missing, the contract is unknown, a circular dependency
 * is detected, or the factory result does not satisfy the requested contract.
 *
 * @throws \Throwable
 * If autoloading the service contract or executing its creation function throws.
 *
 * @author Oleg Schildt
 */
function event(): IEventManager
{
    return singleton(IEventManager::class);
} // event

/**
 * Short function for getting the instance of the ConfigSettingsManager.
 *
 * @return ConfigSettingsManager
 * Returns the instance of the ConfigSettingsManager.
 *
 * @throws ObjectFactoryException
 * If the service binding is missing, the contract is unknown, a circular dependency
 * is detected, or the factory result does not satisfy the requested contract.
 *
 * @throws \Throwable
 * If autoloading the service contract or executing its creation function throws.
 *
 * @author Oleg Schildt
 */
function config_settings(): ConfigSettingsManager
{
    return singleton(ConfigSettingsManager::class);
} // config_settings

/**
 * Returns the shared application settings manager backed by centralized database storage.
 *
 * @return ApplicationSettingsManager
 * Returns the configured application settings manager.
 *
 * @throws \Throwable
 * If the factory binding is missing or creating and initializing the manager fails.
 *
 * @author Oleg Schildt
 */
function application_settings(): ApplicationSettingsManager
{
    return singleton(ApplicationSettingsManager::class);
} // application_settings

/**
 * Short function for getting the instance of the UserSettingsManager.
 *
 * @return UserSettingsManager
 * Returns the instance of the UserSettingsManager.
 *
 * @throws ObjectFactoryException
 * If the service binding is missing, the contract is unknown, a circular dependency
 * is detected, or the factory result does not satisfy the requested contract.
 *
 * @throws \Throwable
 * If autoloading the service contract or executing its creation function throws.
 *
 * @author Oleg Schildt
 */
function user_settings(): UserSettingsManager
{
    return singleton(UserSettingsManager::class);
} // user_settings

/**
 * Short function for requesting the dbworker connected to the specified shard.
 *
 * @param string $shard_name
 * The name of the shard.
 *
 * @return ?\SmartFactory\DatabaseWorkers\DBWorker
 * Returns the shard worker, or null if a custom shard manager returns no worker.
 *
 * @throws ObjectFactoryException
 * If the shard manager binding is missing or resolving its factory fails validation.
 *
 * @throws \Exception
 * If the shard is unknown, its driver or native extension is unavailable,
 * connection settings are invalid, or establishing the connection fails.
 *
 * @throws \Throwable
 * If creating or initializing a custom shard manager or worker throws an exception or error.
 *
 * @author Oleg Schildt
 */
function dbshard(string $shard_name): ?\SmartFactory\DatabaseWorkers\DBWorker
{
    return singleton(IShardManager::class)->dbshard($shard_name);
} // dbshard

/**
 * Returns a randomly selected shard worker through the shared shard manager.
 *
 * Uses deterministic client affinity when enabled in the manager.
 *
 * @param string $load_balancing_group
 * The name of a nonempty registered load-balancing group.
 *
 * @return ?\SmartFactory\DatabaseWorkers\DBWorker
 * Returns the selected shard's worker, or null if a custom shard manager returns no worker.
 *
 * @throws \Exception
 * If the manager cannot be resolved, the group is unknown, worker initialization fails,
 * or the required client identity is unavailable or invalid.
 *
 * @throws \Throwable
 * If creating a custom manager or worker, or calling the configured identifier getter,
 * throws an exception or error, it is propagated unchanged.
 *
 * @author Oleg Schildt
 */
function randomDBShard(string $load_balancing_group): ?\SmartFactory\DatabaseWorkers\DBWorker
{
    return singleton(IShardManager::class)->randomDBShard($load_balancing_group);
} // randomDBShard
