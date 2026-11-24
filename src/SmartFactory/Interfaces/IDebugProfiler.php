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
     * Completes when the implementation's initialization settings have been accepted.
     *
     * @throws \Exception
     * If the supplied settings are invalid or the required logging resources cannot be initialized.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void;

    /**
     * Appends a diagnostic message to the chosen log file.
     *
     * @param string $message
     * Message to be logged.
     *
     * @param bool $write_call_stack
     * Whether the call stack should be included.
     *
     * @param string $file_name
     * A plain file name within the configured log directory, without directory components.
     *
     * @return void
     * Completes when the message has been written.
     *
     * @throws \Exception
     * If the profiler is uninitialized, the file name is invalid, or the message cannot be written.
     *
     * @author Oleg Schildt
     */
    public function debugMessage(string $message, bool $write_call_stack = false, string $file_name = "debug.log"): void;

    /**
     * Writes a profiling message and starts a new interval on this instance.
     *
     * @param string $message
     * Message to be logged.
     *
     * @return void
     * Starts the interval after the message has been written successfully.
     *
     * @throws \Exception
     * If the profiler is uninitialized or the profiling message cannot be written.
     *
     * @see IDebugProfiler::fixProfilePoint()
     *
     * @author Oleg Schildt
     */
    public function startProfilePoint(string $message): void;

    /**
     * Writes the interval since the previous startProfilePoint() or fixProfilePoint().
     * If no interval has been started, writes only the message and starts the timer.
     * Each instance maintains its own timer; log writing is excluded from intervals.
     *
     * @param string $message
     * Message to be logged.
     *
     * @return void
     * Starts the next interval after the message has been written successfully.
     *
     * @throws \Exception
     * If the profiler is uninitialized or the profiling message cannot be written.
     *
     * @see IDebugProfiler::startProfilePoint()
     *
     * @author Oleg Schildt
     */
    public function fixProfilePoint(string $message): void;

    /**
     * Deletes the specified log file; an already absent file counts as success.
     *
     * @param string $file_name
     * A plain file name within the configured log directory, without directory components.
     *
     * @return void
     * Completes when the file is absent.
     *
     * @throws \Exception
     * If the profiler is uninitialized, the file name or target is invalid,
     * or deletion fails while the file still exists.
     *
     * @see IDebugProfiler::clearLogFiles()
     *
     * @author Oleg Schildt
     */
    public function clearLogFile(string $file_name): void;

    /**
     * Deletes log files in the configured directory, preserving subdirectories.
     *
     * @return void
     * Completes when the matching files have been removed; absent files are accepted.
     *
     * @throws \Exception
     * If the profiler is uninitialized, the directory cannot be listed,
     * or a matching file is invalid or cannot be deleted while it still exists.
     *
     * @see IDebugProfiler::clearLogFile()
     *
     * @author Oleg Schildt
     */
    public function clearLogFiles(): void;
} // IDebugProfiler
