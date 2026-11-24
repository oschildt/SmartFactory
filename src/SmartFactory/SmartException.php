<?php
/**
 * Implements SmartException, extending the standard exception with a stable string error code,
 * a related element, structured localization details and optional technical information.
 *
 * String error codes identify errors independently of localized messages. Applications decide which
 * messages are appropriate for clients and expose technical diagnostics only when debugging is enabled.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

/**
 * Carries a localized message, a stable string error code and optional structured diagnostics.
 *
 * The string code is available through getErrorCode(); the inherited getCode() remains zero.
 * The original exception or error can be preserved through getPrevious().
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
     * Identifies requests rejected because an API admission limit has been reached.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    const string ERR_CODE_TOO_MANY_REQUESTS = "too_many_requests";

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
     * Stores the stable string error code independently of the native integer exception code.
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
     * In that case, the server returns a message identifier and details. The client translates the message
     * and substitutes its parameters using those details. Keys, value types and nested data are preserved.
     *
     * @var array<array-key, mixed>
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
     * Creates an exception with a stable string code, optional diagnostics and an underlying cause.
     *
     * @param string $message
     * Error message.
     *
     * @param string $error_code
     * Error code.
     *
     * The code identifies the error independently of the localized message.
     *
     * @param string $error_element
     * Error element.
     *
     * @param array<array-key, mixed> $error_details
     * The details might be useful if the message translations are provided on the client, not
     * on the server, and the message should contain some details that may vary from case to case.
     * The server can return a message identifier and details so the client can translate the message
     * and substitute its parameters. Keys, value types and nested data are preserved without conversion.
     *
     * @param string $technical_info
     * Additional technical information. Applications should expose it only when debugging is enabled.
     *
     * @param \Throwable|null $previous
     * The original exception or error, or null when there is no underlying cause.
     *
     * @author Oleg Schildt
     */
    public function __construct(
        string $message,
        string $error_code = SmartException::ERR_CODE_SYSTEM_ERROR,
        string $error_element = "",
        array $error_details = [],
        string $technical_info = "",
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);

        $this->error_code = $error_code;
        $this->error_element = $error_element;
        $this->error_details = $error_details;
        $this->technical_info = $technical_info;
    } // __construct

    /**
     * Returns the string error code of the exception.
     *
     * The code identifies the error independently of the localized message.
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
    } // getErrorCode

    /**
     * Sets the error element.
     *
     * @param string $error_element
     * Error element.
     *
     * @return void
     * Updates the related element without changing the other exception data.
     *
     * @see SmartException::getErrorElement()
     *
     * @author Oleg Schildt
     */
    public function setErrorElement(string $error_element): void
    {
        $this->error_element = $error_element;
    } // setErrorElement

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
    } // getErrorElement

    /**
     * Returns the error details of the exception.
     *
     * The error details might be useful if the error message translations are provided on the client, not
     * on the server, and the error message should contain some details that may vary from case to case.
     * In that case, the server returns a message identifier and details. The client translates the message
     * and substitutes its parameters using those details.
     *
     * @return array<array-key, mixed>
     * Returns the details with their original keys, value types and nested data.
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
    } // getErrorDetails

    /**
     * Returns the technical information for the error.
     *
     * @return string
     * Returns the technical information for the error.
     *
     * @see SmartException::getErrorCode()
     * @see SmartException::getErrorElement()
     * @see SmartException::getErrorDetails()
     *
     * @author Oleg Schildt
     */
    public function getTechnicalInfo(): string
    {
        return $this->technical_info;
    } // getTechnicalInfo
} // SmartException
