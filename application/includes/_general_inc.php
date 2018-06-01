<?php
/**
 * This is the file with general includes.
 *
 * Place your includes here if necessary.
 */

use function SmartFactory\approot;
 
/** defining the application root */
require_once "SmartFactory/application_root_inc.php";

/** class auto loading */
require_once approot() . "includes/SmartFactory/class_autoload_inc.php";

/** factory methods and bindings */
require_once approot() . "includes/SmartFactory/factory_inc.php";

/** short functions */
require_once approot() . "includes/SmartFactory/short_functions_inc.php";

/** utilities */
require_once approot() . "includes/SmartFactory/utility_functions_inc.php";

/** error handling */
require_once approot() . "includes/SmartFactory/error_handler_inc.php";

/** session handling */
require_once approot() . "includes/SmartFactory/session_handler_inc.php";

/** html_utils */
require_once approot() . "includes/SmartFactory/html_utils_inc.php";

/** user class bindings */
require_once approot() . "includes/user_factory_inc.php";

/** user includes */
require_once approot() . "includes/user_includes_inc.php";
