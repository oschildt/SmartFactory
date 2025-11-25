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
    public function init(array $parameters): void;
    
    /**
     * Logs a message to a standard debug output.
     *
     * @param string $message
     * Message to be logged.
     *
     * @param bool $write_call_stack
     * Flag that defines where the call stack should be written.
     *
     * @param string $file_name
     * The target file name.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function debugMessage(string $message, bool $write_call_stack = false, string $file_name = "debug.log"): void;
    
    /**
     * Logs a message to a standard profiling output and stores the current time.
     *
     * @param string $message
     * Message to be logged.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @see IDebugProfiler::fixProfilePoint()
     *
     * @author Oleg Schildt
     */
    public function startProfilePoint(string $message): void;
    
    /**
     * Logs a message to a standard profiling output and shows
     * the time elapsed since the last call startProfilePoint or
     * fixProfilePoint.
     *
     * @param string $message
     * Message to be logged.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @see IDebugProfiler::startProfilePoint()
     *
     * @author Oleg Schildt
     */
    public function fixProfilePoint(string $message): void;
    
    /**
     * Clears the specified log file.
     *
     * @param string $file_name
     * The target file name.
     *
     * @return void
     *
     * @see IDebugProfiler::clearLogFiles()
     *
     * @author Oleg Schildt
     */
    public function clearLogFile(string $file_name): void;
    
    /**
     * Clears all log files.
     *
     * @return void
     *
     * @see IDebugProfiler::clearLogFile()
     *
     * @author Oleg Schildt
     */
    public function clearLogFiles(): void;
} // IDebugProfiler
