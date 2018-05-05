<?php
/**
 * This file contains the implementation of the interface IDebugProfiler 
 * in the class DebugProfiler for debugging, tracing and profiling.
 *
 * @package System
 *
 * @author Oleg Schildt 
 */
 
namespace SmartFactory;

use SmartFactory\Interfaces\IDebugProfiler;

/**
 * Class for debugging, tracing and profiling.
 *
 * @author Oleg Schildt 
 */
class DebugProfiler implements IDebugProfiler
{
  /**
   * @var string
   * Internal variable for storing the time by profiling.
   *
   * @see startProfilePoint
   * @see fixProfilePoint
   *
   * @author Oleg Schildt 
   */
  private static $profile_time;

  /**
   * Logs a message to a specified log file.
   *
   * @param string $message
   * Message to be logged.
   *
   * @param string $file_name
   * The target file name.
   *
   * @return boolean
   * It should return true if the logging was successful, otherwise false.
   *
   * @author Oleg Schildt 
   */
  public function logMessageToFile($message, $file_name)
  {
    $path = APPLICATION_ROOT . "logs/";
    $file = $path . $file_name;

    if((!file_exists($file) && is_writable($path)) || is_writable($file))
    {
      return error_log($message . "\r\n", 3, $file);
    }
    
    return false;
  } // logMessageToFile

  /**
   * Logs a message to a standard debug output (logs/debug.log).
   *
   * @param string $message
   * Message to be logged.
   *
   * @return boolean
   * It should return true if the logging was successful, otherwise false.
   *
   * @author Oleg Schildt 
   */
  public function debugMessage($message)
  {
    return $this->logMessageToFile($message, "debug.log");
  } // debugMessage

  /**
   * Logs a message to a standard profiling output (logs/profile.log) 
   * and stores the current time.
   *
   * @param string $message
   * Message to be logged.
   *
   * @return boolean
   * It should return true if the logging was successful, otherwise false.
   *
   * @see fixProfilePoint
   *
   * @author Oleg Schildt 
   */
  public function startProfilePoint($message)
  {
    $result = $this->logMessageToFile($message, "profile.log");

    self::$profile_time = microtime(true);
    
    return $result;
  } // startProfilePoint

  /**
   * Logs a message to a standard profiling output (logs/profile.log) and shows
   * the time elapsed since the last call startProfilePoint or
   * fixProfilePoint.
   *
   * @param string $message
   * Message to be logged.
   *
   * @return boolean
   * It should return true if the logging was successful, otherwise false.
   *
   * @see startProfilePoint 
   *
   * @author Oleg Schildt 
   */
  public function fixProfilePoint($message)
  {
    if(!empty(self::$profile_time)) 
    {
      $message = $message . ": " . number_format(microtime(true) - self::$profile_time, 3, ".", "") . " seconds";
    }

    $result = $this->logMessageToFile($message, "profile.log");
    
    self::$profile_time = microtime(true);
    
    return $result;
  } // fixProfilePoint

  /**
   * Clears the specified log file.
   *
   * @param string $file_name
   * The target file name.
   *
   * @return boolean
   * It should return true if the file has been successfully deleted, otherwise false.
   *
   * @author Oleg Schildt 
   */
  public function clearLogFile($file_name)
  {
    $path = APPLICATION_ROOT . "logs/";
    $file = $path . $file_name;

    if((!file_exists($file) && is_writable($path)) || is_writable($file))
    {
      return @unlink($file);
    }
    
    return false;
  } // clearLogFile

  /**
   * Clears all log files.
   *
   * @return boolean
   * It should return true if the files have been successfully deleted, otherwise false.
   *
   * @author Oleg Schildt 
   */
  public function clearLogFiles()
  {
    $dir = APPLICATION_ROOT . "logs/";
    $files = scandir($dir);
    foreach($files as $file)
    {
      if($file == "." || $file == ".." || is_dir($dir . $file)) continue;

      $pi = pathinfo($dir . $file);
      
      if(empty($pi["extension"]) || strtolower($pi["extension"]) != "log") continue;
      
      if(!$this->clearLogFile($file))
      {
        return false;
      }
    }
    
    return true;
  } // clearLogFiles
} // IDebugProfiler
