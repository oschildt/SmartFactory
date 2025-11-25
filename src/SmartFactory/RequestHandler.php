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

use \SmartFactory\Interfaces\IErrorHandler;
use \SmartFactory\Interfaces\IRequestHandler;

/**
 * Abstract class for handling the JSON and XML requests.
 *
 * @author Oleg Schildt
 */
abstract class RequestHandler implements IRequestHandler
{
    /**
     * Internal variable for storing the API action.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $action = "";

    /**
     * Internal variable for storing the API parameter string.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $param_string = "";

    /**
     * Internal variable for storing the response headers.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected array $response_headers = [];

    /**
     * This is an auxiliary function for getting request headers and parsing the API url.
     *
     * It put the retrieved data into the corresponding properties.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    protected function processInputData(): void
    {
        if (empty($_SERVER['REQUEST_URI'])) {
            return;
        }

        $api_base = str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']);
        $api_request = str_replace($api_base, "", $_SERVER['REQUEST_URI']);

        if (preg_match("~^([^/.?&]+)([/?]?.*)~", $api_request, $matches)) {
            $this->action = $matches[1];
            $this->param_string = urldecode($matches[2]);
        } else {
            $this->action = $_REQUEST["action"] ?? "";
            $this->param_string = urldecode(str_replace(basename($_SERVER['SCRIPT_NAME']), "", $api_request));
        }
    } // processInputData

    /**
     * This is an auxiliary function for sending the response.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    abstract protected function sendResponse(): void;

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
     * @author Oleg Schildt
     */
    protected function addResponseHeader(string $header, string $value): void
    {
        $this->response_headers[$header] = $value;
    } // addResponseHeader

    /**
     * This is an auxiliary function for parsing the incoming data.
     *
     * @return void
     *
     * @throws SmartException
     * It might throw an exception if the content type or data is invalid.
     *
     * @author Oleg Schildt
     */
    abstract protected function parseInput(): void;

    /**
     * This is an auxiliary function for adding error messages, warnings, info messages to the response which might have been collected
     * during handling of the request.
     *
     * @return void
     *
     * @throws SmartException
     * It might throw an exception if the content type or data is invalid.
     *
     * @author Oleg Schildt
     */
    abstract protected function addMessagesToResponse(): void;

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
    protected function preprocessRequest(): void
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
    protected function postprocessRequest(): void
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
     * @throws \Exception
     * It might throw exceptions in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function handleRequest(): void
    {
        try {
            $this->processInputData();

            $this->parseInput();

            if (empty($this->action)) {
                throw new SmartException("The action of the API request cannot be defined!", SmartException::ERR_CODE_SYSTEM_ERROR);
            }

            $robject = new \ReflectionObject($this);
            if (!$robject->hasMethod($this->action)) {
                throw new SmartException(sprintf("No handler is defined for the API request action '%s'!", $this->action), SmartException::ERR_CODE_SYSTEM_ERROR);
            }

            $rmethod = $robject->getMethod($this->action);

            if ($this->action == "handleRequest" || $rmethod->isConstructor() || $rmethod->isDestructor() || !$rmethod->isPublic()) {
                throw new SmartException(sprintf("The name '%s' for the API action is not supported!", $this->action), SmartException::ERR_CODE_SYSTEM_ERROR);
            }

            $this->preprocessRequest();
            $rmethod->invoke($this);
            $this->postprocessRequest();
        } catch (SmartException $ex) {
            messenger()->addError($ex->getMessage(), $ex->getErrorDetails(), $ex->getErrorElement(), $ex->getErrorCode(), $ex->getTechnicalInfo(), $ex->getFile(), $ex->getLine());
        } catch (\Throwable $ex) {
            messenger()->addError("System error occurred!", [], "", SmartException::ERR_CODE_SYSTEM_ERROR, $ex->getMessage(), $ex->getFile(), $ex->getLine());
            singleton(IErrorHandler::class)->handleException($ex, E_USER_ERROR);
        }

        $this->sendResponse();
    } // handleRequest
} // RequestHandler