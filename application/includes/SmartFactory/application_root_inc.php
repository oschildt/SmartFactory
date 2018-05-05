<?php
/**
 * Definition of the application root and placing the value to the constact APPLICATION_ROOT.
 *
 * @package System
 *
 * @author Oleg Schildt 
 */
$aroot = __FILE__;
$basename = basename(__FILE__);
$aroot = str_replace("\\", "/", $aroot);
$aroot = str_replace("includes/SmartFactory/$basename", "", $aroot);

/**
 * The APPLICATION_ROOT contains the path to the application root directory.
 *
 * @author Oleg Schildt
 */
define('APPLICATION_ROOT', $aroot);
?>