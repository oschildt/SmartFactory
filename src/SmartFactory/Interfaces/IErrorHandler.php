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
interface IErrorHandler extends IInitable
{
    /**
     * Initializes the error handler with parameters.
     *
     * @param array $parameters
     * The parameters may vary for each error handler.
     *
     * @return boolean
     * The method should return true upon successful initialization, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function init($parameters);
    
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
     * This is the function for handling of the PHP exceptions. It should
     * be called in the catch block to trace detailed infromation
     * if an exception is thrown.
     *
     * @param \Throwable $ex
     * Thrown exception.
     *
     * @param string $errfuntion
     * Funtion name where the exception has been catched.
     *
     * @param string $errfile
     * Source file where the exception has been catched.
     *
     * @param int $errline
     * Line number where the exception has been catched.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function handleException($ex, $errfuntion, $errfile, $errline);
    
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
     * @see IErrorHandler::enableTrace()
     * @see IErrorHandler::disableTrace()
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
     * @see IErrorHandler::traceActive()
     * @see IErrorHandler::disableTrace()
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
     * @see IErrorHandler::traceActive()
     * @see IErrorHandler::enableTrace()
     *
     * @author Oleg Schildt
     */
    public function disableTrace();
} // IErrorHandler
