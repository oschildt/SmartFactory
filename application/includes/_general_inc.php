<?php
/**
 * This is the file with general includes.
 *
 * Place your includes here if necessary.
 */
 
/** defining the application root */
require_once "SmartFactory/application_root_inc.php";

/** class auto loading */
require_once APPLICATION_ROOT . "includes/SmartFactory/class_autoload_inc.php";

/** factory methods and bindings */
require_once APPLICATION_ROOT . "includes/SmartFactory/factory_inc.php";

/** short functions */
require_once APPLICATION_ROOT . "includes/SmartFactory/short_functions_inc.php";

/** utilities */
require_once APPLICATION_ROOT . "includes/SmartFactory/utility_functions_inc.php";

/** error handling */
require_once APPLICATION_ROOT . "includes/SmartFactory/error_handler_inc.php";

/** session handling */
require_once APPLICATION_ROOT . "includes/SmartFactory/session_handler_inc.php";

/** html_utils */
require_once APPLICATION_ROOT . "includes/SmartFactory/html_utils_inc.php";

/** user class bindings */
require_once APPLICATION_ROOT . "includes/user_factory_inc.php";

/** user includes */
require_once APPLICATION_ROOT . "includes/user_includes_inc.php";
