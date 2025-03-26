<?php
/**
 * Setting the custome error handler.
 * Adding the event for generation of the programmer warning
 * upon a PHP error, notice or warning.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use \SmartFactory\Interfaces\IErrorHandler;
use \SmartFactory\Interfaces\IEventManager;
use \SmartFactory\Interfaces\IMessageManager;

//------------------------------------------------------------------------------
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    singleton(IErrorHandler::class)->handleError($errno, $errstr, $errfile, $errline);
});

singleton(IEventManager::class)->addHandler("php_error", function ($event, $params) {
    singleton(IMessageManager::class)->addProgWarning($params["etype"] . ": " . str_replace("<br/>", "\n", trim($params["errstr"])), $params["errfile"], $params["errline"]);
});
//------------------------------------------------------------------------------
