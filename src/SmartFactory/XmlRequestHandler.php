<?php
/**
 * This file contains the implementation of the interface IRequestHandler
 * in the class XmlRequestHandler for handling the XML API requests.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\Interfaces\IRequestHandler;

/**
 * Class for handling the XML API requests.
 *
 * The user should derive from this class and implement the methods with the names
 * of the API actions.
 *
 * URL: http://smartfactorydev.loc/api/get_rooms/
 *
 * Action: get_rooms
 *
 * ```php
 * function get_rooms()
 * {
 *    $xsdpath = new \DOMXPath($this->request_xmldoc);
 *
 *    $nodes = $xsdpath->evaluate("/Request/City");
 *    if ($nodes->length == 0) {
 *        throw new SmartException("City is undefined!", "no_city", SmartException::ERR_TYPE_USER_ERROR);
 *    }
 *    $city = $nodes->item(0)->nodeValue;
 *
 *    $response = $this->response_xmldoc->createElement("Response");
 *    $this->response_xmldoc->appendChild($response);
 *
 *    ...
 * } // get_rooms
 * ```
 *
 * @author Oleg Schildt
 */
abstract class XmlRequestHandler implements IRequestHandler
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
     * Internal variable for storing the request XML document.
     *
     * @var \DOMDocument
     *
     * @author Oleg Schildt
     */
    protected $request_xmldoc;

    /**
     * Internal variable for storing the response XML document.
     *
     * @var \DOMDocument
     *
     * @author Oleg Schildt
     */
    protected $response_xmldoc;

    /**
     * Constructor.
     *
     * It ititialize the XML document variables.
     *
     * @author Oleg Schildt
     */
    function __construct() {
        $this->request_xmldoc = new \DOMDocument("1.0", "UTF-8");
        $this->request_xmldoc->formatOutput = true;

        $this->response_xmldoc = new \DOMDocument("1.0", "UTF-8");
        $this->response_xmldoc->formatOutput = true;
    }

    /**
     * This is an auxiliary function for getting request headers and parsing the API url.
     *
     * It put the retrieved data into the corresponding properties.
     *
     * @return void
     *
     * @see XmlRequestHandler::$action
     * @see XmlRequestHandler::$param_string
     * @see XmlRequestHandler::$request_headers
     *
     * @author Oleg Schildt
     */
    protected function processInputData()
    {
        if (empty($_SERVER['REQUEST_URI'])) {
            return;
        }

        $api_base = str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']);
        $api_request = str_replace($api_base, "", $_SERVER['REQUEST_URI']);

        if (preg_match("~^([^/.?&]+)([/?]?.+)~", $api_request, $matches)) {
            $this->action = $matches[1];
            $this->param_string = $matches[2];
        } else {
            $this->action = reqvar_value("action");
            $this->param_string = str_replace(basename($_SERVER['SCRIPT_NAME']), "", $api_request);
        }

        $this->request_headers = getallheaders();
    }

    /**
     * This is an auxiliary function for parsing the incoming XML data.
     *
     * It validates the content type 'application/xml' and takes the data from RAWDATA.
     *
     * @throws SmartException
     * It might throw an exception if the content type oк XML data is invalid.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    protected function parseXmlInput()
    {
        try {
            $content_type = empty($this->request_headers["Content-Type"]) ? "" : $this->request_headers["Content-Type"];
            if (!preg_match("/application\/xml.*/", $content_type)) {
                throw new SmartException(sprintf("Content type 'application/xml' is expected, got '%s'!", $content_type), SmartException::ERR_CODE_INVALID_CONTENT_TYPE, SmartException::ERR_TYPE_PROGRAMMING_ERROR);
            }

            $xmldata = trim(file_get_contents("php://input"));

            if (empty($xmldata)) {
                throw new SmartException("The request XML is empty!", SmartException::ERR_CODE_MISSING_REQUEST_DATA, SmartException::ERR_TYPE_PROGRAMMING_ERROR);
            }

            $this->request_xmldoc = new \DOMDocument("1.0", "UTF-8");
            $this->request_xmldoc->formatOutput = true;
            if (!@$this->request_xmldoc->loadXML($xmldata)) {
                throw new SmartException("Error by XML parsing!", SmartException::ERR_CODE_XML_PARSE_ERROR, SmartException::ERR_TYPE_PROGRAMMING_ERROR);
            }
        } catch (SmartException $ex) {
            throw $ex;
        } catch (\Throwable $ex) {
            throw new SmartException($ex->getMessage(), SmartException::ERR_CODE_XML_PARSE_ERROR, SmartException::ERR_TYPE_PROGRAMMING_ERROR);
        }
    } // parseXmlInput

    /**
     * Adds a new error to the error list.
     *
     * It is possible to validate an API request and return many errors at once.
     *
     * @param array $error
     * Error descrition the form key => value:
     *
     * - $error["error_code"] - error code. Since the error texts can be
     * localized, the unique code of the error might be important fo using in comparison.
     *
     * - $error["error_type"] - error type. The type of the error might be useful for decision how to show the error on the client. If it is a
     * user error, the full error texts should be shown. If it is a programming error, the detailed text should
     * be shown only in the debug mode to prevent that the hackers get sensible information about the system.
     *
     * - $error["error_message"] - error message.
     *
     * @return void
     *
     * @see XmlRequestHandler::addExceptionError()
     * @see XmlRequestHandler::exitWithErrors()
     *
     * @author Oleg Schildt
     */
    protected function addError(array $error)
    {
        $this->errors[] = $error;
    } // addError

    /**
     * This is an auxiliary function for adding an error to the list
     * from an exception.
     *
     * @param \Throwable $ex
     * The thrown exception.
     *
     * @return void
     *
     * @see XmlRequestHandler::addError()
     * @see XmlRequestHandler::exitWithErrors()
     *
     * @author Oleg Schildt
     */
    protected function addExceptionError(\Throwable $ex)
    {
        if ($ex instanceof SmartException) {
            $this->addError(
                [
                    "error_code" => $ex->getErrorCode(),
                    "error_type" => $ex->getErrorType(),
                    "error_text" => $ex->getMessage()
                ]);
        } else {
            $this->addError(
                [
                    "error_code" => SmartException::ERR_CODE_SYSTEM_ERROR,
                    "error_type" => SmartException::ERR_TYPE_PROGRAMMING_ERROR,
                    "error_text" => $ex->getMessage()
                ]);
        }
    } // addExceptionError

    /**
     * This is an auxiliary function for exiting the processing and
     * sending all collected errors to the response.
     *
     * Since the desired format of the response XML is not fixed, the user should implement
     * this method in his derived class.
     *
     * @return void
     *
     * @see XmlRequestHandler::addError()
     * @see XmlRequestHandler::addExceptionError()
     *
     * @author Oleg Schildt
     */
    protected abstract function exitWithErrors();

    /**
     * This is an auxiliary function for sending the response in XML format.
     *
     * It sends the prepared response XML document and the response headers. The header
     * "Content-type: application/xml" ist sent automatically.
     *
     * @return void
     *
     * @see XmlRequestHandler::$response_headers
     *
     * @author Oleg Schildt
     */
    protected function sendXmlResponse()
    {
        header('Content-type: application/xml');

        if (!empty($this->response_headers)) {
            if (is_array($this->response_headers)) {
                foreach ($this->response_headers as $header) {
                    header($header);
                }
            }
        }

        echo $this->response_xmldoc->saveXML();
    } // sendXmlResponse

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
     * It detects the action, retrieves the request headers, parses the incoming XML data and
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

            $this->parseXmlInput();

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

            $this->preprocessRequest();
            $rmethod->invoke($this);
            $this->postprocessRequest();

            $this->sendXmlResponse();
        } catch (\Exception $ex) {
            $this->addExceptionError($ex);
            $this->exitWithErrors();
        }
    } // handleRequest
} // XmlRequestHandler