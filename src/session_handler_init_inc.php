<?php
/**
 * Setting of the session handler.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use Smartfactory\Interfaces\ISessionManager;

//------------------------------------------------------------------------------
session_set_save_handler(singleton(ISessionManager::class), true);
//------------------------------------------------------------------------------
