<?php
/**
 * This file contains the implementation of the class DBWorkerException. It extends the DBWorkerException
 * exception to distinguish the database related errors in catches.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\DatabaseWorkers;

use \SmartFactory\SmartException;

/**
 * Class for DBWorkerException in the SmartFactory library.
 *
 * @author Oleg Schildt
 */
class DBWorkerException extends SmartException
{
    /**
     * Constructor.
     *
     * @param string $message
     * Error message.
     *
     * @param string $error_code
     * Error code.
     *
     * Since the error texts can be localized, the unique code of the error might be important fo using
     * in comparison.
     *
     * @param string $error_element
     * Error element.
     *
     * @param array $error_details
     * The details might be useful if the message translations are provided on the client, not
     * on the server, and the message should contain some details that may vary from case to case.
     * In that case, the servers return the message id instead of final text and the details, the client
     * uses the message id, gets the final translated text and substitutes the parameters through the details.
     *
     * @param string $technical_info
     * Additional technical information. It can be used if it is a programming error and displayed only if the debug mode is active.
     *
     * @author Oleg Schildt
     */
    public function __construct(string $message, string $error_code, string $error_element = "", array $error_details = [], string $technical_info = "")
    {
        parent::__construct($message, $error_code, $error_element, $error_details, $technical_info);
    }
} // DBWorkerException