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
interface IDebugProfiler extends IInitable
{
    /**
     * Initializes the debug profiler with parameters.
     *
     * @param array $parameters
     * The parameters may vary for each debug profiler.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any system errors.
     *
     * @author Oleg Schildt
     */
    public function init($parameters);
    
    /**
     * Logs a message to a standard debug output.
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
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function debugMessage($message, $file_name = "debug.log");
    
    /**
     * Logs a message to a standard profiling output and stores the current time.
     *
     * @param string $message
     * Message to be logged.
     *
     * @return boolean
     * It should return true if the logging was successful, otherwise false.
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @see IDebugProfiler::fixProfilePoint()
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
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @see IDebugProfiler::startProfilePoint()
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
     * @see IDebugProfiler::clearLogFiles()
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
     * @see IDebugProfiler::clearLogFile()
     *
     * @author Oleg Schildt
     */
    public function clearLogFiles();
} // IDebugProfiler
