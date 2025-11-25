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

use \SmartFactory\Interfaces\IDebugProfiler;

/**
 * Class for debugging, tracing and profiling.
 *
 * @author Oleg Schildt
 */
class DebugProfiler implements IDebugProfiler
{
    /**
     * Internal variable for storing the log path.
     *
     * @var ?string
     *
     * @author Oleg Schildt
     */
    protected ?string $log_path = null;

    /**
     * Internal variable for storing the flag that defines whether for each debug output the source file and
     * line number are written from where it is called.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $write_source_file_and_line_by_debug = false;

    /**
     * Internal variable for storing the time by profiling.
     *
     * @var int
     *
     * @see DebugProfiler::startProfilePoint()
     * @see DebugProfiler::fixProfilePoint()
     *
     * @author Oleg Schildt
     */
    private static int $profile_time;

    /**
     * Initializes the debug profiler with parameters.
     *
     * @param array $parameters
     * Settings for logging as an associative array in the form key => value:
     *
     * - $parameters["log_path"] - the target file path where the logs should be stored.
     *
     * - $parameters["write_source_file_and_line_by_debug"] - the flag that defines whether for each debug output the source file and
     * line number are written from where it is called.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any system errors:
     *
     * - if the log path is not specified.
     * - if the trace file is not writable.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void
    {
        $this->write_source_file_and_line_by_debug = !empty($parameters["write_source_file_and_line_by_debug"]);

        if (empty($parameters["log_path"])) {
            throw new \Exception("Log path is not specified!");
        }

        $this->log_path = rtrim(str_replace("\\", "/", $parameters["log_path"]), "/") . "/";

        if (!file_exists($this->log_path) || !is_writable($this->log_path)) {
            throw new \Exception(sprintf("The log path '%s' is not writable!", $this->log_path));
        }
    }

    /**
     * Sets the flag that defines whether the file name and the line number should be written along with the message.
     *
     * @param bool $state
     * the flag that defines whether the file name and the line number should be written along with the message.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function enableFileAndLineDetails(bool $state): void
    {
        $this->write_source_file_and_line_by_debug = $state;
    }

    /**
     * Extracts the call stack from the standard PHP backtrace (debug_backtrace).
     *
     * @param array $btrace
     * The backtrace.
     *
     * @return string
     * Returns the extracted call stack.
     *
     * @author Oleg Schildt
     */
    protected function extract_call_stack(array $btrace): string
    {
        if (empty($btrace)) {
            return "";
        }

        $trace = "";

        $indent = "";
        foreach ($btrace as $btrace_entry) {

            if (!empty($btrace_entry["function"]) && ($btrace_entry["function"] == "handle_error" || str_contains($btrace_entry["function"], "{closure}") || $btrace_entry["function"] == "handleError" || $btrace_entry["function"] == "trigger_error")) {
                continue;
            }

            if (empty($btrace_entry["file"])) {
                continue;
            }

            if (!empty($btrace_entry["function"])) {
                $function = $btrace_entry["function"];

                if (preg_match("/{closure:([^}{]+)}/", $function, $matches)) {
                    $trace .= $indent . "{closure:" . $this->trim_path(str_replace("\\", "/", $matches[1])) . "}" . "(";
                } else {
                    $trace .= $indent . str_replace("SmartFactory\\", "", $function) . "(";
                }

                $trace .= ")";
            }

            $trace .= " [";

            $trace .= $this->trim_path(str_replace("\\", "/", $btrace_entry["file"]));

            $trace .= ", ";

            if (empty($btrace_entry["line"])) {
                $trace .= "line number undefined";
            } else {
                $trace .= $btrace_entry["line"];
            }

            $trace .= "]";

            $trace .= "\r\n";

            $indent .= "  ";
        }

        return trim($trace);
    } // extract_call_stack

    /**
     * Logs a message to a standard debug output (logs/debug.log).
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
     * It might throw an exception in the case of any errors:
     *
     * - if the debug file is not writable.
     *
     * @author Oleg Schildt
     */
    public function debugMessage(string $message, bool $write_call_stack = false, string $file_name = "debug.log"): void
    {
        $logfile = $this->log_path . $file_name;

        $backfiles = debug_backtrace();

        if (basename($backfiles[0]['file']) == "short_functions_inc.php" && $backfiles[0]['function'] == "debugMessage") {
            $file = empty($backfiles[1]['file']) ? "" : $backfiles[1]['file'];
            $line = empty($backfiles[1]['line']) ? "" : $backfiles[1]['line'];
        } else {
            $file = empty($backfiles[0]['file']) ? "" : $backfiles[0]['file'];
            $line = empty($backfiles[0]['line']) ? "" : $backfiles[0]['line'];
        }

        $prefix = "";

        if ($this->write_source_file_and_line_by_debug && !empty($file) && !empty($line)) {
            $prefix = "#URI: " . ($_SERVER["REQUEST_URI"] ?? "") . "\r\n" .
                "#Source: " . $this->trim_path(str_replace("\\", "/", $file)) . ", " . $line;
        }

        if ($write_call_stack) {
            $prefix .= "\r\n#Callstack:\r\n" . $this->extract_call_stack($backfiles);
        }

        $prefix .= "\r\n\r\n";

        $message = $prefix . trim($message);

        $message .= "\r\n-------\r\n";

        if ((!file_exists($logfile) && is_writable($this->log_path)) || is_writable($logfile)) {
            if (file_put_contents($logfile, $message, FILE_APPEND) === false) {
                throw new \Exception(sprintf("Error by writing the log file '%s'!", $logfile));
            }
        } else {
            throw new \Exception(sprintf("The log file '%s' is not writable!", $logfile));
        }
    } // debugMessage

    /**
     * Logs a message to a standard profiling output (logs/profile.log)
     * and stores the current time.
     *
     * @param string $message
     * Message to be logged.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if the profile file is not writable.
     *
     * @see DebugProfiler::fixProfilePoint()
     *
     * @author Oleg Schildt
     */
    public function startProfilePoint(string $message): void
    {
        $this->debugMessage($message, false,"profile.log");

        self::$profile_time = microtime(true);
    } // startProfilePoint

    /**
     * Logs a message to a standard profiling output (logs/profile.log) and shows
     * the time elapsed since the last call startProfilePoint or
     * fixProfilePoint.
     *
     * @param string $message
     * Message to be logged.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if the profile file is not writable.
     *
     * @see DebugProfiler::startProfilePoint()
     *
     * @author Oleg Schildt
     */
    public function fixProfilePoint(string $message): void
    {
        if (!empty(self::$profile_time)) {
            $message = $message . ": " . number_format(microtime(true) - self::$profile_time, 3, ".", "") . " seconds";
        }

        $this->debugMessage($message, false, "profile.log");

        self::$profile_time = microtime(true);
    } // fixProfilePoint

    /**
     * Clears the specified log file.
     *
     * @param string $file_name
     * The target file name.
     *
     * @return void
     *
     * @see DebugProfiler::clearLogFiles()
     *
     * @author Oleg Schildt
     */
    public function clearLogFile(string $file_name): void
    {
        $file = $this->log_path . $file_name;

        if ((!file_exists($file) && is_writable($this->log_path)) || is_writable($file)) {
            @unlink($file);
        }
    } // clearLogFile

    /**
     * Clears all log files.
     *
     * @return void
     *
     * @see DebugProfiler::clearLogFile()
     *
     * @author Oleg Schildt
     */
    public function clearLogFiles(): void
    {
        $dir = $this->log_path;
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file == "." || $file == ".." || is_dir($dir . $file)) {
                continue;
            }

            $pi = pathinfo($dir . $file);

            if (empty($pi["extension"]) || strtolower($pi["extension"]) != "log") {
                continue;
            }

            $this->clearLogFile($file);
        }
    } // clearLogFiles

    /**
     * This is an auxiliary function that cuts off the common part of the path.
     *
     * @param string $path
     * The path.
     *
     * @return string
     * Returns the cut path.
     *
     * @author Oleg Schildt
     */
    protected function trim_path(string $path): string
    {
        $common_prefix = common_prefix(str_replace("\\", "/", __DIR__), $path);

        return str_replace($common_prefix, "", $path);
    } // trim_path
} // DebugProfiler
