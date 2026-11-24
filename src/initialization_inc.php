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
use SmartFactory\Interfaces\IErrorHandler;
use SmartFactory\Interfaces\IEventManager;
use SmartFactory\Interfaces\IShardManager;

use SmartFactory\DatabaseWorkers\MySQL_DBWorker;
use SmartFactory\DatabaseWorkers\PostgreSQL_DBWorker;
use SmartFactory\DatabaseWorkers\MSSQL_DBWorker;
use SmartFactory\DatabaseWorkers\Oracle_DBWorker;
use SmartFactory\DatabaseWorkers\ShardManager;

//-------------------------------------------------------------------
// Class binding
//-------------------------------------------------------------------
ObjectFactory::bindFactory(IErrorHandler::class, function (): ErrorHandler {
    $instance = new ErrorHandler();

    return $instance;
});

//-------------------------------------------------------------------
ObjectFactory::bindFactory(IEventManager::class, function (): EventManager {
    $instance = new EventManager();

    return $instance;
});

//-------------------------------------------------------------------
ObjectFactory::bindFactory(IMessageManager::class, function (): MessageManager {
    $instance = new MessageManager();

    $instance->init(["debug_mode" => 1]);

    return $instance;
});

//-------------------------------------------------------------------
ObjectFactory::bindFactory(MySQL_DBWorker::class, function (): MySQL_DBWorker {
    $instance = new MySQL_DBWorker();

    return $instance;
});

//-------------------------------------------------------------------
ObjectFactory::bindFactory(PostgreSQL_DBWorker::class, function (): PostgreSQL_DBWorker {
    $instance = new PostgreSQL_DBWorker();

    return $instance;
});

//-------------------------------------------------------------------
ObjectFactory::bindFactory(MSSQL_DBWorker::class, function (): MSSQL_DBWorker {
    $instance = new MSSQL_DBWorker();

    return $instance;
});

//-------------------------------------------------------------------
ObjectFactory::bindFactory(Oracle_DBWorker::class, function (): Oracle_DBWorker {
    $instance = new Oracle_DBWorker();

    return $instance;
});

//-------------------------------------------------------------------
ObjectFactory::bindFactory(IShardManager::class, function (): ShardManager {
    $instance = new ShardManager();

    return $instance;
});
//-------------------------------------------------------------------
// TODO: Override lang

// TODO: OAuth.php
// TODO: PphDoxy


