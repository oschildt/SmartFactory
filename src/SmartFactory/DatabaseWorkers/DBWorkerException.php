<?php
/**
 * This file contains the implementation of the class DBWorkerException.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\DatabaseWorkers;

use SmartFactory\SmartException;

/**
 * Exception for database worker connection, query, data-format and stream errors.
 *
 * Extends SmartException with stable string error codes and optional technical SQL information.
 *
 * @author Oleg Schildt
 */
class DBWorkerException extends SmartException
{
    /**
     * Creates an exception carrying a stable database-worker error code.
     *
     * @param string $message
     * The human-readable error message.
     *
     * @param string $error_code
     * The stable error code, independent of the localized error message.
     *
     * @param string $error_element
     * The related input or application element, when available.
     *
     * @param array<array-key, mixed> $error_details
     * Optional structured details used by an application, including localized-message substitutions.
     *
     * @param string $technical_info
     * Technical diagnostics such as an SQL template. Applications decide whether to display them.
     *
     * @param \Throwable|null $previous
     * The original exception or error, or null when there is no underlying cause.
     *
     * @author Oleg Schildt
     */
    public function __construct(
        string $message,
        string $error_code,
        string $error_element = "",
        array $error_details = [],
        string $technical_info = "",
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $error_code, $error_element, $error_details, $technical_info, $previous);
    } // __construct
} // DBWorkerException
