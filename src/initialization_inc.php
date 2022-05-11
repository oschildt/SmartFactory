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
use SmartFactory\Interfaces\ISessionManager;
use SmartFactory\Interfaces\IErrorHandler;
use SmartFactory\Interfaces\IEventManager;
use SmartFactory\Interfaces\IShardManager;

use SmartFactory\DatabaseWorkers\MySQL_DBWorker;
use SmartFactory\DatabaseWorkers\MSSQL_DBWorker;
use SmartFactory\DatabaseWorkers\ShardManager;

//-------------------------------------------------------------------
// Class binding
//-------------------------------------------------------------------
ObjectFactory::bindClass(IErrorHandler::class, ErrorHandler::class);
//-------------------------------------------------------------------
ObjectFactory::bindClass(ISessionManager::class, SessionManager::class);
//-------------------------------------------------------------------
ObjectFactory::bindClass(IEventManager::class, EventManager::class);
//-------------------------------------------------------------------
ObjectFactory::bindClass(IMessageManager::class, MessageManager::class, function ($instance) {
    $instance->init(["auto_hide_time" => 3]);
});
//-------------------------------------------------------------------
ObjectFactory::bindClass(MySQL_DBWorker::class, MySQL_DBWorker::class);
//-------------------------------------------------------------------
ObjectFactory::bindClass(MSSQL_DBWorker::class, MSSQL_DBWorker::class);
//-------------------------------------------------------------------
ObjectFactory::bindClass(IShardManager::class, ShardManager::class);
//-------------------------------------------------------------------
