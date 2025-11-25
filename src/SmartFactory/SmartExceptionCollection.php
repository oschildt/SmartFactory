<?php
/**
 * This file contains the implementation of the class SmartException. It extends the standard
 * exception by the string code and type of excetion.
 *
 * Since the error texts can be localized, the unique code of the error might be important fo using
 * in comparison.
 * The type of the error might be useful for decision how to show the error on the client. If it is a
 * user error, the full error texts should be shown. If it is a programming error, the detailed text should
 * be shown only in the debug mode to prevent that the hackers get sensible information about the system.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

/**
 * Class for extended exception collection used in the SmartFactory library. It allows to collect many errors
 * and report them at once.
 *
 * @author Oleg Schildt
 */
class SmartExceptionCollection extends \Exception
{
    /**
     * Internal variable for storing the single errors.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected array $errors = [];

    /**
     * Stores the error to be reported.
     *
     * @param string $message
     * The error message to be reported.
     *
     * @param array $details
     * The details might be useful if the message translations are provided on the client, not
     * on the server, and the message should contain some details that may vary from case to case.
     * In that case, the servers return the message id instead of final text and the details, the client
     * uses the message id, gets the final translated text and substitutes the parameters through the details.
     *
     * @param string $related_element
     * The error element associated with the error.
     *
     * @param string $code
     * The error code to be reported.
     *
     * @param string $technical_info
     * The technical information for the error. Displaying
     * of this part might be controlled over an option
     * "debug_mode".
     *
     * @param string $file
     * The source file where the error occurred. Per default, the file where the adding error called.
     *
     * @param string $line
     * The source file line where the error occurred. Per default, the file line where the adding error called.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function addError(string $message, array $details = [], string $related_element = "", string $code = "", string $technical_info = "", string $file = "", string $line = ""): void
    {
        if (empty($message)) {
            return;
        }

        if (empty($file) || empty($line)) {
            $backfiles = debug_backtrace();

            if (empty($file)) {
                $file = empty($backfiles[0]['file']) ? "" : $backfiles[0]['file'];
            }

            if (empty($line)) {
                $line = empty($backfiles[0]['line']) ? "" : $backfiles[0]['line'];
            }
        }

        $this->errors[md5($message . implode("#", $details))] = [
            "message" => $message,
            "details" => $details,
            "related_element" => $related_element,
            "code" => $code,
            "technical_info" => $technical_info,
            "file" => $file,
            "line" => $line
        ];
    }

    /**
     * Throws itself if any error has been collected.
     *
     * @return void
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
    }

    /**
     * Returns the array of errors if any have been stored.
     *
     * @return array
     * Returns the array of errors if any have been stored.
     *
     * @author Oleg Schildt
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
} // SmartExceptionCollection