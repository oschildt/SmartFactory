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
     * Canonical log directory with a trailing slash, or null before initialization.
     *
     * @var string|null
     *
     * @author Oleg Schildt
     */
    protected ?string $log_path = null;

    /**
     * Controls whether diagnostic messages include the calling source file and line.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $write_source_file_and_line_by_debug = false;

    /**
     * Monotonic time in seconds after the previous profile entry was written.
     * Null means that no interval has been started on this instance.
     *
     * @var float|null
     *
     * @see DebugProfiler::startProfilePoint()
     * @see DebugProfiler::fixProfilePoint()
     *
     * @author Oleg Schildt
     */
    private ?float $profile_time = null;

    /**
     * Canonical source root with a trailing slash, used to shorten source paths.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $source_root = "";

    /**
     * Correlation identifier shared by profiler instances in the current PHP request.
     *
     * @var string|null
     *
     * @author Oleg Schildt
     */
    private static ?string $request_id = null;

    /**
     * Validates and stores logging settings, then resets this instance's timer.
     * Failed initialization preserves the previous settings and timer.
     *
     * @param array{log_path: string, source_root?: string|null, write_source_file_and_line_by_debug?: mixed} $parameters
     * The existing writable log directory, optional existing source directory, and
     * optional source details flag interpreted using PHP's empty-value semantics.
     * An omitted or null source root defaults to the working directory at initialization.
     *
     * @return void
     * Completes when all settings have been accepted.
     *
     * @throws \InvalidArgumentException
     * If a directory setting is missing, empty, not a string, or contains a null byte.
     *
     * @throws \RuntimeException
     * If the log directory does not exist or is not writable, or the source root
     * cannot be resolved to an existing directory.
     *
     * @throws \Random\RandomException
     * If generating the request identifier fails because the system random source fails.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void
    {
        $log_path = $parameters["log_path"] ?? null;
        $source_root = $parameters["source_root"] ?? getcwd();

        if (!is_string($log_path) || $log_path === "" || str_contains($log_path, "\0")) {
            throw new \InvalidArgumentException("Log path must be a nonempty directory path without null bytes.");
        }

        if (!is_string($source_root) || $source_root === "" || str_contains($source_root, "\0")) {
            throw new \InvalidArgumentException("Source root must be a nonempty directory path without null bytes.");
        }

        $resolved_log_path = realpath($log_path);
        $resolved_source_root = realpath($source_root);

        if ($resolved_log_path === false || !is_dir($resolved_log_path) || !is_writable($resolved_log_path)) {
            throw new \RuntimeException(sprintf("The log directory '%s' does not exist or is not writable!", $log_path));
        }

        if ($resolved_source_root === false || !is_dir($resolved_source_root)) {
            throw new \RuntimeException(sprintf("The source root '%s' is not an existing directory!", $source_root));
        }

        self::$request_id ??= bin2hex(random_bytes(8));
        $this->log_path = rtrim(str_replace("\\", "/", $resolved_log_path), "/") . "/";
        $this->source_root = rtrim(str_replace("\\", "/", $resolved_source_root), "/") . "/";
        $this->write_source_file_and_line_by_debug = !empty($parameters["write_source_file_and_line_by_debug"]);
        $this->profile_time = null;
    } // init

    /**
     * Controls whether subsequent messages include the calling source file and line.
     *
     * @param bool $state
     * Whether source details should be included.
     *
     * @return void
     * Updates the source details flag.
     *
     * @author Oleg Schildt
     */
    public function enableFileAndLineDetails(bool $state): void
    {
        $this->write_source_file_and_line_by_debug = $state;
    } // enableFileAndLineDetails

    /**
     * Formats backtrace frames with a source file, including methods and closures.
     *
     * @param list<array{file?: string, line?: int, function?: string, class?: string, type?: string}> $btrace
     * Backtrace frames; arguments and objects are not needed.
     *
     * @return string
     * Returns the formatted stack, or an empty string if no frame has a source file.
     *
     * @author Oleg Schildt
     */
    protected function extract_call_stack(array $btrace): string
    {
        $lines = [];
        $indent = "";

        foreach ($btrace as $btrace_entry) {
            if (empty($btrace_entry["file"])) {
                continue;
            }

            $function = $btrace_entry["function"] ?? "";

            if (preg_match("/{closure:([^}{]+)}/", $function, $matches)) {
                $function = "{closure:" . $this->trim_path($matches[1]) . "}";
            }

            $call = ($btrace_entry["class"] ?? "") . ($btrace_entry["type"] ?? "") . $function;
            $call = $call === "" ? "" : $call . "()";
            $lines[] = $indent . $call . " [" . $this->trim_path($btrace_entry["file"]) . ", " .
                ($btrace_entry["line"] ?? "line number undefined") . "]";
            $indent .= "  ";
        }

        return implode(PHP_EOL, $lines);
    } // extract_call_stack

    /**
     * Appends a timestamped message with a request identifier to the chosen log.
     * Source details and the call stack are collected only when requested.
     *
     * @param string $message
     * Message to log; surrounding whitespace is removed.
     *
     * @param bool $write_call_stack
     * Whether to include the full call stack without arguments or object contents.
     *
     * @param string $file_name
     * A plain file name within the configured log directory, defaulting to debug.log.
     * Separators, colons, control characters and trailing dots or spaces are rejected.
     * Existing symbolic links and nonregular files are also rejected.
     *
     * @return void
     * Completes after the entire entry has been appended under an exclusive lock.
     *
     * @throws \LogicException
     * If init() has not completed successfully.
     *
     * @throws \InvalidArgumentException
     * If the file name is empty, a special directory name, or contains forbidden characters.
     *
     * @throws \RuntimeException
     * If the target is a symbolic link or nonregular file, or the complete entry cannot be written.
     *
     * @author Oleg Schildt
     */
    public function debugMessage(string $message, bool $write_call_stack = false, string $file_name = "debug.log"): void
    {
        $logfile = $this->getLogFilePath($file_name);
        $prefix = "#Time: " . date("Y-m-d H:i:s") . PHP_EOL . "#Request: " . self::$request_id;

        if ($this->write_source_file_and_line_by_debug || $write_call_stack) {
            $backfiles = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $write_call_stack ? 0 : 16);

            if ($this->write_source_file_and_line_by_debug) {
                foreach ($backfiles as $entry) {
                    $file = str_replace("\\", "/", $entry["file"] ?? "");

                    // Internal profile methods and the convenience wrapper are not the caller.
                    if (
                        $file === "" || $file === str_replace("\\", "/", __FILE__) ||
                        $file === str_replace("\\", "/", dirname(__DIR__) . "/short_functions_inc.php")
                    ) {
                        continue;
                    }

                    if (isset($entry["line"])) {
                        $prefix .= PHP_EOL . "#URI: " . ($_SERVER["REQUEST_URI"] ?? "") .
                            PHP_EOL . "#Source: " . $this->trim_path($file) . ", " . $entry["line"];
                    }

                    break;
                }
            }

            if ($write_call_stack) {
                $prefix .= PHP_EOL . "#Callstack:" . PHP_EOL . $this->extract_call_stack($backfiles);
            }
        }

        $message = $prefix . PHP_EOL . PHP_EOL . trim($message) . PHP_EOL . "-------" . PHP_EOL;
        $written = @file_put_contents($logfile, $message, FILE_APPEND | LOCK_EX);

        if ($written === false || $written !== strlen($message)) {
            throw new \RuntimeException(sprintf("Could not write the complete log entry to '%s'!", $logfile));
        }
    } // debugMessage

    /**
     * Writes to profile.log and starts a new monotonic interval on this instance.
     * Time spent writing the entry is excluded from the following interval.
     *
     * @param string $message
     * Message to be logged.
     *
     * @return void
     * Resets the timer after the entry has been written successfully.
     *
     * @throws \LogicException
     * If init() has not completed successfully.
     *
     * @throws \RuntimeException
     * If profile.log is a symbolic link or nonregular file, or its entry cannot be fully written.
     *
     * @see DebugProfiler::fixProfilePoint()
     *
     * @author Oleg Schildt
     */
    public function startProfilePoint(string $message): void
    {
        $this->debugMessage($message, false, "profile.log");

        $this->profile_time = hrtime(true) / 1000000000;
    } // startProfilePoint

    /**
     * Writes the elapsed interval to profile.log, then starts the next interval.
     * If no interval has been started, writes only the message and starts the timer.
     * Timers are independent per instance; log writing is excluded from intervals.
     *
     * @param string $message
     * Message to be logged.
     *
     * @return void
     * Resets the timer after the entry has been written successfully.
     *
     * @throws \LogicException
     * If init() has not completed successfully.
     *
     * @throws \RuntimeException
     * If profile.log is a symbolic link or nonregular file, or its entry cannot be fully written.
     *
     * @see DebugProfiler::startProfilePoint()
     *
     * @author Oleg Schildt
     */
    public function fixProfilePoint(string $message): void
    {
        if ($this->profile_time !== null) {
            $elapsed = hrtime(true) / 1000000000 - $this->profile_time;
            $message .= ": " . number_format($elapsed, 3, ".", "") . " seconds";
        }

        $this->debugMessage($message, false, "profile.log");

        $this->profile_time = hrtime(true) / 1000000000;
    } // fixProfilePoint

    /**
     * Deletes the specified log file; an already absent file counts as success.
     *
     * @param string $file_name
     * A plain file name subject to the same restrictions as debugMessage().
     *
     * @return void
     * Completes when the file is absent, including removal by another process.
     *
     * @throws \LogicException
     * If init() has not completed successfully.
     *
     * @throws \InvalidArgumentException
     * If the file name is empty, a special directory name, or contains forbidden characters.
     *
     * @throws \RuntimeException
     * If the target is a symbolic link or nonregular file, or deletion fails and it still exists.
     *
     * @see DebugProfiler::clearLogFiles()
     *
     * @author Oleg Schildt
     */
    public function clearLogFile(string $file_name): void
    {
        $file = $this->getLogFilePath($file_name);

        if (!file_exists($file)) {
            return;
        }

        $removed = @unlink($file);

        clearstatcache(true, $file);

        if (!$removed && (file_exists($file) || is_link($file))) {
            throw new \RuntimeException(sprintf("Could not delete the log file '%s'!", $file));
        }
    } // clearLogFile

    /**
     * Deletes files with a case-insensitive .log extension in the log directory.
     * Subdirectories and files with other extensions are preserved.
     *
     * @return void
     * Completes after matching files have been removed; absent files are accepted.
     *
     * @throws \LogicException
     * If init() has not completed successfully.
     *
     * @throws \InvalidArgumentException
     * If a matching directory entry has a file name rejected by clearLogFile().
     *
     * @throws \RuntimeException
     * If the directory cannot be listed, or a matching file is a symbolic link,
     * is nonregular, or cannot be deleted while it still exists.
     *
     * @see DebugProfiler::clearLogFile()
     *
     * @author Oleg Schildt
     */
    public function clearLogFiles(): void
    {
        $dir = $this->requireLogPath();
        $files = @scandir($dir);

        if ($files === false) {
            throw new \RuntimeException(sprintf("Could not list the log directory '%s'!", $dir));
        }

        foreach ($files as $file) {
            if (is_dir($dir . $file) || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== "log") {
                continue;
            }

            $this->clearLogFile($file);
        }
    } // clearLogFiles

    /**
     * Returns the configured log directory after checking initialization.
     *
     * @return string
     * Returns the canonical directory with a trailing slash.
     *
     * @throws \LogicException
     * If init() has not completed successfully.
     *
     * @author Oleg Schildt
     */
    protected function requireLogPath(): string
    {
        if ($this->log_path === null) {
            throw new \LogicException("The debug profiler has not been initialized!");
        }

        return $this->log_path;
    } // requireLogPath

    /**
     * Resolves a plain file name within the log directory without following symbolic links.
     *
     * @param string $file_name
     * A nonempty file name without separators, colons, control characters or trailing dots or spaces.
     *
     * @return string
     * Returns the path within the configured directory; no extension is required.
     *
     * @throws \LogicException
     * If init() has not completed successfully.
     *
     * @throws \InvalidArgumentException
     * If the file name is empty, a special directory name, or contains forbidden characters.
     *
     * @throws \RuntimeException
     * If the target is a symbolic link or an existing nonregular file.
     *
     * @author Oleg Schildt
     */
    protected function getLogFilePath(string $file_name): string
    {
        $dir = $this->requireLogPath();

        if (
            $file_name === "" || rtrim($file_name, ". ") !== $file_name ||
            preg_match('~[\\\\/:\x00-\x1F\x7F]~', $file_name)
        ) {
            throw new \InvalidArgumentException("The log file name must be a plain file name without directory components.");
        }

        $file = $dir . $file_name;

        clearstatcache(true, $file);

        if (is_link($file) || (file_exists($file) && !is_file($file))) {
            throw new \RuntimeException(sprintf("The log target '%s' must be a regular file, not a symbolic link!", $file));
        }

        return $file;
    } // getLogFilePath

    /**
     * Removes the configured source root only from the beginning of a source path.
     * Paths outside that root are preserved, with backslashes normalized to slashes.
     *
     * @param string $path
     * Source path to shorten.
     *
     * @return string
     * Returns a relative path within source_root, or the normalized original path.
     *
     * @author Oleg Schildt
     */
    protected function trim_path(string $path): string
    {
        $path = str_replace("\\", "/", $path);
        $length = strlen($this->source_root);
        $matches = DIRECTORY_SEPARATOR === "\\"
            ? strncasecmp($path, $this->source_root, $length) === 0
            : strncmp($path, $this->source_root, $length) === 0;

        if ($length > 0 && $matches) {
            return substr($path, $length);
        }

        return $path;
    } // trim_path
} // DebugProfiler
