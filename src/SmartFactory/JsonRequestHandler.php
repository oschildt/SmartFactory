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
 *         throw new SmartException("Wrong login or password!", "wrong_login");
 *     }
 *
 *     $this->response_data["welcome_msg"] = "Welcome, admin!";
 * }
 * ```
 *
 * @author Oleg Schildt
 */
abstract class JsonRequestHandler extends RequestHandler
{
    /**
     * Internal variable for storing the request data.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected array $request_data = [];

    /**
     * Internal variable for storing the response data.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected array $response_data = [];

    /**
     * This is an auxiliary function for sending the response in JSON format.
     *
     * It sends the collected response data and the response headers. The header
     * "Content-type: application/json; charset=UTF-8" ist sent automatically.
     *
     * @return void
     *
     * @throws SmartException
     * It might throw exceptions in the case of any errors.
     *
     * @author Oleg Schildt
     */
    protected function sendResponse(): void
    {
        $this->addResponseHeader("Content-type", "application/json; charset=UTF-8");

        if (!empty($this->response_headers)) {
            if (is_array($this->response_headers)) {
                foreach ($this->response_headers as $header => $value) {
                    header($header . ": " . $value);
                }
            }
        }

        $this->addMessagesToResponse();

        if (empty($this->response_data)) {
            echo json_encode($this->response_data, JSON_FORCE_OBJECT | JSON_PRETTY_PRINT);
        } else {
            echo json_encode($this->response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    } // sendResponse

    /**
     * This is an auxiliary function for parsing the incoming JSON data.
     *
     * It validates the content type 'application/json' and takes the data from RAWDATA.
     *
     * @return void
     *
     * @throws \Exception|SmartException
     * It might throw an exception if the content type oк JSON data is invalid.
     *
     * @author Oleg Schildt
     */
    protected function parseInput(): void
    {
        try {
            $content_type = get_header("Content-Type");
            
            if (empty($content_type)) {
                throw new SmartException("Content type header 'Content-Type' is missing, expected 'application/json; charset=UTF-8'!", SmartException::ERR_CODE_INVALID_CONTENT_TYPE);
            }

            if (!preg_match("/application\/json.*charset=UTF-8/i", $content_type)) {
                throw new SmartException(sprintf("Content type 'application/json; charset=UTF-8' is expected, got '%s'!", $content_type), SmartException::ERR_CODE_INVALID_CONTENT_TYPE);
            }

            $json = trim(file_get_contents("php://input"));

            if (empty($json)) {
                throw new SmartException("The request JSON is empty!", SmartException::ERR_CODE_MISSING_REQUEST_DATA);
            }

            $json = json_decode($json, true);

            if (empty($json) && !is_array($json)) {
                throw new SmartException(json_last_error_msg(), SmartException::ERR_CODE_JSON_PARSE_ERROR);
            }

            $this->request_data = array_merge($this->request_data, $json);
        } catch (SmartException $ex) {
            throw $ex;
        } catch (\Throwable $ex) {
            singleton(IErrorHandler::class)->handleException($ex, E_USER_ERROR);

            throw new SmartException($ex->getMessage(), SmartException::ERR_CODE_JSON_PARSE_ERROR);
        }
    } // parseInput
} // JsonRequestHandler