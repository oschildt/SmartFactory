<?php
/**
 * Definition of the application root directory and placing the value to the constact APPLICATION_ROOT.
 *
 * @package System
 *
 * @author Oleg Schildt 
 */

namespace SmartFactory;

$aroot = __DIR__;
$aroot = str_replace("\\", "/", $aroot);
$aroot = str_replace("includes/SmartFactory", "", $aroot);

/**
 * The APPLICATION_ROOT contains the path to the application root directory.
 *
 * @used_by \SmartFactory\approot()
 *
 * @author Oleg Schildt
 */
define('SmartFactory\APPLICATION_ROOT', $aroot);

/**
 * Auxiliary function that returns the application root directory.
 *
 * @return string
 * Returns the application root directory.
 *
 * @see \SmartFactory\APPLICATION_ROOT
 *
 * @author Oleg Schildt
 */
function approot()
{
  return APPLICATION_ROOT;
} // approot
