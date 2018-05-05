<?php
/**
 * This file contains the declaration of the interface IDebugProfiler for debugging, tracing and profiling.
 *
 * @package System
 *
 * @author Oleg Schildt 
 */

namespace SmartFactory\Interfaces;

/**
 * Interface for debugging, tracing and profiling.
 *
 * @author Oleg Schildt 
 */
interface IDebugProfiler
{
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
  public function logMessageToFile($message, $file_name);

  /**
   * Logs a message to a standard debug output.
   *
   * @param string $message
   * Message to be logged.
   *
   * @return boolean
   * It should return true if the logging was successful, otherwise false.
   *
   * @author Oleg Schildt 
   */
  public function debugMessage($message);

  /**
   * Logs a message to a standard profiling output and stores the current time.
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
  public function startProfilePoint($message);
  
  /**
   * Logs a message to a standard profiling output and shows
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
  public function fixProfilePoint($message);

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
  public function clearLogFile($file_name);

  /**
   * Clears all log files.
   *
   * @return boolean
   * It should return true if the files have been successfully deleted, otherwise false.
   *
   * @author Oleg Schildt 
   */
  public function clearLogFiles();
} // IDebugProfiler
?>