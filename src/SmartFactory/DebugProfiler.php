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
     * Internal variable for storing the log path.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected $log_path;

    /**
     * Internal variable for storing the flag that defines whether for each debug output the source file and
     * line number are written from where it is called.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected $write_source_file_and_line_by_debug;

    /**
     * Internal variable for storing the time by profiling.
     *
     * @var string
     *
     * @see DebugProfiler::startProfilePoint()
     * @see DebugProfiler::fixProfilePoint()
     *
     * @author Oleg Schildt
     */
    private static $profile_time;
    
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
    public function init($parameters)
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
     * Logs a message to a standard debug output (logs/debug.log).
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
     * It might throw an exception in the case of any errors:
     *
     * - if the debug file is not writable.
     *
     * @author Oleg Schildt
     */
    public function debugMessage($message, $file_name = "debug.log")
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

        $appendix = "";
        if($this->write_source_file_and_line_by_debug && !empty($file) && !empty($line)) {
            $appendix = "\r\n#source: " . $file . ", " . $line . "\r\n\r\n";
        }

        if ((!file_exists($logfile) && is_writable($this->log_path)) || is_writable($logfile)) {
            return file_put_contents($logfile, $appendix . trim($message) . "\r\n", FILE_APPEND) !== false;
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
     * @return boolean
     * It should return true if the logging was successful, otherwise false.
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
    public function startProfilePoint($message)
    {
        $result = $this->debugMessage($message, "profile.log");
        
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
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if the profile file is not writable.
     *
     * @see DebugProfiler::startProfilePoint()
     *
     * @author Oleg Schildt
     */
    public function fixProfilePoint($message)
    {
        if (!empty(self::$profile_time)) {
            $message = $message . ": " . number_format(microtime(true) - self::$profile_time, 3, ".", "") . " seconds";
        }
        
        $result = $this->debugMessage($message, "profile.log");
        
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
     * @see DebugProfiler::clearLogFiles()
     *
     * @author Oleg Schildt
     */
    public function clearLogFile($file_name)
    {
        $file = $this->log_path . $file_name;
        
        if ((!file_exists($file) && is_writable($this->log_path)) || is_writable($file)) {
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
     * @see DebugProfiler::clearLogFile()
     *
     * @author Oleg Schildt
     */
    public function clearLogFiles()
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
            
            if (!$this->clearLogFile($file)) {
                return false;
            }
        }
        
        return true;
    } // clearLogFiles
} // DebugProfiler
