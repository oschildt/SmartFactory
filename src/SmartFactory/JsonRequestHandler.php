<?php
/**
 * Implements the JSON API request handler and response serializer.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\Interfaces\IErrorHandler;

/**
 * Handles JSON API requests using public action methods implemented by a subclass.
 *
 * For example, /api/login/ invokes login(), which reads request_data and writes response_data:
 *
 * ```php
 * public function login(): void
 * {
 *     if (empty($this->request_data["login"]) || empty($this->request_data["password"]) ||
 *         $this->request_data["login"] != "admin" || $this->request_data["password"] != "qwerty") {
 *         throw new SmartException("Wrong login or password!", "wrong_login");
 *     }
 *
 *     $this->response_data["welcome_msg"] = "Welcome, admin!";
 * } // login
 * ```
 *
 * Requests must contain a JSON object or array encoded as UTF-8. Content-Type parameters
 * are optional; an explicit charset must be UTF-8. Large integers are retained as strings.
 * Numeric keys are preserved when request values replace predefined request defaults.
 *
 * @author Oleg Schildt
 */
abstract class JsonRequestHandler extends RequestHandler
{
    /**
     * Stores decoded request values, including predefined defaults.
     *
     * @var array<array-key, mixed>
     *
     * @author Oleg Schildt
     */
    protected array $request_data = [];

    /**
     * Stores response values; an empty response is serialized as an object.
     *
     * @var array<array-key, mixed>
     *
     * @author Oleg Schildt
     */
    protected array $response_data = [];

    /**
     * Retains request defaults captured before this instance handles its first request.
     *
     * @var array<array-key, mixed>|null
     *
     * @author Oleg Schildt
     */
    private ?array $initial_request_data = null;

    /**
     * Retains response defaults captured before this instance handles its first request.
     *
     * @var array<array-key, mixed>|null
     *
     * @author Oleg Schildt
     */
    private ?array $initial_response_data = null;

    /**
     * Restores initial payload defaults and clears common per-request state.
     *
     * Defaults assigned by subclass property declarations or constructors survive reuse.
     * Defaults containing objects retain their object identity; mutable request objects should
     * be initialized in a lifecycle hook rather than stored as reusable defaults.
     *
     * @return void
     * Removes values added by earlier requests while retaining initial defaults.
     *
     * @author Oleg Schildt
     */
    protected function resetRequest(): void
    {
        parent::resetRequest();

        $this->initial_request_data ??= $this->request_data;
        $this->initial_response_data ??= $this->response_data;
        $this->request_data = $this->initial_request_data;
        $this->response_data = $this->initial_response_data;
    } // resetRequest

    /**
     * Assembles and serializes the JSON response before sending any headers.
     *
     * @return void
     * Sends application/json with UTF-8 encoding and the selected HTTP status.
     *
     * @throws \JsonException
     * If response values contain invalid UTF-8, recursion, unsupported types or nonfinite numbers.
     *
     * @throws \Throwable
     * If message assembly, custom JSON serialization or header transmission fails.
     *
     * @author Oleg Schildt
     */
    protected function sendResponse(): void
    {
        $this->addMessagesToResponse();

        $response = $this->response_data === [] ? (object) [] : $this->response_data;
        $json = json_encode(
            $response,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $this->addResponseHeader("Content-Type", "application/json; charset=UTF-8");

        $this->sendResponseHeaders();

        echo $json;
    } // sendResponse

    /**
     * Sends a fixed JSON system error without reusing application response values or message hooks.
     *
     * @return void
     * Emits a valid JSON error after handleRequest() selects HTTP status 500.
     *
     * @throws \Throwable
     * If fallback headers cannot be added or transmitted.
     *
     * @author Oleg Schildt
     */
    protected function sendErrorResponse(): void
    {
        $this->addResponseHeader("Content-Type", "application/json; charset=UTF-8");

        $this->sendResponseHeaders();

        echo '{"result":"error","errors":[{"message":"System error occurred!","code":"system_error"}]}';
    } // sendErrorResponse

    /**
     * Validates the JSON content type and parses the unmodified request body.
     *
     * @return void
     * Replaces matching request defaults with decoded JSON values.
     *
     * @throws SmartException
     * If the content type is invalid, the body is empty, malformed or scalar, or input access fails.
     * Unexpected failures use a neutral system-error message and retain technical details and their cause.
     *
     * @throws \Throwable
     * If logging an unexpected parsing failure fails.
     *
     * @author Oleg Schildt
     */
    protected function parseInput(): void
    {
        try {
            $this->validateContentType("application/json", "UTF-8");

            $this->parseJson($this->readInput());
        } catch (SmartException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            singleton(IErrorHandler::class)->handleException($exception, E_USER_ERROR);

            throw new SmartException(
                "The request JSON could not be read or parsed!",
                SmartException::ERR_CODE_SYSTEM_ERROR,
                technical_info: $exception->getMessage(),
                previous: $exception
            );
        }
    } // parseInput

    /**
     * Decodes a JSON object or array without modifying the supplied body.
     *
     * @param string $json
     * Raw UTF-8 JSON; only JSON whitespace is recognized when checking for an empty body.
     *
     * @return void
     * Replaces matching request defaults while preserving numeric keys and large integers.
     * Top-level objects and arrays are both represented as PHP arrays.
     *
     * @throws SmartException
     * If the body is empty, invalid JSON or a scalar rather than an object or array.
     * Syntax failures preserve the original JsonException and its technical diagnostic.
     *
     * @author Oleg Schildt
     */
    protected function parseJson(string $json): void
    {
        if ($json === "" || strspn($json, " \t\r\n") === strlen($json)) {
            throw new SmartException("The request JSON is empty!", SmartException::ERR_CODE_MISSING_REQUEST_DATA);
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException $exception) {
            throw new SmartException(
                "The request JSON is invalid!",
                SmartException::ERR_CODE_JSON_PARSE_ERROR,
                technical_info: $exception->getMessage(),
                previous: $exception
            );
        }

        if (!is_array($data)) {
            throw new SmartException(
                "The request JSON must contain an object or array!",
                SmartException::ERR_CODE_INVALID_REQUEST_DATA
            );
        }

        $this->request_data = array_replace($this->request_data, $data);
    } // parseJson
} // JsonRequestHandler
