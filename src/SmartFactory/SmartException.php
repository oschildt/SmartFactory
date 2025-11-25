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
 * Class for extended exception used in the SmartFactory library.
 *
 * @author Oleg Schildt
 */
class SmartException extends \Exception
{
    /**
     * Constant for the error code "system_error".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_CODE_SYSTEM_ERROR = "system_error";

    /**
     * Constant for the error code "config_error".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_CODE_CONFIG_ERROR = "config_error";

    /**
     * Constant for the error code "missing_request_data".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_CODE_MISSING_REQUEST_DATA = "missing_request_data";

    /**
     * Constant for the error code "invalid_request_data".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_CODE_INVALID_REQUEST_DATA = "invalid_request_data";

    /**
     * Constant for the error code "invalid_content_type".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_CODE_INVALID_CONTENT_TYPE = "invalid_content_type";

    /**
     * Constant for the error code "json_parse_error".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_CODE_JSON_PARSE_ERROR = "json_parse_error";

    /**
     * Constant for the error code "xml_parse_error".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_CODE_XML_PARSE_ERROR = "xml_parse_error";

    /**
     * Constant for the error code "path_access_error".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_CODE_PATH_ACCESS_ERROR = "path_access_error";

    /**
     * Constant for the error code "file_write_error".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_CODE_FILE_WRITE_ERROR = "file_write_error";

    /**
     * Constant for the error code "not_found".
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_CODE_NOT_FOUND = "not_found";

    /**
     * Internal variable for storing the error element.
     *
     * @var string
     *
     * @see SmartException::getErrorElement()
     *
     * @author Oleg Schildt
     */
    protected string $error_element = "";

    /**
     * Internal variable for storing the error.
     *
     * @var string
     *
     * @see SmartException::getErrorCode()
     *
     * @author Oleg Schildt
     */
    protected string $error_code = SmartException::ERR_CODE_SYSTEM_ERROR;

    /**
     * Internal variable for storing the error details.
     *
     * The error details might be useful if the error message translations are provided on the client, not
     * on the server, and the error message should contain some details that may vary from case to case.
     * In that case, the servers return the error message id instead of final text and the details, the client
     * uses the error message id, gets the final translated text and substitutes the parameters through the details.
     *
     * @var array
     *
     * @see SmartException::getErrorDetails()
     *
     * @author Oleg Schildt
     */
    protected array $error_details = [];

    /**
     * Internal variable for storing the additional technical information.
     *
     * @var string
     *
     * @see SmartException::getTechnicalInfo()
     *
     * @author Oleg Schildt
     */
    protected string $technical_info = "";

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
    public function __construct(string $message, string $error_code = SmartException::ERR_CODE_SYSTEM_ERROR, string $error_element = "", array $error_details = [], string $technical_info = "")
    {
        parent::__construct($message);

        $this->error_code = $error_code;
        $this->error_element = $error_element;
        $this->error_details = $error_details;
        $this->technical_info = $technical_info;
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
     * @see SmartException::getErrorDetails()
     * @see SmartException::getErrorElement()
     * @see SmartException::getTechnicalInfo()
     *
     * @author Oleg Schildt
     */
    public function getErrorCode(): string
    {
        return $this->error_code;
    }

    /**
     * Sets the error element.
     *
     * @param string $error_element
     * Error element.
     *
     * @return void
     *
     * @see SmartException::getErrorElement()
     *
     * @author Oleg Schildt
     */
    public function setErrorElement(string $error_element): void
    {
        $this->error_element = $error_element;
    }

    /**
     * Returns the error element.
     *
     * @return string
     * Returns the error element.
     *
     * @see SmartException::setErrorElement()
     * @see SmartException::getErrorCode()
     * @see SmartException::getErrorDetails()
     * @see SmartException::getTechnicalInfo()
     *
     * @author Oleg Schildt
     */
    public function getErrorElement(): string
    {
        return $this->error_element;
    }

    /**
     * Returns the error details of the exception.
     *
     * The error details might be useful if the error message translations are provided on the client, not
     * on the server, and the error message should contain some details that may vary from case to case.
     * In that case, the servers return the error message id instead of final text and the details, the client
     * uses the error message id, gets the final translated text and substitutes the parameters through the details.
     *
     * @return array
     * Returns the error details.
     *
     * @see SmartException::getErrorCode()
     * @see SmartException::getErrorElement()
     * @see SmartException::getTechnicalInfo()
     *
     * @author Oleg Schildt
     */
    public function getErrorDetails(): array
    {
        return $this->error_details;
    }

    /**
     * Returns the technical information for the error.
     *
     * @return string
     * Returns the technical information for the error.
     *
     * @see SmartException::getErrorCode()
     * @see SmartException::getErrorElement()
     * @see SmartException::getTechnicalInfo()
     *
     * @author Oleg Schildt
     */
    public function getTechnicalInfo(): string
    {
        return $this->technical_info;
    }
} // SmartException