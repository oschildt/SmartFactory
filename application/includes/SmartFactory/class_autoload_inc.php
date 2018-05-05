<?php
/**
 * Class auto load method
 *
 * Base directory: classes
 *
 * Approach: PSR4
 *
 * @package System
 *
 * @author Oleg Schildt 
 */
spl_autoload_register(function ($class_name) {
  
  //echo "Looking for class: " . $class_name . "<br>";
  
  $class_path = APPLICATION_ROOT . "classes/" . str_replace("\\", "/", $class_name) . ".php";

  //echo "In path: " . $class_path . "<br>";
  
  if(file_exists($class_path)) 
  {
    //echo "FOUND!<br>";
    @include_once($class_path);
  }
  else
  {
    //echo "NOT FOUND!<br>";
  }
}); // spl_autoload_register
