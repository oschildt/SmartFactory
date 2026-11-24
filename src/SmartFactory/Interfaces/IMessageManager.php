<?php
/**
 * This file contains the declaration of the interface IMessageManager for working with messages.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\Interfaces;

/**
 * Interface for working with messages.
 *
 * @author Oleg Schildt
 */
interface IMessageManager extends IInitable
{
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
    public function init(array $parameters): void;

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
     * The element associated with the error.
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
     * @see IMessageManager::addErrorFromSmartException()
     * @see IMessageManager::addWarning()
     * @see IMessageManager::addProgWarning()
     * @see IMessageManager::addDebugMessage()
     * @see IMessageManager::addInfoMessage()
     * @see IMessageManager::addBubbleMessage()
     * @see IMessageManager::getErrors()
     * @see IMessageManager::hasErrors()
     * @see IMessageManager::clearErrors()
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
    ): void;

    /**
     * Appends a structured exception or every error in a collection using the current debug settings.
     *
     * @param \SmartFactory\SmartException|\SmartFactory\SmartExceptionCollection $smart_exception
     * The exception or collection to report. Collection entries retain their insertion order.
     *
     * @return void
     * Adds the exception data without serializing details or deduplicating errors.
     *
     * @see IMessageManager::addError()
     * @see IMessageManager::addWarning()
     * @see IMessageManager::addProgWarning()
     * @see IMessageManager::addDebugMessage()
     * @see IMessageManager::addInfoMessage()
     * @see IMessageManager::addBubbleMessage()
     * @see IMessageManager::getErrors()
     * @see IMessageManager::hasErrors()
     * @see IMessageManager::clearErrors()
     *
     * @author Oleg Schildt
     */
    public function addErrorFromSmartException(\SmartFactory\SmartException|\SmartFactory\SmartExceptionCollection $smart_exception): void;

    /**
     * Clears the stored error messages.
     *
     * @return void
     *
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::clearInfoMessages()
     * @see IMessageManager::clearBubbleMessages()
     * @see IMessageManager::clearAll()
     * @see IMessageManager::addError()
     *
     * @author Oleg Schildt
     */
    public function clearErrors(): void;

    /**
     * Checks whether any errors are stored.
     *
     * @return bool
     * Returns true if the stored error message exists, otherwise false.
     *
     * @see IMessageManager::hasWarnings()
     * @see IMessageManager::hasProgWarnings()
     * @see IMessageManager::hasDebugMessages()
     * @see IMessageManager::hasInfoMessages()
     * @see IMessageManager::hasBubbleMessages()
     * @see IMessageManager::getErrors()
     * @see IMessageManager::addError()
     *
     * @author Oleg Schildt
     */
    public function hasErrors(): bool;

    /**
     * Returns stored errors in insertion order and clears that category.
     *
     * @return list<array{message: string, details: array<array-key, mixed>, related_element: string,
     *     code: string, technical_info: string, file: string, line: int|string}>
     * Returns all stored errors, or an empty list. Other categories are unchanged.
     * Source lines are integers when visible, zero when unavailable and empty strings when hidden.
     *
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::getInfoMessages()
     * @see IMessageManager::getBubbleMessages()
     * @see IMessageManager::hasErrors()
     * @see IMessageManager::addError()
     *
     * @author Oleg Schildt
     */
    public function getErrors(): array;

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
     * @see IMessageManager::addError()
     * @see IMessageManager::addProgWarning()
     * @see IMessageManager::addDebugMessage()
     * @see IMessageManager::addInfoMessage()
     * @see IMessageManager::addBubbleMessage()
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::hasWarnings()
     * @see IMessageManager::clearWarnings()
     *
     * @author Oleg Schildt
     */
    public function addWarning(string $message, array $details = [], string $related_element = ""): void;

    /**
     * Clears the stored warning messages.
     *
     * @return void
     *
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::clearInfoMessages()
     * @see IMessageManager::clearBubbleMessages()
     * @see IMessageManager::clearAll()
     * @see IMessageManager::addWarning()
     *
     * @author Oleg Schildt
     */
    public function clearWarnings(): void;

    /**
     * Checks whether any warnings are stored.
     *
     * @return bool
     * Returns true if the stored warning message exists, otherwise false.
     *
     * @see IMessageManager::hasErrors()
     * @see IMessageManager::hasProgWarnings()
     * @see IMessageManager::hasDebugMessages()
     * @see IMessageManager::hasInfoMessages()
     * @see IMessageManager::hasBubbleMessages()
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::addWarning()
     *
     * @author Oleg Schildt
     */
    public function hasWarnings(): bool;

    /**
     * Returns stored warnings in insertion order and clears that category.
     *
     * @return list<array{message: string, details: array<array-key, mixed>, related_element: string}>
     * Returns all stored warnings, or an empty list. Other categories are unchanged.
     *
     * @see IMessageManager::getErrors()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::getInfoMessages()
     * @see IMessageManager::getBubbleMessages()
     * @see IMessageManager::hasWarnings()
     * @see IMessageManager::addWarning()
     *
     * @author Oleg Schildt
     */
    public function getWarnings(): array;

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
     * @see IMessageManager::addError()
     * @see IMessageManager::addWarning()
     * @see IMessageManager::addDebugMessage()
     * @see IMessageManager::addInfoMessage()
     * @see IMessageManager::addBubbleMessage()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::hasProgWarnings()
     * @see IMessageManager::clearProgWarnings()
     *
     * @author Oleg Schildt
     */
    public function addProgWarning(string $message, string $file = "", int|string|null $line = ""): void;

    /**
     * Clears the stored programming warning messages.
     *
     * @return void
     *
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::clearInfoMessages()
     * @see IMessageManager::clearBubbleMessages()
     * @see IMessageManager::clearAll()
     * @see IMessageManager::addProgWarning()
     *
     * @author Oleg Schildt
     */
    public function clearProgWarnings(): void;

    /**
     * Checks whether any programming warnings are stored.
     *
     * @return bool
     * Returns true if the stored programming warning message exists, otherwise false.
     *
     * @see IMessageManager::hasErrors()
     * @see IMessageManager::hasWarnings()
     * @see IMessageManager::hasDebugMessages()
     * @see IMessageManager::hasInfoMessages()
     * @see IMessageManager::hasBubbleMessages()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::addProgWarning()
     *
     * @author Oleg Schildt
     */
    public function hasProgWarnings(): bool;

    /**
     * Returns stored programming warnings in insertion order and clears that category.
     *
     * @return list<array{message: string, file: string, line: int|string}>
     * Returns all stored programming warnings, or an empty list. Other categories are unchanged.
     * Source lines are integers when visible, zero when unavailable and empty strings when hidden.
     *
     * @see IMessageManager::getErrors()
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::getInfoMessages()
     * @see IMessageManager::getBubbleMessages()
     * @see IMessageManager::hasProgWarnings()
     * @see IMessageManager::addProgWarning()
     *
     * @author Oleg Schildt
     */
    public function getProgWarnings(): array;

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
     * @see IMessageManager::addError()
     * @see IMessageManager::addWarning()
     * @see IMessageManager::addProgWarning()
     * @see IMessageManager::addInfoMessage()
     * @see IMessageManager::addBubbleMessage()
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::hasDebugMessages()
     * @see IMessageManager::clearDebugMessages()
     *
     * @author Oleg Schildt
     */
    public function addDebugMessage(string $message, string $file = "", int|string|null $line = ""): void;

    /**
     * Clears the stored debugging messages.
     *
     * @return void
     *
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::clearInfoMessages()
     * @see IMessageManager::clearBubbleMessages()
     * @see IMessageManager::clearAll()
     * @see IMessageManager::addDebugMessage()
     *
     * @author Oleg Schildt
     */
    public function clearDebugMessages(): void;

    /**
     * Checks whether any debugging messages are stored.
     *
     * @return bool
     * Returns true if the stored debugging message exists, otherwise false.
     *
     * @see IMessageManager::hasErrors()
     * @see IMessageManager::hasWarnings()
     * @see IMessageManager::hasProgWarnings()
     * @see IMessageManager::hasInfoMessages()
     * @see IMessageManager::hasBubbleMessages()
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::addDebugMessage()
     *
     * @author Oleg Schildt
     */
    public function hasDebugMessages(): bool;

    /**
     * Returns stored debugging messages in insertion order and clears that category.
     *
     * @return list<array{message: string, file: string, line: int|string}>
     * Returns all stored debugging messages, or an empty list. Other categories are unchanged.
     * Source lines are integers when visible, zero when unavailable and empty strings when hidden.
     *
     * @see IMessageManager::getErrors()
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::getInfoMessages()
     * @see IMessageManager::getBubbleMessages()
     * @see IMessageManager::hasDebugMessages()
     * @see IMessageManager::addDebugMessage()
     *
     * @author Oleg Schildt
     */
    public function getDebugMessages(): array;

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
     * The flag that controls whether the message box should be closed
     * automatically after a time.
     *
     * @return void
     * Retains the message unless its text is empty. Repeated messages are retained.
     *
     * @see IMessageManager::addError()
     * @see IMessageManager::addWarning()
     * @see IMessageManager::addProgWarning()
     * @see IMessageManager::addDebugMessage()
     * @see IMessageManager::addBubbleMessage()
     * @see IMessageManager::getInfoMessages()
     * @see IMessageManager::hasInfoMessages()
     * @see IMessageManager::clearInfoMessages()
     *
     * @author Oleg Schildt
     */
    public function addInfoMessage(string $message, array $details = [], bool $autoclose = false): void;

    /**
     * Clears the stored information messages.
     *
     * @return void
     *
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::clearBubbleMessages()
     * @see IMessageManager::clearAll()
     * @see IMessageManager::addInfoMessage()
     *
     * @author Oleg Schildt
     */
    public function clearInfoMessages(): void;

    /**
     * Checks whether any information messages are stored.
     *
     * @return bool
     * Returns true if the information message exists, otherwise false.
     *
     * @see IMessageManager::hasErrors()
     * @see IMessageManager::hasWarnings()
     * @see IMessageManager::hasProgWarnings()
     * @see IMessageManager::hasDebugMessages()
     * @see IMessageManager::hasBubbleMessages()
     * @see IMessageManager::getInfoMessages()
     * @see IMessageManager::addInfoMessage()
     *
     * @author Oleg Schildt
     */
    public function hasInfoMessages(): bool;

    /**
     * Returns stored information messages in insertion order and clears that category.
     *
     * @return list<array{message: string, details: array<array-key, mixed>, autoclose: bool}>
     * Returns all stored information messages, or an empty list. Other categories are unchanged.
     *
     * @see IMessageManager::getErrors()
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::getBubbleMessages()
     * @see IMessageManager::hasInfoMessages()
     * @see IMessageManager::addInfoMessage()
     *
     * @author Oleg Schildt
     */
    public function getInfoMessages(): array;

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
     * @see IMessageManager::addError()
     * @see IMessageManager::addWarning()
     * @see IMessageManager::addProgWarning()
     * @see IMessageManager::addDebugMessage()
     * @see IMessageManager::addInfoMessage()
     * @see IMessageManager::getBubbleMessages()
     * @see IMessageManager::hasBubbleMessages()
     * @see IMessageManager::clearBubbleMessages()
     *
     * @author Oleg Schildt
     */
    public function addBubbleMessage(string $message, array $details = [], bool $autoclose = true): void;

    /**
     * Clears the stored bubble messages.
     *
     * @return void
     * Removes all pending bubble messages.
     *
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::clearInfoMessages()
     * @see IMessageManager::clearAll()
     * @see IMessageManager::addBubbleMessage()
     *
     * @author Oleg Schildt
     */
    public function clearBubbleMessages(): void;

    /**
     * Checks whether any bubble messages are stored.
     *
     * @return bool
     * Returns true if the bubble message exists, otherwise false.
     *
     * @see IMessageManager::hasErrors()
     * @see IMessageManager::hasWarnings()
     * @see IMessageManager::hasProgWarnings()
     * @see IMessageManager::hasDebugMessages()
     * @see IMessageManager::hasInfoMessages()
     * @see IMessageManager::getBubbleMessages()
     * @see IMessageManager::addBubbleMessage()
     *
     * @author Oleg Schildt
     */
    public function hasBubbleMessages(): bool;

    /**
     * Returns stored bubble messages in insertion order and clears that category.
     *
     * @return list<array{message: string, details: array<array-key, mixed>, autoclose: bool}>
     * Returns all stored bubble messages, or an empty list. Other categories are unchanged.
     *
     * @see IMessageManager::getErrors()
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::getInfoMessages()
     * @see IMessageManager::hasBubbleMessages()
     * @see IMessageManager::addBubbleMessage()
     *
     * @author Oleg Schildt
     */
    public function getBubbleMessages(): array;

    /**
     * Clears all stored messages.
     *
     * @return void
     * Removes all pending messages in every category.
     *
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::clearInfoMessages()
     * @see IMessageManager::clearBubbleMessages()
     *
     * @author Oleg Schildt
     */
    public function clearAll(): void;
} // IMessageManager
