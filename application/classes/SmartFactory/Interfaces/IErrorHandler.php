<?php
/**
 * This file contains the declaration of the interface IErrorHandler for error handling.
 *
 * @package System
 *
 * @author Oleg Schildt 
 */
 
namespace SmartFactory\Interfaces;

/**
 * Interface for error handling.
 *
 * @author Oleg Schildt 
 */
interface IErrorHandler
{
  /**
   * This is the function for handling of the PHP errors. It is set a the 
   * error handler.
   *
   * @param int $errno
   * Error code.
   *
   * @param string $errstr
   * Error text.
   *
   * @param string $errfile
   * Source file where the error occured.
   *
   * @param int $errline
   * Line number where the error occured.
   *
   * @return void
   *
   * @author Oleg Schildt 
   */
  public function handleError($errno, $errstr, $errfile, $errline);
  
  /**
   * Returns the last error.
   *
   * @return string
   * Returns the last error or an empty string if no error occured so far.
   *
   * @author Oleg Schildt 
   */
  public function getLastError();

  /**
   * Stores the last error.
   *
   * @param string $error
   * The error text to be stored.
   *
   * @return void
   *
   * @author Oleg Schildt 
   */
  public function setLastError($error);
  
  /**
   * Returns the state whether the trace is active or not.
   *
   * If the trace is active, any eror, warning or notice is traced to
   * the standard file.
   *
   * @return boolean
   * Returns the state whether the trace is active or not.
   *
   * @see enableTrace
   * @see disableTrace
   *
   * @author Oleg Schildt 
   */
  public function traceActive();
  
  /**
   * Enables the trace.
   *
   * If the trace is active, any eror, warning or notice is traced to
   * the standard file.
   *
   * @return void
   *
   * @see traceActive
   * @see disableTrace
   *
   * @author Oleg Schildt 
   */
  public function enableTrace();

  /**
   * Disables the trace.
   *
   * If the trace is active, any eror, warning or notice is traced to
   * the standard file.
   *
   * @return void
   *
   * @see traceActive
   * @see enableTrace
   *
   * @author Oleg Schildt 
   */
  public function disableTrace();
} // IErrorHandler
?>