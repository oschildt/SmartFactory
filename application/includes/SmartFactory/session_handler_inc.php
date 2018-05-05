<?php
/**
 * Setting of the session handler.
 *
 * @package System
 *
 * @author Oleg Schildt 
 */
namespace SmartFactory;

use SmartFactory\Interfaces\ISessionManager;

//------------------------------------------------------------------------------
$smanager = singleton(ISessionManager::class);

session_set_save_handler($smanager, true);
//------------------------------------------------------------------------------
