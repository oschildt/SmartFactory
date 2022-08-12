<?php
/**
 * This file contains the implementation of the interface IRequestHandler
 * in the class JsonRequestHandler for handling the JSON API requests.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\Interfaces\IRequestHandler;

/**
 * Class for handling the JSON API requests.
 *
 * The user should derive from this class and implement the methods with the names
 * of the API actions.
 *
 * URL: http://smartfactorydev.loc/api/login/
 *
 * Action: login
 *
 * ```php
 * function login()
 * {
 *     if (empty($this->request_data["login"]) || empty($this->request_data["password"]) ||
 *         $this->request_data["login"] != "admin" || $this->request_data["password"] != "qwerty") {
 *         throw new SmartException("Wrong login or password!", "wrong_login", SmartException::ERR_TYPE_USER_ERROR);
 *     }
 *
 *     $this->response_data["welcome_msg"] = "Welcome, admin!";
 * }
 * ```
 *
 * @author Oleg Schildt
 */
class JsonRequestHandler implements IRequestHandler
{
    /**
     * Internal variable for storing the API action.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected $action = "";

    /**
     * Internal variable for storing the API parameter string.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected $param_string = "";

    /**
     * Internal variable for storing the request headers.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $request_headers = array();

    /**
     * Internal variable for storing the response headers.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $response_headers = array();

    /**
     * Internal variable for storing the processing errors.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $errors = array();

    /**
     * Internal variable for storing the processing warnings.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $warnings = array();

    /**
     * Internal variable for storing the processing information messages.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $info_messages = array();

    /**
     * Internal variable for storing the processing bubble messages.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $bubble_messages = array();

    /**
     * Internal variable for storing the request data.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $request_data = array();

    /**
     * Internal variable for storing the response data.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $response_data = array();

    /**
     * This is an auxiliary function for getting request headers and parsing the API url.
     *
     * It put the retrieved data into the corresponding properties.
     *
     * @return void
     *
     * @see JsonRequestHandler::$action
     * @see JsonRequestHandler::$param_string
     * @see JsonRequestHandler::$request_headers
     *
     * @author Oleg Schildt
     */
    protected function processInputData()
    {
        $this->response_headers = [];

        $this->addResponseHeader("Content-type", "application/json; charset=UTF-8");

        if (empty($_SERVER['REQUEST_URI'])) {
            return;
        }

        $api_base = str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']);
        $api_request = str_replace($api_base, "", $_SERVER['REQUEST_URI']);

        if (preg_match("~^([^/.?&]+)([/?]?.*)~", $api_request, $matches)) {
            $this->action = $matches[1];
            $this->param_string = $matches[2];
        } else {
            $this->action = reqvar_value("action");
            $this->param_string = str_replace(basename($_SERVER['SCRIPT_NAME']), "", $api_request);
        }

        $this->request_headers = getallheaders();
    } // processInputData

    /**
     * This is an auxiliary function for sending the response in JSON format.
     *
     * It sends the collected response data and the response headers. The header
     * "Content-type: application/json; charset=UTF-8" ist sent automatically.
     *
     * @return void
     *
     * @see JsonRequestHandler::$response_headers
     *
     * @author Oleg Schildt
     */
    protected function sendJsonResponse()
    {
        if (!empty($this->response_headers)) {
            if (is_array($this->response_headers)) {
                foreach ($this->response_headers as $header => $value) {
                    header($header . ": " . $value);
                }
            }
        }

        if (empty($this->response_data)) {
            echo json_encode($this->response_data, JSON_FORCE_OBJECT | JSON_PRETTY_PRINT);
        } else {
            echo json_encode($this->response_data, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);
        }
    } // sendJsonResponse

    /**
     * Adds a new error to the error list.
     *
     * It is possible to validate an API request and return many errors at once.
     *
     * @param array $error
     * Error description the form key => value:
     *
     * - $error["code"] - error code. Since the error texts can be
     * localized, the unique code of the error might be important fo using in comparison.
     *
     * - $error["type"] - error type. The type of the error might be useful for decision how to show the error on the client. If it is a
     * user error, the full error texts should be shown. If it is a programming error, the detailed text should
     * be shown only in the debug mode to prevent that the hackers get sensible information about the system.
     *
     * - $error["message"] - error message.
     *
     * - $error["focus_element"] - error element.
     *
     * - $error["details"] - error details. The error details might be useful if the error message translations are provided on the client, not
     * on the server, and the error message should contain some details that may vary from case to case.
     * In that case, the servers return the error message id instead of final text and the details, the client
     * uses the error message id, gets the final translated text and substitutes the parameters through the details.
     *
     * @return void
     *
     * @see JsonRequestHandler::addExceptionError()
     * @see JsonRequestHandler::exitWithErrors()
     * @see JsonRequestHandler::addBubbleMessage()
     * @see JsonRequestHandler::addWarning()
     * @see JsonRequestHandler::addInfoMessage()
     *
     * @author Oleg Schildt
     */
    protected function addError(array $error)
    {
        $this->errors[] = $error;
    } // addError

    /**
     * Adds a response header to the response.
     *
     * @param string $header
     * The name of the response header.
     *
     * @param string $value
     * The value of the response header.
     *
     * @return void
     *
     * @see JsonRequestHandler::sendJsonResponse()
     *
     * @author Oleg Schildt
     */
    protected function addResponseHeader($header, $value)
    {
        $this->response_headers[$header] = $value;
    } // addResponseHeader

    /**
     * Adds a new warning to the warning list.
     *
     * @param array $warning
     * Warning description the form key => value:
     *
     * - $warning["message"] - warning message.
     *
     * - $warning["focus_element"] - warning element.
     *
     * - $warning["details"] - warning details. The warning details might be useful if the warning message translations are provided on the client, not
     * on the server, and the warning message should contain some details that may vary from case to case.
     * In that case, the servers return the warning message id instead of final text and the details, the client
     * uses the warning message id, gets the final translated text and substitutes the parameters through the details.
     *
     * @return void
     *
     * @see JsonRequestHandler::addBubbleMessage()
     * @see JsonRequestHandler::addError()
     * @see JsonRequestHandler::addInfoMessage()
     *
     * @author Oleg Schildt
     */
    protected function addWarning(array $warning)
    {
        $this->warnings[] = $warning;
    } // addWarning

    /**
     * Adds a new information message to the info list.
     *
     * @param array $info_message
     * Message description the form key => value:
     *
     * - $info_message["message"] - information message.
     *
     * - $info_message["details"] - information details. The information details might be useful if the information message translations are provided on the client, not
     * on the server, and the information message should contain some details that may vary from case to case.
     * In that case, the servers return the information message id instead of final text and the details, the client
     * uses the information message id, gets the final translated text and substitutes the parameters through the details.
     *
     * @return void
     *
     * @see JsonRequestHandler::addBubbleMessage()
     * @see JsonRequestHandler::addError()
     * @see JsonRequestHandler::addWarning()
     *
     * @author Oleg Schildt
     */
    protected function addInfoMessage(array $info_message)
    {
        $this->info_messages[] = $info_message;
    } // addInfoMessage

    /**
     * Adds a new bubble message to the bubble message list.
     *
     * @param array $bubble_message
     * Message description the form key => value:
     *
     * - $bubble_message["message"] - bubble message.
     *
     * - $bubble_message["details"] - bubble message details. The bubble message details might be useful if the bubble message translations are provided on the client, not
     * on the server, and the bubble message should contain some details that may vary from case to case.
     * In that case, the servers return the bubble message id instead of final text and the details, the client
     * uses the bubble message id, gets the final translated text and substitutes the parameters through the details.
     *
     * @return void
     *
     * @see JsonRequestHandler::addInfoMessage()
     * @see JsonRequestHandler::addError()
     * @see JsonRequestHandler::addWarning()
     *
     * @author Oleg Schildt
     */
    protected function addBubbleMessage(array $bubble_message)
    {
        $this->bubble_messages[] = $bubble_message;
    } // addBubbleMessage

    /**
     * This is an auxiliary function for adding an error to the list
     * from an exception.
     *
     * @param \Throwable $ex
     * The thrown exception.
     *
     * @return void
     *
     * @see JsonRequestHandler::addError()
     * @see JsonRequestHandler::exitWithErrors()
     *
     * @author Oleg Schildt
     */
    protected function addExceptionError(\Throwable $ex)
    {
        if ($ex instanceof SmartException) {
            $this->addError(
                [
                    "code" => $ex->getErrorCode(),
                    "type" => $ex->getErrorType(),
                    "message" => $ex->getMessage(),
                    "focus_element" => $ex->getErrorElement()
                ]);
        } else {
            $this->addError(
                [
                    "code" => SmartException::ERR_CODE_SYSTEM_ERROR,
                    "type" => SmartException::ERR_TYPE_PROGRAMMING_ERROR,
                    "message" => $ex->getMessage()
                ]);
        }
    } // addExceptionError

    /**
     * This is an auxiliary function for exiting the processing and
     * sending all collected errors to the response.
     *
     * @return void
     *
     * @see JsonRequestHandler::addError()
     * @see JsonRequestHandler::addExceptionError()
     *
     * @author Oleg Schildt
     */
    protected function exitWithErrors()
    {
        $this->response_data = [];

        $this->response_data["result"] = "error";
        $this->response_data["errors"] = $this->errors;

        if ($this->warnings) {
            $this->response_data["warnings"] = $this->warnings;
        }
        if ($this->info_messages) {
            $this->response_data["info_messages"] = $this->info_messages;
        }
        if ($this->bubble_messages) {
            $this->response_data["bubble_messages"] = $this->bubble_messages;
        }

        $this->sendJsonResponse();
        exit;
    } // exitWithErrors

    /**
     * This is an auxiliary function for parsing the incoming JSON data.
     *
     * It validates the content type 'application/json' and takes the data from RAWDATA.
     *
     * @return void
     *
     * @throws SmartException
     * It might throw an exception if the content type oк JSON data is invalid.
     *
     * @author Oleg Schildt
     */
    protected function parseJsonInput()
    {
        try {
            if (empty($this->request_headers["Content-Type"])) {
                throw new SmartException("Content type header 'Content-Type' is missing, expected 'application/json; charset=UTF-8'!", SmartException::ERR_CODE_INVALID_CONTENT_TYPE, SmartException::ERR_TYPE_PROGRAMMING_ERROR);
            }

            $content_type = $this->request_headers["Content-Type"];
            if (!preg_match("/application\/json.*charset=UTF-8/i", $content_type)) {
                throw new SmartException(sprintf("Content type 'application/json; charset=UTF-8' is expected, got '%s'!", $content_type), SmartException::ERR_CODE_INVALID_CONTENT_TYPE, SmartException::ERR_TYPE_PROGRAMMING_ERROR);
            }

            $json = trim(file_get_contents("php://input"));

            if (empty($json)) {
                throw new SmartException("The request JSON is empty!", SmartException::ERR_CODE_MISSING_REQUEST_DATA, SmartException::ERR_TYPE_PROGRAMMING_ERROR);
            }

            $json = json_decode($json, true);

            if (empty($json) && !is_array($json)) {
                throw new SmartException(json_last_error_msg(), SmartException::ERR_CODE_JSON_PARSE_ERROR, SmartException::ERR_TYPE_PROGRAMMING_ERROR);
            }

            $this->request_data = array_merge($this->request_data, $json);
        } catch (SmartException $ex) {
            throw $ex;
        } catch (\Throwable $ex) {
            throw new SmartException($ex->getMessage(), SmartException::ERR_CODE_JSON_PARSE_ERROR, SmartException::ERR_TYPE_PROGRAMMING_ERROR);
        }
    } // parseJsonInput

    /**
     * This function is called before the processing of the request. It should be overridden if you want
     * to perform some standard action before every API request of your library.
     * The usage example are - validation of the access tokens, checking maintenance mode and reporting it to
     * the user if it is active.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    protected function preprocessRequest()
    {
    } // preprocessRequest

    /**
     * This function is called after the processing of the request. It should be overridden if you want
     * to perform some standard action after every API request of your library.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    protected function postprocessRequest()
    {
    } // postprocessRequest

    /**
     * This is the main function that should be called to handle the API request.
     *
     * It detects the action, retrieves the request headers, parses the incoming JSON data and
     * tries to call the corresponding method for the API action.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function handleRequest()
    {
        try {
            $this->processInputData();

            $this->parseJsonInput();

            if (empty($this->action)) {
                throw new SmartException("The action of the API request cannot be defined!", SmartException::ERR_CODE_SYSTEM_ERROR, SmartException::ERR_TYPE_PROGRAMMING_ERROR);
            }

            $robject = new \ReflectionObject($this);
            if (!$robject->hasMethod($this->action)) {
                throw new SmartException(sprintf("No handler is defined for the API request action '%s'!", $this->action), SmartException::ERR_CODE_SYSTEM_ERROR, SmartException::ERR_TYPE_PROGRAMMING_ERROR);
            }

            $rmethod = $robject->getMethod($this->action);

            if ($this->action == "handleRequest" || $rmethod->isConstructor() || $rmethod->isDestructor() || !$rmethod->isPublic()) {
                throw new SmartException(sprintf("The name '%s' for the API action is not supported!", $this->action), SmartException::ERR_CODE_SYSTEM_ERROR, SmartException::ERR_TYPE_PROGRAMMING_ERROR);
            }

            $this->response_data["result"] = "success";

            $this->preprocessRequest();
            $rmethod->invoke($this);
            $this->postprocessRequest();

            if ($this->warnings) {
                $this->response_data["warnings"] = $this->warnings;
            }
            if ($this->info_messages) {
                $this->response_data["info_messages"] = $this->info_messages;
            }
            if ($this->bubble_messages) {
                $this->response_data["bubble_messages"] = $this->bubble_messages;
            }

            $this->sendJsonResponse();
        } catch (\Exception $ex) {
            $this->addExceptionError($ex);
            $this->exitWithErrors();
        }
    } // handleRequest
} // JsonRequestHandler