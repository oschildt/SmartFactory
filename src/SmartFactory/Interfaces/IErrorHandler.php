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
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any system errors.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void;
    
    /**
     * This is the function for handling of the PHP errors. It is set in the
     * error handler.
     *
     * @param int $errno
     * Error code.
     *
     * @param string $errstr
     * Error text.
     *
     * @param string $errfile
     * Source file where the error occurred.
     *
     * @param int $errline
     * Line number where the error occurred.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): void;
    
    /**
     * This is the function for handling of the PHP exceptions. It should
     * be called in the catch block to trace detailed information
     * if an exception is thrown.
     *
     * @param \Throwable $ex
     * Thrown exception.
     *
     * @param int $errno
     * Error code.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function handleException(\Throwable $ex, int $errno): void;
    
    /**
     * Returns the last error.
     *
     * @return string
     * Returns the last error or an empty string if no error occurred so far.
     *
     * @author Oleg Schildt
     */
    public function getLastError(): string;
    
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
    public function setLastError(string $error): void;
    
    /**
     * Returns the state whether the trace is active or not.
     *
     * If the trace is active, any error, warning or notice is traced to
     * the standard file.
     *
     * @return bool
     * Returns the state whether the trace is active or not.
     *
     * @see IErrorHandler::enableTrace()
     * @see IErrorHandler::disableTrace()
     *
     * @author Oleg Schildt
     */
    public function traceActive(): bool;
    
    /**
     * Enables the trace.
     *
     * If the trace is active, any error, warning or notice is traced to
     * the standard file.
     *
     * @return void
     *
     * @see IErrorHandler::traceActive()
     * @see IErrorHandler::disableTrace()
     *
     * @author Oleg Schildt
     */
    public function enableTrace(): void;
    
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
    public function disableTrace(): void;
} // IErrorHandler
