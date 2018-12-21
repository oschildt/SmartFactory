<?php
/**
 * This file contains the mapping of the implementing classes to the interfaces.
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
use SmartFactory\Interfaces\IShardManager;

use SmartFactory\DatabaseWorkers\MySQL_DBWorker;
use SmartFactory\DatabaseWorkers\MSSQL_DBWorker;
use SmartFactory\DatabaseWorkers\ShardManager;

//-------------------------------------------------------------------
// Class binding
//-------------------------------------------------------------------
FactoryBuilder::bindClass(ISessionManager::class, SessionManager::class);
//-------------------------------------------------------------------
FactoryBuilder::bindClass(IEventManager::class, EventManager::class);
//-------------------------------------------------------------------
FactoryBuilder::bindClass(IMessageManager::class, MessageManager::class, function ($instance) {
    $instance->init(["auto_hide_time" => 3]);
});
//-------------------------------------------------------------------
FactoryBuilder::bindClass(JsonApiRequestManager::class, JsonApiRequestManager::class);
//-------------------------------------------------------------------
FactoryBuilder::bindClass(MySQL_DBWorker::class, MySQL_DBWorker::class);
//-------------------------------------------------------------------
FactoryBuilder::bindClass(MSSQL_DBWorker::class, MSSQL_DBWorker::class);
//-------------------------------------------------------------------
FactoryBuilder::bindClass(IShardManager::class, ShardManager::class);
//-------------------------------------------------------------------
