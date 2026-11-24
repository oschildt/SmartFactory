<?php
/**
 * This file implements IErrorHandler in ErrorHandler for bounded diagnostic logging.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\Interfaces\IErrorHandler;

/**
 * Logs PHP errors and exception chains without implicitly writing to HTTP responses.
 * Argument values are omitted by default; explicit argument tracing still masks
 * sensitive parameter names, array keys and SensitiveParameterValue instances.
 * Arbitrary error messages are retained and must not contain application secrets.
 *
 * @author Oleg Schildt
 */
class ErrorHandler implements IErrorHandler
{
    /**
     * Array nesting depth at which formatting replaces the array with an ellipsis.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    private const int MAX_ARRAY_DEPTH = 4;

    /**
     * Maximum number of items shown per array in a stack trace argument.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    private const int MAX_ARRAY_ITEMS = 3;

    /**
     * Maximum number of arguments shown per stack frame.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    private const int MAX_ARGUMENTS = 16;

    /**
     * Maximum number of stack frames inspected when formatting a trace.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    private const int MAX_FRAMES = 64;

    /**
     * Maximum number of exceptions formatted from a chain, including the original exception.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    private const int MAX_EXCEPTIONS = 8;

    /**
     * Maximum length in bytes of a formatted diagnostic, including the truncation marker if present.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    private const int MAX_MESSAGE_BYTES = 65536;

    /**
     * Shared labels for PHP error levels accepted by both handling methods.
     *
     * @var array<int, string>
     *
     * @author Oleg Schildt
     */
    private const array ERROR_TYPES = [
        E_ERROR => "Error",
        E_WARNING => "Warning",
        E_PARSE => "Parsing Error",
        E_NOTICE => "Notice",
        E_CORE_ERROR => "Core Error",
        E_CORE_WARNING => "Core Warning",
        E_COMPILE_ERROR => "Compile Error",
        E_COMPILE_WARNING => "Compile Warning",
        E_USER_ERROR => "User Error",
        E_USER_WARNING => "User Warning",
        E_USER_NOTICE => "User Notice",
        E_RECOVERABLE_ERROR => "Recoverable Error",
        E_DEPRECATED => "Deprecated Notice",
        E_USER_DEPRECATED => "User Deprecated Notice"
    ];

    /**
     * Canonical log directory with a trailing slash, explicit stdout, or null before initialization.
     *
     * @var string|null
     *
     * @author Oleg Schildt
     */
    protected ?string $log_path = null;

    /**
     * Canonical source root with a trailing slash; empty uses the current working directory.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $source_root = "";

    /**
     * Enables bounded argument values; false omits values from call stacks.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $trace_arguments = false;

    /**
     * Allows explicit stdout diagnostics in a web SAPI for development only.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $display_errors = false;

    /**
     * Result of the last attempted write, or null if this instance has not attempted a write.
     *
     * @var bool|null
     *
     * @author Oleg Schildt
     */
    protected ?bool $last_trace_succeeded = null;

    /**
     * Last explicitly stored error, shared by handler instances in this PHP request.
     *
     * @var string
     *
     * @see ErrorHandler::getLastError()
     * @see ErrorHandler::setLastError()
     *
     * @author Oleg Schildt
     */
    protected static string $last_error = "";

    /**
     * Shared tracing switch; event notifications are controlled separately by error_reporting().
     *
     * @var bool
     *
     * @see ErrorHandler::traceActive()
     * @see ErrorHandler::enableTrace()
     * @see ErrorHandler::disableTrace()
     *
     * @author Oleg Schildt
     */
    protected static bool $trace_disabled = false;

    /**
     * Prevents recursive event notifications and logging while handling a PHP error.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    private static bool $handling_error = false;

    /**
     * Validates logging settings without changing existing settings on failure.
     *
     * @param array{log_path: string, source_root?: string|null, trace_arguments?: bool, display_errors?: bool} $parameters
     * An existing writable log directory or "stdout"; source_root defaults to the
     * working directory. Argument tracing and web stdout display default to false.
     * Web stdout requires display_errors=true; otherwise it uses PHP's server log.
     *
     * @return void
     * Stores the validated settings and resets the last write result.
     *
     * @throws \InvalidArgumentException
     * If a path is empty, not a string or contains null bytes, or a flag is not boolean.
     *
     * @throws \RuntimeException
     * If the log directory or source root cannot be resolved, the log directory is
     * not writable, or trace.log is a symbolic link, nonregular file or unwritable file.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void
    {
        $log_path = $parameters["log_path"] ?? null;
        $source_root = $parameters["source_root"] ?? getcwd();
        $trace_arguments = array_key_exists("trace_arguments", $parameters) ? $parameters["trace_arguments"] : false;
        $display_errors = array_key_exists("display_errors", $parameters) ? $parameters["display_errors"] : false;

        foreach ([$log_path, $source_root] as $path) {
            if (!is_string($path) || $path === "" || str_contains($path, "\0")) {
                throw new \InvalidArgumentException("Log path and source root must be nonempty strings without null bytes.");
            }
        }

        if (!is_bool($trace_arguments) || !is_bool($display_errors)) {
            throw new \InvalidArgumentException("Argument tracing and diagnostic display flags must be boolean.");
        }

        $resolved_source_root = realpath($source_root);

        if ($resolved_source_root === false || !is_dir($resolved_source_root)) {
            throw new \RuntimeException(sprintf("The source root '%s' is not an existing directory!", $source_root));
        }

        if ($log_path !== "stdout") {
            $resolved_log_path = realpath($log_path);

            if ($resolved_log_path === false || !is_dir($resolved_log_path) || !is_writable($resolved_log_path)) {
                throw new \RuntimeException(sprintf("The log directory '%s' does not exist or is not writable!", $log_path));
            }

            $log_path = rtrim(str_replace("\\", "/", $resolved_log_path), "/") . "/";
            $file = $log_path . "trace.log";

            clearstatcache(true, $file);

            if (is_link($file) || (file_exists($file) && (!is_file($file) || !is_writable($file)))) {
                throw new \RuntimeException(sprintf("The trace file '%s' must be a writable regular file!", $file));
            }
        }

        $this->log_path = $log_path;
        $this->source_root = rtrim(str_replace("\\", "/", $resolved_source_root), "/") . "/";
        $this->trace_arguments = $trace_arguments;
        $this->display_errors = $display_errors;
        $this->last_trace_succeeded = null;
    } // init

    /**
     * Formats standard PHP backtrace frames without requiring error-handler arguments.
     * Also accepts the former synthetic first frame containing args[1..3] as the
     * message, file and line, for compatibility with previously prepared traces.
     *
     * @param list<array{file?: string, line?: int, function?: string, class?: string, type?: string, args?: array<array-key, mixed>}> $btrace
     * Frames returned by debug_backtrace() or Throwable::getTrace(), optionally
     * preceded by the legacy synthetic diagnostic frame without file or function.
     *
     * @return string
     * Returns a bounded formatted stack, a legacy diagnostic, or "backtrace empty".
     *
     * @author Oleg Schildt
     */
    public function format_backtrace(array $btrace): string
    {
        if ($btrace === []) {
            return "backtrace empty";
        }

        $first = $btrace[0];
        $args = $first["args"] ?? [];

        if (
            !isset($first["file"]) && !isset($first["function"]) &&
            is_string($args[1] ?? null) && is_string($args[2] ?? null) && is_int($args[3] ?? null)
        ) {
            array_shift($btrace);

            return $this->format_diagnostic($args[1], $args[2], $args[3], $btrace);
        }

        return $this->extract_call_stack($btrace);
    } // format_backtrace

    /**
     * Removes only the source root at the beginning of a normalized source path.
     * Paths outside that directory, including similarly named siblings, are preserved.
     *
     * @param string $path
     * Source path to shorten; the file does not have to exist.
     *
     * @return string
     * Returns a relative path within the source root, or the normalized original path.
     *
     * @author Oleg Schildt
     */
    protected function trim_path(string $path): string
    {
        $path = str_replace("\\", "/", $path);
        $root = $this->source_root;

        if ($root === "") {
            $cwd = getcwd();
            $root = $cwd === false ? "" : rtrim(str_replace("\\", "/", $cwd), "/") . "/";
        }

        $length = strlen($root);
        $matches = DIRECTORY_SEPARATOR === "\\"
            ? strncasecmp($path, $root, $length) === 0
            : strncmp($path, $root, $length) === 0;

        return $length > 0 && $matches ? substr($path, $length) : $path;
    } // trim_path

    /**
     * Extracts a bounded stack while retaining class names, call types and application closures.
     * Only this handler's frames, trigger_error() and the library's registration
     * callback are skipped; unrelated methods named handleError remain visible.
     *
     * @param list<array{file?: string, line?: int, function?: string, class?: string, type?: string, args?: array<array-key, mixed>}> $btrace
     * Standard backtrace frames; values are omitted unless trace_arguments is enabled.
     *
     * @return string
     * Returns the formatted stack, limited to 64 frames and 65536 bytes.
     *
     * @author Oleg Schildt
     */
    protected function extract_call_stack(array $btrace): string
    {
        $lines = [];
        $size = 0;
        $registration_file = str_replace("\\", "/", dirname(__DIR__) . "/error_handler_init_inc.php");

        foreach (array_slice($btrace, 0, self::MAX_FRAMES) as $entry) {
            $file = str_replace("\\", "/", $entry["file"] ?? "");
            $function = $entry["function"] ?? "";
            $class = $entry["class"] ?? "";

            if (
                $file === "" || $file === $registration_file ||
                ($class === "" && $function === "trigger_error") ||
                ($class !== "" && is_a($class, self::class, true))
            ) {
                continue;
            }

            if (preg_match("/{closure:([^}{]+)}/", $function, $matches)) {
                $function = "{closure:" . $this->trim_path($matches[1]) . "}";
            }

            $arguments = "";

            if (!empty($entry["args"])) {
                $arguments = $this->trace_arguments
                    ? $this->make_arg_list($this->name_arguments($entry))
                    : "[arguments omitted]";
            }

            $call = $class . ($entry["type"] ?? "") . $function;
            $line = str_repeat("  ", count($lines)) . ($call === "" ? "" : $call . "(" . $arguments . ")") .
                " [" . $this->trim_path($file) . ", " . ($entry["line"] ?? "line number undefined") . "]";
            $lines[] = $this->limit_message($line);
            $size += strlen($line);

            if ($size >= self::MAX_MESSAGE_BYTES) {
                break;
            }
        }

        if (count($btrace) > self::MAX_FRAMES || $size >= self::MAX_MESSAGE_BYTES) {
            $lines[] = "... [call stack truncated]";
        }

        return $this->limit_message(implode(PHP_EOL, $lines));
    } // extract_call_stack

    /**
     * Truncates an argument preview after normalizing control characters to spaces.
     * Work is limited to a short input prefix even for very large strings.
     *
     * @param string|null $str
     * UTF-8 or binary input; invalid UTF-8 bytes are replaced, and null becomes "null".
     *
     * @param int $limit
     * Maximum preview length in Unicode characters; nonpositive limits omit all characters.
     *
     * @return string
     * Returns the preview followed by "..." if any input was omitted.
     *
     * @author Oleg Schildt
     */
    protected function truncate_argument(?string $str, int $limit): string
    {
        if ($str === null) {
            return "null";
        }

        $limit = max(0, $limit);
        $prefix = mb_scrub(substr($str, 0, $limit * 4 + 4), "UTF-8");
        $prefix = trim(preg_replace('/[\x00-\x1f\x7f]+/', " ", $prefix));
        $truncated = strlen($str) > $limit * 4 + 4 || mb_strlen($prefix, "UTF-8") > $limit;

        return mb_substr($prefix, 0, $limit, "UTF-8") . ($truncated ? "..." : "");
    } // truncate_argument

    /**
     * Formats an array preview without modifying elements or following references indefinitely.
     *
     * @param array<array-key, mixed> &$arr
     * Array to preview; the reference is retained for compatibility and is never written to.
     *
     * @return string
     * Returns a preview limited to three items per array and four array levels.
     *
     * @author Oleg Schildt
     */
    protected function deep_implode(array &$arr): string
    {
        return $this->format_argument($arr);
    } // deep_implode

    /**
     * Formats a bounded argument list with explicit scalar types and sensitive-name masking.
     *
     * @param array<array-key, mixed> $args
     * Argument values, optionally keyed by reflected parameter names.
     *
     * @return string
     * Returns at most sixteen argument previews; additional values are replaced by an ellipsis.
     *
     * @author Oleg Schildt
     */
    protected function make_arg_list(array $args): string
    {
        $items = [];

        foreach ($args as $name => $arg) {
            if (count($items) >= self::MAX_ARGUMENTS) {
                $items[] = "...";

                break;
            }

            $label = is_string($name) ? $this->truncate_argument($name, 40) . "=" : "";
            $items[] = $label . ($this->is_sensitive_name($name) ? "[REDACTED]" : $this->format_argument($arg));
        }

        return $this->limit_message(implode(", ", $items));
    } // make_arg_list

    /**
     * Renders one diagnostic value without invoking user-defined object conversion methods.
     *
     * @param mixed $value
     * Any PHP value, including closed resources and arrays containing recursive references.
     *
     * @param int $depth
     * Current array nesting level, starting at zero.
     *
     * @return string
     * Returns a bounded preview with strings quoted, explicit booleans and null,
     * resource markers, class names and masked sensitive array values.
     *
     * @author Oleg Schildt
     */
    protected function format_argument(mixed $value, int $depth = 0): string
    {
        if ($value instanceof \SensitiveParameterValue) {
            return "[REDACTED]";
        }

        if ($value === null) {
            return "null";
        }

        if (is_bool($value)) {
            return $value ? "true" : "false";
        }

        if (is_string($value)) {
            return json_encode($this->truncate_argument($value, 15), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }

        if (is_int($value) || is_float($value)) {
            return var_export($value, true);
        }

        if (is_resource($value)) {
            return get_resource_type($value) . "#" . get_resource_id($value);
        }

        if (gettype($value) === "resource (closed)") {
            return "resource(closed)";
        }

        if (is_object($value)) {
            return get_class($value);
        }

        if ($depth >= self::MAX_ARRAY_DEPTH) {
            return "[...]";
        }

        $items = [];

        foreach ($value as $name => $item) {
            if (count($items) >= self::MAX_ARRAY_ITEMS) {
                $items[] = "...";

                break;
            }

            $label = $this->truncate_argument((string) $name, 40);
            $items[] = $label . "=" . ($this->is_sensitive_name($name) ? "[REDACTED]" : $this->format_argument($item, $depth + 1));
        }

        return "[" . implode(", ", $items) . "]";
    } // format_argument

    /**
     * Recognizes common credential names in parameter names and associative array keys.
     *
     * @param string|int $name
     * Parameter name, array key or positional index; numeric indices are not sensitive names.
     *
     * @return bool
     * Returns true for common password, secret, token, authorization, cookie, key and session names.
     *
     * @author Oleg Schildt
     */
    protected function is_sensitive_name(string|int $name): bool
    {
        return is_string($name) && preg_match(
            '/pass(?:word|phrase|wd)?|pwd|secret|token|authorization|cookie|api[_-]?key|private[_-]?key|credential|session[_-]?id/i',
            $name
        ) === 1;
    } // is_sensitive_name

    /**
     * Assigns available parameter names to positional arguments for credential masking.
     *
     * @param array{function?: string, class?: string, args?: array<array-key, mixed>} $entry
     * Standard backtrace frame; closures and unavailable internal functions may lack metadata.
     *
     * @return array<array-key, mixed>
     * Returns at most seventeen arguments for the bounded formatter. Sensitive variadic
     * parameters are masked for every supplied value, without reflecting argument objects.
     *
     * @author Oleg Schildt
     */
    protected function name_arguments(array $entry): array
    {
        $args = array_slice($entry["args"] ?? [], 0, self::MAX_ARGUMENTS + 1, true);
        $parameters = [];

        if (empty($entry["function"])) {
            return $args;
        }

        try {
            $reflection = isset($entry["class"])
                ? new \ReflectionMethod($entry["class"], $entry["function"])
                : new \ReflectionFunction($entry["function"]);
            $parameters = $reflection->getParameters();
        } catch (\ReflectionException) {
            // Generated closure names cannot be resolved through ReflectionFunction.
        }

        $named = [];
        $variadic = null;

        foreach ($args as $index => $arg) {
            $parameter = is_int($index) ? ($parameters[$index] ?? $variadic) : null;

            if ($parameter !== null && $parameter->isVariadic()) {
                $variadic = $parameter;
            }

            $name = $parameter === null ? $index : $parameter->getName();
            $sensitive = $this->is_sensitive_name($name) ||
                ($parameter !== null && $parameter->getAttributes(\SensitiveParameter::class) !== []);

            if ($sensitive) {
                $arg = new \SensitiveParameterValue($arg);
            }

            // Keep every variadic value instead of overwriting a repeated parameter name.
            $key = array_key_exists($name, $named) ? $index : $name;
            $named[$key] = $arg;
        }

        return $named;
    } // name_arguments

    /**
     * Formats an error header separately from its standard backtrace frames.
     * URI query values and fragments are never included in diagnostics.
     *
     * @param string $message
     * Error or exception description supplied by the application or PHP.
     *
     * @param string $file
     * Source file where the error or exception originated.
     *
     * @param int $line
     * Source line reported by PHP or the exception.
     *
     * @param list<array{file?: string, line?: int, function?: string, class?: string, type?: string, args?: array<array-key, mixed>}> $trace
     * Standard backtrace frames without a synthetic header.
     *
     * @return string
     * Returns a diagnostic limited to 65536 bytes, including a sanitized request URI.
     *
     * @author Oleg Schildt
     */
    protected function format_diagnostic(string $message, string $file, int $line, array $trace): string
    {
        $uri = (string) ($_SERVER["REQUEST_URI"] ?? "");
        $path_length = strcspn($uri, "?#");
        $uri = substr($uri, 0, $path_length) . (isset($uri[$path_length]) && $uri[$path_length] === "?" ? "?[REDACTED]" : "");
        $uri = preg_replace('/[\x00-\x1f\x7f]/', " ", $uri);
        $header = $this->limit_message($message) . PHP_EOL . PHP_EOL .
            "#URI: " . $this->limit_message($uri) . PHP_EOL .
            "#Source: " . $this->trim_path($file) . ", " . $line . PHP_EOL;
        $stack = $this->extract_call_stack($trace);

        return $this->limit_message($header . ($stack === "" ? "" : "#Callstack:" . PHP_EOL . $stack . PHP_EOL));
    } // format_diagnostic

    /**
     * Caps diagnostic byte length while retaining an explicit truncation marker.
     *
     * @param string $message
     * Diagnostic text, possibly containing binary data from an original error message.
     *
     * @return string
     * Returns at most 65536 bytes, ending with an omission marker when shortened.
     *
     * @author Oleg Schildt
     */
    protected function limit_message(string $message): string
    {
        $marker = PHP_EOL . "... [diagnostic truncated]";

        return strlen($message) > self::MAX_MESSAGE_BYTES
            ? substr($message, 0, self::MAX_MESSAGE_BYTES - strlen($marker)) . $marker
            : $message;
    } // limit_message

    /**
     * Appends a bounded diagnostic under an exclusive lock, falling back to PHP's server log.
     * Missing initialization and failed or incomplete file writes never imply stdout.
     *
     * @param string $etype
     * Human-readable PHP error category or an unknown numeric error code as a string.
     *
     * @param string $message
     * Formatted diagnostic; messages larger than 65536 bytes are truncated.
     *
     * @return bool
     * Returns true when tracing is disabled, stdout was explicitly used, the entire
     * file entry was written, or error_log() accepted the fallback. Returns false
     * if both the primary destination and fallback failed; records attempted write results.
     *
     * @author Oleg Schildt
     */
    protected function trace_message(string $etype, string $message): bool
    {
        if (!$this->traceActive()) {
            return true;
        }

        $message = date("Y-m-d H:i:s") . PHP_EOL .
            "------------------------------------" . PHP_EOL .
            "### " . $etype . " ###" . PHP_EOL . PHP_EOL .
            $this->limit_message($message) . PHP_EOL .
            "------------------------------------" . PHP_EOL . PHP_EOL . PHP_EOL;
        $file = $this->log_path === null ? null : $this->log_path . "trace.log";

        try {
            if ($this->log_path === "stdout" && (!is_web() || $this->display_errors)) {
                echo is_web() ? "<pre>" . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") . "</pre>" : $message;
                $this->last_trace_succeeded = true;

                return true;
            }

            if ($this->log_path !== null && $this->log_path !== "stdout") {
                clearstatcache(true, $file);

                if (!is_link($file) && (!file_exists($file) || is_file($file))) {
                    $written = @file_put_contents($file, $message, FILE_APPEND | LOCK_EX);

                    if ($written === strlen($message)) {
                        $this->last_trace_succeeded = true;

                        return true;
                    }
                }
            }
        } catch (\Throwable) {
            // A logging failure must not replace the original application error.
        }

        $reason = $this->log_path === null
            ? "The trace file is not specified."
            : ($this->log_path === "stdout" ? "Web stdout diagnostics are disabled." : "Could not write the complete trace entry to '" . $file . "'.");

        try {
            $this->last_trace_succeeded = @error_log($reason . PHP_EOL . $message);
        } catch (\Throwable) {
            $this->last_trace_succeeded = false;
        }

        return $this->last_trace_succeeded;
    } // trace_message

    /**
     * Handles reportable PHP errors, stores their text and notifies programming-warning listeners.
     * Masked errors, including ordinary warnings suppressed with @, are ignored.
     * Backtraces are collected only while tracing is enabled; recursive callbacks are ignored.
     *
     * @param int $errno
     * PHP error level, checked against the active error_reporting() mask.
     *
     * @param string $errstr
     * Original error message to store and log.
     *
     * @param string $errfile
     * Source file where the error occurred.
     *
     * @param int $errline
     * Source line where the error occurred.
     *
     * @return void
     * Logs the error when enabled and fires php_error, except for E_USER_ERROR.
     *
     * @throws \Throwable
     * If resolving the event manager or executing a php_error listener throws;
     * the recursion guard is restored before the failure is propagated.
     *
     * @author Oleg Schildt
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): void
    {
        if ((error_reporting() & $errno) === 0 || self::$handling_error) {
            return;
        }

        self::$handling_error = true;

        try {
            $this->setLastError($errstr);

            $etype = self::ERROR_TYPES[$errno] ?? (string) $errno;

            if ($this->traceActive()) {
                $options = $this->trace_arguments ? 0 : DEBUG_BACKTRACE_IGNORE_ARGS;
                $trace = debug_backtrace($options, self::MAX_FRAMES + 1);

                $this->trace_message($etype, $this->format_diagnostic($errstr, $errfile, $errline, $trace));
            }

            // E_USER_ERROR is used for explicit application error tracing, so it needs no extra warning.
            if ($errno !== E_USER_ERROR) {
                event()->fireEvent("php_error", ["etype" => $etype, "errstr" => $errstr, "errfile" => $errfile, "errline" => $errline]);
            }
        } finally {
            self::$handling_error = false;
        }
    } // handleError

    /**
     * Logs a caught throwable with its class, code, source and bounded previous-exception chain.
     * Explicit exception logging is independent of the PHP error_reporting() mask.
     *
     * @param \Throwable $ex
     * Exception or error to record; previous exceptions are followed for at most eight entries.
     *
     * @param int $errno
     * PHP category used to label the log entry, including E_USER_DEPRECATED or unknown codes.
     *
     * @return void
     * Stores the outer message and logs the bounded chain only when tracing is enabled.
     *
     * @author Oleg Schildt
     */
    public function handleException(\Throwable $ex, int $errno): void
    {
        $this->setLastError($ex->getMessage());

        if (!$this->traceActive()) {
            return;
        }

        $parts = [];
        $size = 0;
        $current = $ex;

        for ($index = 0; $current !== null && $index < self::MAX_EXCEPTIONS; $index++) {
            $description = ($index === 0 ? "" : "Caused by: ") . get_class($current) .
                " (code " . $current->getCode() . "): " . $this->limit_message($current->getMessage());
            $part = $this->format_diagnostic($description, $current->getFile(), $current->getLine(), $current->getTrace());
            $parts[] = $part;
            $size += strlen($part);
            $current = $current->getPrevious();

            if ($size >= self::MAX_MESSAGE_BYTES) {
                break;
            }
        }

        if ($current !== null) {
            $parts[] = "... [exception chain truncated]";
        }

        $this->trace_message(self::ERROR_TYPES[$errno] ?? (string) $errno, $this->limit_message(implode(PHP_EOL, $parts)));
    } // handleException

    /**
     * Returns the stored error, falling back to PHP's last native error when storage is empty.
     *
     * @return string
     * Returns the error message, including "0", or an empty string if no error is available.
     *
     * @author Oleg Schildt
     */
    public function getLastError(): string
    {
        return self::$last_error !== "" ? self::$last_error : (error_get_last()["message"] ?? "");
    } // getLastError

    /**
     * Stores the last error across handler instances in this PHP request.
     *
     * @param string $error
     * Error text; an empty string restores fallback to PHP's native last error.
     *
     * @return void
     * Updates the shared stored error without clearing PHP's native error state.
     *
     * @author Oleg Schildt
     */
    public function setLastError(string $error): void
    {
        self::$last_error = $error;
    } // setLastError

    /**
     * Exposes the result of the last attempted diagnostic write on this instance.
     *
     * @return bool|null
     * Returns true after a complete file write, explicit stdout or accepted server-log
     * fallback; false if both destinations failed; null before any write or after init().
     * Suppressed errors and disabled tracing preserve the previous result.
     *
     * @author Oleg Schildt
     */
    public function getLastTraceSucceeded(): ?bool
    {
        return $this->last_trace_succeeded;
    } // getLastTraceSucceeded

    /**
     * Reports the shared tracing switch, independent of the PHP error-reporting mask.
     * Applications can initialize it from trace_programming_warnings and temporarily
     * disable stack collection and logging without disabling programming-warning events.
     *
     * @return bool
     * Returns true when trace collection and logging are enabled.
     *
     * @see ErrorHandler::enableTrace()
     * @see ErrorHandler::disableTrace()
     *
     * @author Oleg Schildt
     */
    public function traceActive(): bool
    {
        return !self::$trace_disabled;
    } // traceActive

    /**
     * Enables stack collection and diagnostic logging for all handler instances.
     *
     * @return void
     * Enables tracing without changing the PHP error-reporting mask.
     *
     * @see ErrorHandler::traceActive()
     * @see ErrorHandler::disableTrace()
     *
     * @author Oleg Schildt
     */
    public function enableTrace(): void
    {
        self::$trace_disabled = false;
    } // enableTrace

    /**
     * Disables stack collection and diagnostic logging for all handler instances.
     * Reportable programming-warning events and last-error storage remain active.
     *
     * @return void
     * Disables tracing until enableTrace() is called.
     *
     * @see ErrorHandler::traceActive()
     * @see ErrorHandler::enableTrace()
     *
     * @author Oleg Schildt
     */
    public function disableTrace(): void
    {
        self::$trace_disabled = true;
    } // disableTrace
} // ErrorHandler
