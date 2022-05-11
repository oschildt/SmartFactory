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
 * Class for extended exception user in the smarFactory library.
 *
 * @author Oleg Schildt
 */
class SmartException extends \Exception
{
    /**
     * Constant for the error type "user_error".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const ERR_TYPE_USER_ERROR = "user_error";

    /**
     * Constant for the error type "programming_error".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const ERR_TYPE_PROGRAMMING_ERROR = "programming_error";

    /**
     * Constant for the error code "system_error".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const ERR_CODE_SYSTEM_ERROR = "system_error";

    /**
     * Constant for the error code "config_error".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const ERR_CODE_CONFIG_ERROR = "config_error";

    /**
     * Constant for the error code "missing_request_data".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const ERR_CODE_MISSING_REQUEST_DATA = "missing_request_data";

    /**
     * Constant for the error code "invalid_request_data".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const ERR_CODE_INVALID_REQUEST_DATA = "invalid_request_data";

    /**
     * Constant for the error code "invalid_content_type".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const ERR_CODE_INVALID_CONTENT_TYPE = "invalid_content_type";

    /**
     * Constant for the error code "json_parse_error".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const ERR_CODE_JSON_PARSE_ERROR = "json_parse_error";

    /**
     * Constant for the error code "xml_parse_error".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const ERR_CODE_XML_PARSE_ERROR = "xml_parse_error";

    /**
     * Constant for the error code "not_found".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const ERR_CODE_NOT_FOUND = "not_found";

    /**
     * Internal variable for storing the error.
     *
     * @var string
     *
     * @see getErrorCode()
     *
     * @author Oleg Schildt
     */
    protected $error_code = SmartException::ERR_CODE_SYSTEM_ERROR;

    /**
     * Internal variable for storing the error type.
     *
     * @var string
     *
     * @see getErrorType()
     *
     * @author Oleg Schildt
     */
    protected $error_type = SmartException::ERR_TYPE_PROGRAMMING_ERROR;

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
     * @param string $error_type
     * Error type.
     *
     * The type of the error might be useful for decision how to show the error on the client. If it is a
     * user error, the full error texts should be shown. If it is a programming error, the detailed text should
     * be shown only in the debug mode to prevent that the hackers get sensible information about the system.
     *
     * @author Oleg Schildt
     */
    public function __construct($message, $error_code, $error_type)
    {
        parent::__construct($message);

        $this->error_code = $error_code;
        $this->error_type = $error_type;
    }

    /**
     * Returns the string error code of the exception.
     *
     * Since the error texts can be localized, the unique code of the error might be important fo using
     * in comparison.
     *
     * @return string
     * Returns the error code.
     *
     * @see getErrorType()
     *
     * @author Oleg Schildt
     */
    public function getErrorCode()
    {
        return $this->error_code;
    }

    /**
     * Returns the error type of the exception.
     *
     * The type of the error might be useful for decision how to show the error on the client. If it is a
     * user error, the full error texts should be shown. If it is a programming error, the detailed text should
     * be shown only in the debug mode to prevent that the hackers get sensible information about the system.
     *
     * @return string
     * Returns the error type.
     *
     * @see getErrorCode()
     *
     * @author Oleg Schildt
     */
    public function getErrorType()
    {
        return $this->error_type;
    }
} // SmartException