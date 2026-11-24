<?php
/**
 * Collects structured errors in SmartExceptionCollection so they can be thrown and reported together.
 *
 * Each error retains its message, localization details, related element, string code and source location.
 * Applications control whether technical information and source locations are exposed to clients.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

/**
 * Collects errors in insertion order and reports them together as an exception.
 *
 * Repeated errors are retained. Error details are stored without conversion or serialization.
 *
 * @author Oleg Schildt
 */
class SmartExceptionCollection extends \Exception
{
    /**
     * Errors retained in insertion order without deduplication.
     *
     * @var list<array{message: string, details: array<array-key, mixed>, related_element: string,
     *     code: string, technical_info: string, file: string, line: int}>
     *
     * @author Oleg Schildt
     */
    protected array $errors = [];

    /**
     * Creates a collection with a summary for general-purpose exception handlers.
     *
     * @param string $message
     * The summary message; individual error messages remain available through getErrors().
     *
     * @param int $code
     * The native exception code, independent of the string codes of individual errors.
     *
     * @param \Throwable|null $previous
     * The original exception or error, or null when there is no underlying cause.
     *
     * @author Oleg Schildt
     */
    public function __construct(
        string $message = "One or more errors occurred.",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    } // __construct

    /**
     * Appends an error without deduplication, preserving all details and their types.
     *
     * @param string $message
     * The error message or localization identifier. Only an empty string is ignored; "0" is retained.
     *
     * @param array<array-key, mixed> $details
     * Structured details for client-side message translation and parameter substitution.
     * Keys, value types and nested data are preserved without conversion.
     *
     * @param string $related_element
     * The error element associated with the error.
     *
     * @param string $code
     * The error code to be reported.
     *
     * @param string $technical_info
     * Technical diagnostics retained by the collection. Applications control whether to expose them
     * to clients; MessageManager applies its debug settings when importing the collection.
     *
     * @param string $file
     * The source file where the error occurred. An empty string uses the caller's file, if available.
     *
     * @param int|string|null $line
     * The source line as an integer or a numeric string, retained for compatibility with existing callers.
     * Null, an empty string or a value converting to a nonpositive integer uses the caller's line.
     *
     * @return void
     * Retains the error unless its message is an empty string.
     *
     * @author Oleg Schildt
     */
    public function addError(
        string $message,
        array $details = [],
        string $related_element = "",
        string $code = "",
        string $technical_info = "",
        string $file = "",
        int|string|null $line = ""
    ): void {
        if ($message === "") {
            return;
        }

        $line = (int) $line;

        if ($file === "" || $line <= 0) {
            // Only the immediate caller's location is needed; arguments may contain sensitive data.
            $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? [];

            if ($file === "") {
                $file = $caller['file'] ?? "";
            }

            if ($line <= 0) {
                $line = $caller['line'] ?? 0;
            }
        }

        $this->errors[] = [
            "message" => $message,
            "details" => $details,
            "related_element" => $related_element,
            "code" => $code,
            "technical_info" => $technical_info,
            "file" => $file,
            "line" => $line
        ];
    } // addError

    /**
     * Throws itself if any error has been collected.
     *
     * @return void
     * Returns normally when the collection is empty.
     *
     * @throws SmartExceptionCollection
     * Throws itself if there are errors.
     *
     * @author Oleg Schildt
     */
    public function throwIfErrors(): void
    {
        if (count($this->errors) > 0) {
            throw $this;
        }
    } // throwIfErrors

    /**
     * Returns all retained errors without clearing the collection.
     *
     * @return list<array{message: string, details: array<array-key, mixed>, related_element: string,
     *     code: string, technical_info: string, file: string, line: int}>
     * Returns errors in insertion order, or an empty list. A missing source line is represented by zero.
     *
     * @author Oleg Schildt
     */
    public function getErrors(): array
    {
        return $this->errors;
    } // getErrors
} // SmartExceptionCollection
