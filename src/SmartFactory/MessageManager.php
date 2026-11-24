<?php
/**
 * This file contains the implementation of the interface IMessageManager
 * in the class MessageManager for working with messages - errors, warnings etc.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\Interfaces\IMessageManager;

/**
 * Class for working with messages - errors, warnings etc.
 *
 * @author Oleg Schildt
 */
class MessageManager implements IMessageManager
{
    /**
     * Stores message lists by category, preserving insertion order and repeated messages.
     *
     * @var array<string, list<array<string, mixed>>>
     *
     * @author Oleg Schildt
     */
    protected array $messages = [];

    /**
     * Internal variable for storing the state whether technical details, programmer warnings and debug information are also sent to the client.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $debug_mode = false;

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
     * Applies debug settings to new and stored messages without clearing public messages.
     *
     * Disabling debug mode removes stored programming warnings and debugging messages and
     * clears technical information and source locations from errors. Disabling source output
     * clears stored source locations. Enabling either option cannot restore removed data.
     *
     * @param array{debug_mode?: mixed, write_source_file_and_line_by_debug?: mixed} $parameters
     * Options are interpreted using PHP nonempty-value semantics; omitted options are disabled.
     * Debug mode enables technical diagnostics. Source output additionally enables file names
     * and line numbers for errors, programming warnings and debugging messages.
     *
     * @return void
     * Updates the settings and removes stored diagnostics that the new settings disallow.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void
    {
        $this->debug_mode = !empty($parameters["debug_mode"]);
        $this->write_source_file_and_line_by_debug = !empty($parameters["write_source_file_and_line_by_debug"]);

        if (!$this->debug_mode) {
            $this->clearProgWarnings();

            $this->clearDebugMessages();

            foreach (array_keys($this->messages["errors"] ?? []) as $index) {
                $this->messages["errors"][$index]["technical_info"] = "";
            }
        }

        if (!$this->debug_mode || !$this->write_source_file_and_line_by_debug) {
            foreach (["errors", "prog_warnings", "debug_messages"] as $category) {
                foreach (array_keys($this->messages[$category] ?? []) as $index) {
                    $this->messages[$category][$index]["file"] = "";
                    $this->messages[$category][$index]["line"] = "";
                }
            }
        }
    } // init

    /**
     * Appends an error without deduplication, preserving structured details and their types.
     *
     * @param string $message
     * The error message or localization identifier. Only an empty string is ignored; "0" is retained.
     *
     * @param array<array-key, mixed> $details
     * The details might be useful if the message translations are provided on the client, not
     * on the server, and the message should contain some details that may vary from case to case.
     * The server can return a message identifier and details so the client can translate the message
     * and substitute its parameters. Keys, value types and nested data are preserved without conversion.
     *
     * @param string $related_element
     * The error element associated with the error.
     *
     * @param string $code
     * The error code to be reported.
     *
     * @param string $technical_info
     * Technical diagnostics, retained only when debug mode is enabled.
     *
     * @param string $file
     * The source file where the error occurred. An empty string uses the caller's file, if available.
     *
     * @param int|string|null $line
     * The source line as an integer or a numeric string, retained for compatibility with existing callers.
     * Null, an empty string or a value converting to a nonpositive integer uses the caller's line.
     *
     * @return void
     * Retains the error unless its message is empty. Technical information and source locations
     * are filtered according to the debug settings.
     *
     * @see MessageManager::addErrorFromSmartException()
     * @see MessageManager::addWarning()
     * @see MessageManager::addProgWarning()
     * @see MessageManager::addDebugMessage()
     * @see MessageManager::addInfoMessage()
     * @see MessageManager::addBubbleMessage()
     * @see MessageManager::getErrors()
     * @see MessageManager::hasErrors()
     * @see MessageManager::clearErrors()
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

        if ($this->debug_mode && $this->write_source_file_and_line_by_debug) {
            $line = (int) $line;

            if ($file === "" || $line <= 0) {
                // Only the caller's location is needed; arguments and further stack frames are omitted.
                $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? [];

                if ($file === "") {
                    $file = $caller['file'] ?? "";
                }

                if ($line <= 0) {
                    $line = $caller['line'] ?? 0;
                }
            }
        } else {
            $file = "";
            $line = "";
        }

        if (!$this->debug_mode) {
            $technical_info = "";
        }

        $this->messages["errors"][] = [
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
     * Appends a structured exception or every error in a collection using the current debug settings.
     *
     * @param SmartException|SmartExceptionCollection $smart_exception
     * The exception or collection to report. Collection entries retain their insertion order.
     *
     * @return void
     * Adds the exception data without serializing details or deduplicating errors.
     *
     * @see MessageManager::addError()
     * @see MessageManager::addWarning()
     * @see MessageManager::addProgWarning()
     * @see MessageManager::addDebugMessage()
     * @see MessageManager::addInfoMessage()
     * @see MessageManager::addBubbleMessage()
     * @see MessageManager::getErrors()
     * @see MessageManager::hasErrors()
     * @see MessageManager::clearErrors()
     *
     * @author Oleg Schildt
     */
    public function addErrorFromSmartException(SmartException|SmartExceptionCollection $smart_exception): void
    {
        if ($smart_exception instanceof SmartException) {
            $this->addError($smart_exception->getMessage(), $smart_exception->getErrorDetails(), $smart_exception->getErrorElement(), $smart_exception->getErrorCode(), trim($smart_exception->getTechnicalInfo()), $smart_exception->getFile(), $smart_exception->getLine());
        } elseif ($smart_exception instanceof SmartExceptionCollection) {
            $errors = $smart_exception->getErrors();

            foreach ($errors as $error) {
                $this->addError($error["message"], $error["details"], $error["related_element"], $error["code"], $error["technical_info"], $error["file"], $error["line"]);
            }
        }
    } // addErrorFromSmartException

    /**
     * Clears the stored error messages.
     *
     * @return void
     *
     * @see MessageManager::clearWarnings()
     * @see MessageManager::clearProgWarnings()
     * @see MessageManager::clearDebugMessages()
     * @see MessageManager::clearInfoMessages()
     * @see MessageManager::clearBubbleMessages()
     * @see MessageManager::clearAll()
     * @see MessageManager::addError()
     *
     * @author Oleg Schildt
     */
    public function clearErrors(): void
    {
        unset($this->messages["errors"]);
    } // clearErrors

    /**
     * Checks whether any errors are stored.
     *
     * @return bool
     * Returns true if the stored error message exists, otherwise false.
     *
     * @see MessageManager::hasWarnings()
     * @see MessageManager::hasProgWarnings()
     * @see MessageManager::hasDebugMessages()
     * @see MessageManager::hasInfoMessages()
     * @see MessageManager::hasBubbleMessages()
     * @see MessageManager::getErrors()
     * @see MessageManager::addError()
     *
     * @author Oleg Schildt
     */
    public function hasErrors(): bool
    {
        return (isset($this->messages["errors"]) && count($this->messages["errors"]) > 0);
    } // hasErrors

    /**
     * Returns stored errors in insertion order and clears that category.
     *
     * @return list<array{message: string, details: array<array-key, mixed>, related_element: string,
     *     code: string, technical_info: string, file: string, line: int|string}>
     * Returns all stored errors, or an empty list. Other categories are unchanged.
     * Source lines are integers when visible, zero when unavailable and empty strings when hidden.
     *
     * @see MessageManager::getWarnings()
     * @see MessageManager::getProgWarnings()
     * @see MessageManager::getDebugMessages()
     * @see MessageManager::getInfoMessages()
     * @see MessageManager::getBubbleMessages()
     * @see MessageManager::hasErrors()
     * @see MessageManager::addError()
     *
     * @author Oleg Schildt
     */
    public function getErrors(): array
    {
        if (!$this->hasErrors()) {
            return [];
        }

        $messages = $this->getMessages($this->messages["errors"]);

        $this->clearErrors();

        return $messages;
    } // getErrors

    /**
     * Appends a warning without deduplication, preserving structured details and their types.
     *
     * @param string $message
     * The message or localization identifier. Only an empty string is ignored; "0" is retained.
     *
     * @param array<array-key, mixed> $details
     * Structured localization details. Keys, value types, nested arrays and objects are preserved.
     *
     * @param string $related_element
     * The error element associated with the warning.
     *
     * @return void
     * Retains the message unless its text is empty. Repeated messages are retained.
     *
     * @see MessageManager::addError()
     * @see MessageManager::addProgWarning()
     * @see MessageManager::addDebugMessage()
     * @see MessageManager::addInfoMessage()
     * @see MessageManager::addBubbleMessage()
     * @see MessageManager::getWarnings()
     * @see MessageManager::hasWarnings()
     * @see MessageManager::clearWarnings()
     *
     * @author Oleg Schildt
     */
    public function addWarning(string $message, array $details = [], string $related_element = ""): void
    {
        if ($message === "") {
            return;
        }

        $this->messages["warnings"][] = [
            "message" => $message,
            "details" => $details,
            "related_element" => $related_element
        ];
    } // addWarning

    /**
     * Clears the stored warning messages.
     *
     * @return void
     *
     * @see MessageManager::clearErrors()
     * @see MessageManager::clearProgWarnings()
     * @see MessageManager::clearDebugMessages()
     * @see MessageManager::clearInfoMessages()
     * @see MessageManager::clearBubbleMessages()
     * @see MessageManager::clearAll()
     * @see MessageManager::addWarning()
     *
     * @author Oleg Schildt
     */
    public function clearWarnings(): void
    {
        unset($this->messages["warnings"]);
    } // clearWarnings

    /**
     * Checks whether any warnings are stored.
     *
     * @return bool
     * Returns true if the stored warning message exists, otherwise false.
     *
     * @see MessageManager::hasErrors()
     * @see MessageManager::hasProgWarnings()
     * @see MessageManager::hasDebugMessages()
     * @see MessageManager::hasInfoMessages()
     * @see MessageManager::hasBubbleMessages()
     * @see MessageManager::getWarnings()
     * @see MessageManager::addWarning()
     *
     * @author Oleg Schildt
     */
    public function hasWarnings(): bool
    {
        return (isset($this->messages["warnings"]) && count($this->messages["warnings"]) > 0);
    } // hasWarnings

    /**
     * Returns stored warnings in insertion order and clears that category.
     *
     * @return list<array{message: string, details: array<array-key, mixed>, related_element: string}>
     * Returns all stored warnings, or an empty list. Other categories are unchanged.
     *
     * @see MessageManager::getErrors()
     * @see MessageManager::getProgWarnings()
     * @see MessageManager::getDebugMessages()
     * @see MessageManager::getInfoMessages()
     * @see MessageManager::getBubbleMessages()
     * @see MessageManager::hasWarnings()
     * @see MessageManager::addWarning()
     *
     * @author Oleg Schildt
     */
    public function getWarnings(): array
    {
        if (!$this->hasWarnings()) {
            return [];
        }

        $messages = $this->getMessages($this->messages["warnings"]);

        $this->clearWarnings();

        return $messages;
    } // getWarnings

    /**
     * Appends a programming warning without deduplication when debug mode is enabled.
     *
     * Source locations are retained only when source output is enabled.
     *
     * @param string $message
     * The message to report. Only an empty string is ignored; "0" is retained.
     *
     * @param string $file
     * The source file. An empty string uses the caller's file when source output is enabled.
     *
     * @param int|string|null $line
     * The source line as an integer or a numeric string. Null, an empty string or a value
     * converting to a nonpositive integer uses the caller's line when source output is enabled.
     *
     * @return void
     * Retains the message unless debug mode is disabled or the message is empty.
     *
     * @see MessageManager::addError()
     * @see MessageManager::addWarning()
     * @see MessageManager::addDebugMessage()
     * @see MessageManager::addInfoMessage()
     * @see MessageManager::addBubbleMessage()
     * @see MessageManager::getProgWarnings()
     * @see MessageManager::hasProgWarnings()
     * @see MessageManager::clearProgWarnings()
     *
     * @author Oleg Schildt
     */
    public function addProgWarning(string $message, string $file = "", int|string|null $line = ""): void
    {
        if (!$this->debug_mode) {
            return;
        }

        if ($message === "") {
            return;
        }

        if ($this->write_source_file_and_line_by_debug) {
            $line = (int) $line;

            if ($file === "" || $line <= 0) {
                // Only the caller's location is needed; arguments and further stack frames are omitted.
                $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? [];

                if ($file === "") {
                    $file = $caller['file'] ?? "";
                }

                if ($line <= 0) {
                    $line = $caller['line'] ?? 0;
                }
            }
        } else {
            $file = "";
            $line = "";
        }

        $this->messages["prog_warnings"][] = [
            "message" => $message,
            "file" => $file,
            "line" => $line
        ];
    } // addProgWarning

    /**
     * Clears the stored programming warning messages.
     *
     * @return void
     *
     * @see MessageManager::clearErrors()
     * @see MessageManager::clearWarnings()
     * @see MessageManager::clearDebugMessages()
     * @see MessageManager::clearInfoMessages()
     * @see MessageManager::clearBubbleMessages()
     * @see MessageManager::clearAll()
     * @see MessageManager::addProgWarning()
     *
     * @author Oleg Schildt
     */
    public function clearProgWarnings(): void
    {
        unset($this->messages["prog_warnings"]);
    } // clearProgWarnings

    /**
     * Checks whether any programming warnings are stored.
     *
     * @return bool
     * Returns true if the stored programming warning message exists, otherwise false.
     *
     * @see MessageManager::hasErrors()
     * @see MessageManager::hasWarnings()
     * @see MessageManager::hasDebugMessages()
     * @see MessageManager::hasInfoMessages()
     * @see MessageManager::hasBubbleMessages()
     * @see MessageManager::getProgWarnings()
     * @see MessageManager::addProgWarning()
     *
     * @author Oleg Schildt
     */
    public function hasProgWarnings(): bool
    {
        return (isset($this->messages["prog_warnings"]) && count($this->messages["prog_warnings"]) > 0);
    } // hasProgWarnings

    /**
     * Returns stored programming warnings in insertion order and clears that category.
     *
     * @return list<array{message: string, file: string, line: int|string}>
     * Returns all stored programming warnings, or an empty list. Other categories are unchanged.
     * Source lines are integers when visible, zero when unavailable and empty strings when hidden.
     *
     * @see MessageManager::getErrors()
     * @see MessageManager::getWarnings()
     * @see MessageManager::getDebugMessages()
     * @see MessageManager::getInfoMessages()
     * @see MessageManager::getBubbleMessages()
     * @see MessageManager::hasProgWarnings()
     * @see MessageManager::addProgWarning()
     *
     * @author Oleg Schildt
     */
    public function getProgWarnings(): array
    {
        if (!$this->hasProgWarnings()) {
            return [];
        }

        $messages = $this->getMessages($this->messages["prog_warnings"]);

        $this->clearProgWarnings();

        return $messages;
    } // getProgWarnings

    /**
     * Appends a debugging message without deduplication when debug mode is enabled.
     *
     * Source locations are retained only when source output is enabled.
     *
     * @param string $message
     * The message to report. Only an empty string is ignored; "0" is retained.
     *
     * @param string $file
     * The source file. An empty string uses the caller's file when source output is enabled.
     *
     * @param int|string|null $line
     * The source line as an integer or a numeric string. Null, an empty string or a value
     * converting to a nonpositive integer uses the caller's line when source output is enabled.
     *
     * @return void
     * Retains the message unless debug mode is disabled or the message is empty.
     *
     * @see MessageManager::addError()
     * @see MessageManager::addWarning()
     * @see MessageManager::addProgWarning()
     * @see MessageManager::addInfoMessage()
     * @see MessageManager::addBubbleMessage()
     * @see MessageManager::getDebugMessages()
     * @see MessageManager::hasDebugMessages()
     * @see MessageManager::clearDebugMessages()
     *
     * @author Oleg Schildt
     */
    public function addDebugMessage(string $message, string $file = "", int|string|null $line = ""): void
    {
        if (!$this->debug_mode) {
            return;
        }

        if ($message === "") {
            return;
        }

        if ($this->write_source_file_and_line_by_debug) {
            $line = (int) $line;

            if ($file === "" || $line <= 0) {
                // Only the caller's location is needed; arguments and further stack frames are omitted.
                $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? [];

                if ($file === "") {
                    $file = $caller['file'] ?? "";
                }

                if ($line <= 0) {
                    $line = $caller['line'] ?? 0;
                }
            }
        } else {
            $file = "";
            $line = "";
        }

        $this->messages["debug_messages"][] = [
            "message" => $message,
            "file" => $file,
            "line" => $line
        ];
    } // addDebugMessage

    /**
     * Clears the stored debugging messages.
     *
     * @return void
     *
     * @see MessageManager::clearErrors()
     * @see MessageManager::clearWarnings()
     * @see MessageManager::clearProgWarnings()
     * @see MessageManager::clearInfoMessages()
     * @see MessageManager::clearBubbleMessages()
     * @see MessageManager::clearAll()
     * @see MessageManager::addDebugMessage()
     *
     * @author Oleg Schildt
     */
    public function clearDebugMessages(): void
    {
        unset($this->messages["debug_messages"]);
    } // clearDebugMessages

    /**
     * Checks whether any debugging messages are stored.
     *
     * @return bool
     * Returns true if the stored debugging message exists, otherwise false.
     *
     * @see MessageManager::hasErrors()
     * @see MessageManager::hasWarnings()
     * @see MessageManager::hasProgWarnings()
     * @see MessageManager::hasInfoMessages()
     * @see MessageManager::hasBubbleMessages()
     * @see MessageManager::getDebugMessages()
     * @see MessageManager::addDebugMessage()
     *
     * @author Oleg Schildt
     */
    public function hasDebugMessages(): bool
    {
        return (isset($this->messages["debug_messages"]) && count($this->messages["debug_messages"]) > 0);
    } // hasDebugMessages

    /**
     * Returns stored debugging messages in insertion order and clears that category.
     *
     * @return list<array{message: string, file: string, line: int|string}>
     * Returns all stored debugging messages, or an empty list. Other categories are unchanged.
     * Source lines are integers when visible, zero when unavailable and empty strings when hidden.
     *
     * @see MessageManager::getErrors()
     * @see MessageManager::getWarnings()
     * @see MessageManager::getProgWarnings()
     * @see MessageManager::getInfoMessages()
     * @see MessageManager::getBubbleMessages()
     * @see MessageManager::hasDebugMessages()
     * @see MessageManager::addDebugMessage()
     *
     * @author Oleg Schildt
     */
    public function getDebugMessages(): array
    {
        if (!$this->hasDebugMessages()) {
            return [];
        }

        $messages = $this->getMessages($this->messages["debug_messages"]);

        $this->clearDebugMessages();

        return $messages;
    } // getDebugMessages

    /**
     * Appends a information message without deduplication, preserving structured details and their types.
     *
     * @param string $message
     * The message or localization identifier. Only an empty string is ignored; "0" is retained.
     *
     * @param array<array-key, mixed> $details
     * Structured localization details. Keys, value types, nested arrays and objects are preserved.
     *
     * @param bool $autoclose
     * The flag that controls whether the message box should be closed automatically after a time.
     *
     * @return void
     * Retains the message unless its text is empty. Repeated messages are retained.
     *
     * @see MessageManager::addError()
     * @see MessageManager::addWarning()
     * @see MessageManager::addProgWarning()
     * @see MessageManager::addDebugMessage()
     * @see MessageManager::addBubbleMessage()
     * @see MessageManager::getInfoMessages()
     * @see MessageManager::hasInfoMessages()
     * @see MessageManager::clearInfoMessages()
     *
     * @author Oleg Schildt
     */
    public function addInfoMessage(string $message, array $details = [], bool $autoclose = false): void
    {
        if ($message === "") {
            return;
        }

        $this->messages["info_messages"][] = [
            "message" => $message,
            "details" => $details,
            "autoclose" => $autoclose
        ];
    } // addInfoMessage

    /**
     * Clears the stored information messages.
     *
     * @return void
     *
     * @see MessageManager::clearErrors()
     * @see MessageManager::clearWarnings()
     * @see MessageManager::clearProgWarnings()
     * @see MessageManager::clearDebugMessages()
     * @see MessageManager::clearBubbleMessages()
     * @see MessageManager::clearAll()
     * @see MessageManager::addInfoMessage()
     *
     * @author Oleg Schildt
     */
    public function clearInfoMessages(): void
    {
        unset($this->messages["info_messages"]);
    } // clearInfoMessages

    /**
     * Checks whether any information messages are stored.
     *
     * @return bool
     * Returns true if the information message exists, otherwise false.
     *
     * @see MessageManager::hasErrors()
     * @see MessageManager::hasWarnings()
     * @see MessageManager::hasProgWarnings()
     * @see MessageManager::hasDebugMessages()
     * @see MessageManager::hasBubbleMessages()
     * @see MessageManager::getInfoMessages()
     * @see MessageManager::addInfoMessage()
     *
     * @author Oleg Schildt
     */
    public function hasInfoMessages(): bool
    {
        return (isset($this->messages["info_messages"]) && count($this->messages["info_messages"]) > 0);
    } // hasInfoMessages

    /**
     * Returns stored information messages in insertion order and clears that category.
     *
     * @return list<array{message: string, details: array<array-key, mixed>, autoclose: bool}>
     * Returns all stored information messages, or an empty list. Other categories are unchanged.
     *
     * @see MessageManager::getErrors()
     * @see MessageManager::getWarnings()
     * @see MessageManager::getProgWarnings()
     * @see MessageManager::getDebugMessages()
     * @see MessageManager::getBubbleMessages()
     * @see MessageManager::hasInfoMessages()
     * @see MessageManager::addInfoMessage()
     *
     * @author Oleg Schildt
     */
    public function getInfoMessages(): array
    {
        if (!$this->hasInfoMessages()) {
            return [];
        }

        $messages = $this->getMessages($this->messages["info_messages"]);

        $this->clearInfoMessages();

        return $messages;
    } // getInfoMessages

    /**
     * Appends a bubble message without deduplication, preserving structured details and their types.
     *
     * @param string $message
     * The message or localization identifier. Only an empty string is ignored; "0" is retained.
     *
     * @param array<array-key, mixed> $details
     * Structured localization details. Keys, value types, nested arrays and objects are preserved.
     *
     * @param bool $autoclose
     * The flag that controls whether the message box should be closed
     * automatically after a time.
     *
     * @return void
     * Retains the message unless its text is empty. Repeated messages are retained.
     *
     * @see MessageManager::addError()
     * @see MessageManager::addWarning()
     * @see MessageManager::addProgWarning()
     * @see MessageManager::addDebugMessage()
     * @see MessageManager::addInfoMessage()
     * @see MessageManager::getBubbleMessages()
     * @see MessageManager::hasBubbleMessages()
     * @see MessageManager::clearBubbleMessages()
     *
     * @author Oleg Schildt
     */
    public function addBubbleMessage(string $message, array $details = [], bool $autoclose = true): void
    {
        if ($message === "") {
            return;
        }

        $this->messages["bubble_messages"][] = [
            "message" => $message,
            "details" => $details,
            "autoclose" => $autoclose
        ];
    } // addBubbleMessage

    /**
     * Clears the stored bubble messages.
     *
     * @return void
     * Removes all pending bubble messages.
     *
     * @see MessageManager::clearErrors()
     * @see MessageManager::clearWarnings()
     * @see MessageManager::clearProgWarnings()
     * @see MessageManager::clearDebugMessages()
     * @see MessageManager::clearInfoMessages()
     * @see MessageManager::clearAll()
     * @see MessageManager::addBubbleMessage()
     *
     * @author Oleg Schildt
     */
    public function clearBubbleMessages(): void
    {
        unset($this->messages["bubble_messages"]);
    } // clearBubbleMessages

    /**
     * Checks whether any bubble messages are stored.
     *
     * @return bool
     * Returns true if the bubble message exists, otherwise false.
     *
     * @see MessageManager::hasErrors()
     * @see MessageManager::hasWarnings()
     * @see MessageManager::hasProgWarnings()
     * @see MessageManager::hasDebugMessages()
     * @see MessageManager::hasInfoMessages()
     * @see MessageManager::getBubbleMessages()
     * @see MessageManager::addBubbleMessage()
     *
     * @author Oleg Schildt
     */
    public function hasBubbleMessages(): bool
    {
        return (isset($this->messages["bubble_messages"]) && count($this->messages["bubble_messages"]) > 0);
    } // hasBubbleMessages

    /**
     * Returns stored bubble messages in insertion order and clears that category.
     *
     * @return list<array{message: string, details: array<array-key, mixed>, autoclose: bool}>
     * Returns all stored bubble messages, or an empty list. Other categories are unchanged.
     *
     * @see MessageManager::getErrors()
     * @see MessageManager::getWarnings()
     * @see MessageManager::getProgWarnings()
     * @see MessageManager::getDebugMessages()
     * @see MessageManager::getInfoMessages()
     * @see MessageManager::hasBubbleMessages()
     * @see MessageManager::addBubbleMessage()
     *
     * @author Oleg Schildt
     */
    public function getBubbleMessages(): array
    {
        if (!$this->hasBubbleMessages()) {
            return [];
        }

        $messages = $this->getMessages($this->messages["bubble_messages"]);

        $this->clearBubbleMessages();

        return $messages;
    } // getBubbleMessages

    /**
     * Clears all stored messages.
     *
     * @return void
     * Removes all pending messages in every category.
     *
     * @see MessageManager::clearErrors()
     * @see MessageManager::clearWarnings()
     * @see MessageManager::clearProgWarnings()
     * @see MessageManager::clearDebugMessages()
     * @see MessageManager::clearInfoMessages()
     * @see MessageManager::clearBubbleMessages()
     *
     * @author Oleg Schildt
     */
    public function clearAll(): void
    {
        $this->clearErrors();

        $this->clearWarnings();

        $this->clearProgWarnings();

        $this->clearDebugMessages();

        $this->clearInfoMessages();

        $this->clearBubbleMessages();
    } // clearAll

    /**
     * Normalizes a message snapshot to a list without changing stored messages.
     *
     * @param array<array-key, array<string, mixed>> $messages
     * The message snapshot to normalize.
     *
     * @return list<array<string, mixed>>
     * Returns the messages in their existing iteration order with consecutive integer keys.
     *
     * @author Oleg Schildt
     */
    protected function getMessages(array $messages): array
    {
        return array_values($messages);
    } // getMessages
} // MessageManager

