<?php
/**
 * Provides the shared request lifecycle for JSON and XML API handlers.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\Interfaces\IErrorHandler;
use SmartFactory\Interfaces\IRequestHandler;
use SmartFactory\Interfaces\IRequestThrottlingManager;

/**
 * Dispatches API actions to public methods and coordinates parsing, cleanup and responses.
 *
 * Public application methods are actions. Constructors, destructors, handleRequest(), init()
 * and throttling dependency accessors are excluded. Keep helpers and lifecycle hooks protected
 * or private. Actions receive no arguments. An optional manager limits requests before parsing.
 *
 * @author Oleg Schildt
 */
abstract class RequestHandler implements IRequestHandler
{
    /**
     * Stores the optional, already initialized throttling service across requests.
     *
     * @var IRequestThrottlingManager|null
     *
     * @author Oleg Schildt
     */
    protected ?IRequestThrottlingManager $throttling_manager = null;

    /**
     * Supplies the current user ID or API-key identifier without resolving it during initialization.
     *
     * @var (\Closure(): (string|int|null))|null
     *
     * @author Oleg Schildt
     */
    protected ?\Closure $user_identificator_getter = null;

    /**
     * Stores the decoded API action name.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $action = "";

    /**
     * Stores the remaining encoded path and query string, including their delimiters.
     *
     * Decode individual path segments with rawurldecode() after splitting them and parse
     * the query separately. Original encoding preserves plus signs and encoded slashes.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $param_string = "";

    /**
     * Stores response header names and values; names are matched case-insensitively.
     *
     * @var array<string, string>
     *
     * @author Oleg Schildt
     */
    protected array $response_headers = [];

    /**
     * Stores the HTTP status; actions and hooks may assign an application-specific status.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $response_status = 200;

    /**
     * Retains headers configured before this instance handles its first request.
     *
     * @var array<string, string>|null
     *
     * @author Oleg Schildt
     */
    private ?array $initial_response_headers = null;

    /**
     * Retains the HTTP status configured before this instance handles its first request.
     *
     * @var int|null
     *
     * @author Oleg Schildt
     */
    private ?int $initial_response_status = null;

    /**
     * Applies optional request-handler dependencies without resetting shared throttling history.
     *
     * @param array{throttling_manager?: IRequestThrottlingManager|null, user_identificator_getter?: (callable(): (string|int|null))|null} $parameters
     * The manager to install, or null to disable throttling. Omission preserves the current manager.
     * The optional zero-argument getter supplies a string, integer or null at request time.
     * Omission preserves the current getter; null removes it. Empty identity results fall back to IP.
     * Additional parameters are reserved for subclasses.
     *
     * @return void
     * Updates the supplied manager; initialization remains optional for unthrottled handlers.
     *
     * @throws \InvalidArgumentException
     * If throttling_manager is neither null nor an IRequestThrottlingManager implementation,
     * or user_identificator_getter is neither callable nor null. Validation precedes mutation.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void
    {
        $manager = array_key_exists("throttling_manager", $parameters)
            ? $parameters["throttling_manager"]
            : $this->throttling_manager;
        $getter = array_key_exists("user_identificator_getter", $parameters)
            ? $parameters["user_identificator_getter"]
            : $this->user_identificator_getter;

        if ($manager !== null && !$manager instanceof IRequestThrottlingManager) {
            throw new \InvalidArgumentException("The throttling_manager must implement IRequestThrottlingManager or be null.");
        }

        if ($getter !== null && !is_callable($getter)) {
            throw new \InvalidArgumentException("The user identifier getter must be callable or null.");
        }

        $getter = $getter === null ? null : \Closure::fromCallable($getter);

        $this->setRequestThrottlingManager($manager);

        $this->user_identificator_getter = $getter;
    } // init

    /**
     * Assigns or removes the throttling service without changing its policy or request history.
     *
     * @param IRequestThrottlingManager|null $throttling_manager
     * The initialized service, or null to disable throttling.
     *
     * @return void
     * Stores the dependency for subsequent requests handled by this instance.
     *
     * @author Oleg Schildt
     */
    public function setRequestThrottlingManager(?IRequestThrottlingManager $throttling_manager): void
    {
        $this->throttling_manager = $throttling_manager;
    } // setRequestThrottlingManager

    /**
     * Retrieves the throttling service currently assigned to the handler.
     *
     * @return IRequestThrottlingManager|null
     * Returns the initialized service, or null when throttling is disabled.
     *
     * @author Oleg Schildt
     */
    public function getRequestThrottlingManager(): ?IRequestThrottlingManager
    {
        return $this->throttling_manager;
    } // getRequestThrottlingManager

    /**
     * Identifies the caller whose requests share a throttling budget.
     *
     * Evaluates user_identificator_getter on each request, using its nonempty string or integer
     * result. Null or an empty string falls back to REMOTE_ADDR. Identity sources are prefixed
     * so a user identifier cannot collide with an IP budget. The IP is normalized; forwarded
     * headers are ignored. This hook runs before body parsing and preprocessing and can be overridden.
     *
     * @return string
     * Returns a user:-prefixed identifier, an ip:-prefixed address, or ip:unknown if no valid IP is available.
     * Integer zero and the string "0" are valid user identifiers and share the same budget.
     *
     * @throws \UnexpectedValueException
     * If the getter returns a value other than string, integer or null.
     *
     * @throws \Throwable
     * If the getter throws an exception or error; it is reported by handleRequest() as a system failure.
     *
     * @author Oleg Schildt
     */
    protected function getRequestThrottlingKey(): string
    {
        if ($this->user_identificator_getter !== null) {
            $identifier = ($this->user_identificator_getter)();

            if (!is_string($identifier) && !is_int($identifier) && $identifier !== null) {
                throw new \UnexpectedValueException("The user identifier getter must return a string, integer or null.");
            }

            if ($identifier !== null && $identifier !== "") {
                return "user:" . (string) $identifier;
            }
        }

        $address = $_SERVER["REMOTE_ADDR"] ?? "";
        $packed = is_string($address) && filter_var($address, FILTER_VALIDATE_IP) !== false
            ? inet_pton($address)
            : false;

        return $packed === false ? "ip:unknown" : "ip:" . inet_ntop($packed);
    } // getRequestThrottlingKey

    /**
     * Checks and records admission before parsing or invoking application code.
     *
     * @return void
     * Continues when no manager is configured or admission succeeds.
     *
     * @throws SmartException
     * If a limit is reached; Retry-After and Cache-Control: no-store are queued, and the error carries the retry delay.
     *
     * @throws \UnexpectedValueException
     * If the manager violates its contract by returning a negative retry delay.
     *
     * @throws \Throwable
     * If deriving the key, accessing shared storage or queuing response headers fails.
     *
     * @author Oleg Schildt
     */
    protected function enforceRequestThrottling(): void
    {
        if ($this->throttling_manager === null) {
            return;
        }

        $retry_after = $this->throttling_manager->registerRequest($this->getRequestThrottlingKey());

        if ($retry_after < 0) {
            throw new \UnexpectedValueException("The throttling manager returned a negative retry delay.");
        }

        if ($retry_after > 0) {
            $this->addResponseHeader("Retry-After", (string) $retry_after);

            $this->addResponseHeader("Cache-Control", "no-store");

            throw new SmartException(
                "Too many API requests. Please try again later.",
                SmartException::ERR_CODE_TOO_MANY_REQUESTS,
                error_details: ["retry_after" => $retry_after]
            );
        }
    } // enforceRequestThrottling

    /**
     * Restores common request state before processing a request.
     *
     * Subclasses should call this implementation when resetting their payload state.
     * Messages already queued by the application are retained until response assembly consumes them.
     *
     * @return void
     * Clears routing data and restores initially configured headers and status (200 by default).
     *
     * @author Oleg Schildt
     */
    protected function resetRequest(): void
    {
        $this->action = "";
        $this->param_string = "";
        $this->initial_response_headers ??= $this->response_headers;
        $this->initial_response_status ??= $this->response_status;
        $this->response_headers = $this->initial_response_headers;
        $this->response_status = $this->initial_response_status;
    } // resetRequest

    /**
     * Extracts the action without changing encoded path parameters or query values.
     *
     * Supports rewritten paths and SCRIPT_NAME followed by an action path. Without a path
     * action, a string action from $_REQUEST is used, including when REQUEST_URI is absent.
     *
     * @return void
     * Updates the decoded action and the encoded parameter string.
     *
     * @throws SmartException
     * If the request URI cannot be parsed or the fallback action is not a string.
     *
     * @author Oleg Schildt
     */
    protected function processInputData(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? "";
        $script = $_SERVER['SCRIPT_NAME'] ?? "";
        $parts = is_string($uri) ? parse_url($uri) : false;

        if ($parts === false) {
            throw new SmartException("The request URI is invalid!", SmartException::ERR_CODE_INVALID_REQUEST_DATA);
        }

        $path = $parts['path'] ?? "";
        $query = isset($parts['query']) ? "?" . $parts['query'] : "";
        $script = is_string($script) ? $script : "";
        $base = $script === "" ? "/" : substr($script, 0, (int) strrpos($script, "/") + 1);

        if (str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        } else {
            $path = ltrim($path, "/");
        }

        $script_name = basename($script);

        if (
            $script_name !== ""
            && ($path === $script_name || str_starts_with($path, $script_name . "/"))
        ) {
            $path = ltrim(substr($path, strlen($script_name)), "/");
        }

        if ($path !== "") {
            $segments = explode("/", $path, 2);
            $this->action = rawurldecode($segments[0]);
            $this->param_string = (isset($segments[1]) ? "/" . $segments[1] : "") . $query;

            return;
        }

        $action = $_REQUEST['action'] ?? "";

        if (!is_string($action)) {
            throw new SmartException("The API action must be a string!", SmartException::ERR_CODE_INVALID_REQUEST_DATA);
        }

        $this->action = $action;
        $this->param_string = $query;
    } // processInputData

    /**
     * Reads the unmodified HTTP request body.
     *
     * @return string
     * Returns the original bytes, including whitespace and zero bytes.
     *
     * @throws \RuntimeException
     * If the input stream cannot be read.
     *
     * @author Oleg Schildt
     */
    protected function readInput(): string
    {
        $input = file_get_contents("php://input");

        if ($input === false) {
            throw new \RuntimeException("The request body could not be read.");
        }

        return $input;
    } // readInput

    /**
     * Validates complete media type syntax and an optional required charset.
     *
     * @param string $expected_type
     * The required type/subtype, compared without regard to case.
     *
     * @param string|null $charset
     * The permitted charset when explicitly supplied; null leaves encoding selection to the parser.
     * A missing charset is accepted in either case.
     *
     * @return void
     * Accepts a matching media type with syntactically valid token or quoted parameters.
     *
     * @throws SmartException
     * If Content-Type is missing, malformed, has another media type or an unsupported charset.
     *
     * @author Oleg Schildt
     */
    protected function validateContentType(string $expected_type, ?string $charset = null): void
    {
        $content_type = get_header("Content-Type");
        $token = "[!#$%&'*+.^_\\x60|~0-9A-Za-z-]+";
        $quoted = '"(?:[^"\\\\\r\n]|\\\\[^\r\n])*"';
        $parameter = '[ \t]*;[ \t]*(' . $token . ')[ \t]*=[ \t]*(' . $token . '|' . $quoted . ')';
        $pattern = '@^[ \t]*(' . $token . '/' . $token . ')((?:' . $parameter . ')*)[ \t]*$@D';

        if (!preg_match($pattern, $content_type, $matches) || strcasecmp($matches[1], $expected_type) !== 0) {
            throw new SmartException(
                sprintf("Content type '%s' is expected!", $expected_type),
                SmartException::ERR_CODE_INVALID_CONTENT_TYPE
            );
        }

        if ($charset === null) {
            return;
        }

        preg_match_all('@' . $parameter . '@', $matches[2], $parameters, PREG_SET_ORDER);

        foreach ($parameters as $parameter_match) {
            if (strcasecmp($parameter_match[1], "charset") !== 0) {
                continue;
            }

            $value = $parameter_match[2];

            if (str_starts_with($value, '"')) {
                $value = preg_replace('@\\\\(.)@s', '$1', substr($value, 1, -1));
            }

            if (strcasecmp($value, $charset) !== 0) {
                throw new SmartException(
                    sprintf("The request charset must be '%s'!", $charset),
                    SmartException::ERR_CODE_INVALID_CONTENT_TYPE
                );
            }
        }
    } // validateContentType

    /**
     * Adds or replaces a response header using a case-insensitive name.
     *
     * @param string $header
     * A nonempty HTTP field name consisting of token characters.
     *
     * @param string $value
     * The field value, without control characters other than horizontal tabs.
     *
     * @return void
     * Stores the header for transmission after successful response serialization.
     *
     * @throws \InvalidArgumentException
     * If the name is invalid or the value contains a prohibited control character.
     *
     * @author Oleg Schildt
     */
    protected function addResponseHeader(string $header, string $value): void
    {
        if (
            !preg_match("@^[!#$%&'*+.^_\\x60|~0-9A-Za-z-]+$@D", $header)
            || preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value)
        ) {
            throw new \InvalidArgumentException("The response header name or value is invalid.");
        }

        foreach (array_keys($this->response_headers) as $name) {
            if (strcasecmp($name, $header) === 0) {
                unset($this->response_headers[$name]);
            }
        }

        $this->response_headers[$header] = $value;
    } // addResponseHeader

    /**
     * Sends the response status and all queued response headers.
     *
     * @return void
     * Emits each header as a name/value pair before the response body.
     *
     * @throws \RuntimeException
     * If earlier output has already committed the HTTP headers.
     *
     * @throws \InvalidArgumentException
     * If the selected status is outside the HTTP status range of 100 through 599.
     *
     * @author Oleg Schildt
     */
    protected function sendResponseHeaders(): void
    {
        if ($this->response_status < 100 || $this->response_status > 599) {
            throw new \InvalidArgumentException("The response HTTP status must be between 100 and 599.");
        }

        if (headers_sent()) {
            throw new \RuntimeException("The response headers have already been sent.");
        }

        http_response_code($this->response_status);

        foreach ($this->response_headers as $header => $value) {
            header($header . ": " . $value);
        }
    } // sendResponseHeaders

    /**
     * Serializes the response before emitting its status, headers and body.
     *
     * @return void
     * Sends the response in the subclass's format.
     *
     * @throws \Throwable
     * If message assembly, serialization or header transmission fails.
     *
     * @author Oleg Schildt
     */
    abstract protected function sendResponse(): void;

    /**
     * Sends a minimal fallback when normal response assembly fails.
     *
     * JSON and XML handlers override this method to preserve their response format.
     * Fallbacks must not invoke message hooks or serialize application values again.
     *
     * @return void
     * Sends a neutral system error; handleRequest() selects HTTP status 500 beforehand.
     *
     * @throws \Throwable
     * If adding or transmitting fallback headers fails, for example after earlier output.
     *
     * @author Oleg Schildt
     */
    protected function sendErrorResponse(): void
    {
        $this->addResponseHeader("Content-Type", "text/plain; charset=UTF-8");

        $this->sendResponseHeaders();

        echo "System error occurred!";
    } // sendErrorResponse

    /**
     * Validates the request content type and parses its body.
     *
     * @return void
     * Populates the subclass's request properties.
     *
     * @throws SmartException
     * If the content type, body or decoded data is invalid, or input access fails.
     *
     * @throws \Throwable
     * If reporting an unexpected parsing failure itself fails.
     *
     * @author Oleg Schildt
     */
    abstract protected function parseInput(): void;

    /**
     * Adds collected errors, warnings and other messages to the prepared response.
     *
     * Implementations should consume messages and clear partial success data when appropriate.
     *
     * @return void
     * Updates the response payload without sending headers or output.
     *
     * @throws \Throwable
     * If the application's message manager or response assembly fails.
     *
     * @author Oleg Schildt
     */
    abstract protected function addMessagesToResponse(): void;

    /**
     * Runs application checks before invoking an API action.
     *
     * Override to validate access tokens, check maintenance mode or allocate resources.
     * Once this hook starts, postprocessRequest() runs even if this hook or the action throws.
     *
     * @return void
     * Permits action execution when no exception is thrown.
     *
     * @throws \Throwable
     * If an application check rejects the request or resource initialization fails.
     *
     * @author Oleg Schildt
     */
    protected function preprocessRequest(): void
    {
    } // preprocessRequest

    /**
     * Finalizes processing after preprocessing starts, including failed requests.
     *
     * Override for shared response additions or cleanup. Check resources before releasing
     * them because preprocessing may have failed before initialization completed.
     * Parsing and routing failures do not call this hook. Its failures are reported separately
     * so they cannot replace an earlier action failure.
     *
     * @return void
     * Finishes application cleanup before response serialization.
     *
     * @throws \Throwable
     * If application finalization or cleanup fails.
     *
     * @author Oleg Schildt
     */
    protected function postprocessRequest(): void
    {
    } // postprocessRequest

    /**
     * Reports a failure and selects its HTTP status without exposing system diagnostics.
     *
     * @param \Throwable $exception
     * A structured client error, an error collection or an unexpected system failure.
     *
     * @return void
     * Queues errors and selects 400 for validation, 404 for missing actions or resources,
     * 415 for unsupported content types, 429 for throttling, and 500 for system or configuration failures.
     * An existing higher error status is retained.
     *
     * @throws \Throwable
     * If resolving services, queuing messages or logging fails.
     *
     * @author Oleg Schildt
     */
    protected function reportFailure(\Throwable $exception): void
    {
        if ($exception instanceof SmartException || $exception instanceof SmartExceptionCollection) {
            messenger()->addErrorFromSmartException($exception);

            $codes = $exception instanceof SmartException
                ? [$exception->getErrorCode()]
                : array_column($exception->getErrors(), "code");
            $status = 400;

            foreach ($codes as $code) {
                $status = max($status, match ($code) {
                    SmartException::ERR_CODE_SYSTEM_ERROR, SmartException::ERR_CODE_CONFIG_ERROR => 500,
                    SmartException::ERR_CODE_INVALID_CONTENT_TYPE => 415,
                    SmartException::ERR_CODE_TOO_MANY_REQUESTS => 429,
                    SmartException::ERR_CODE_NOT_FOUND => 404,
                    default => 400,
                });
            }

            $this->response_status = max($this->response_status, $status);

            return;
        }

        $this->response_status = 500;

        messenger()->addError(
            "System error occurred!", [], "", SmartException::ERR_CODE_SYSTEM_ERROR,
            $exception->getMessage(), $exception->getFile(), $exception->getLine()
        );

        singleton(IErrorHandler::class)->handleException($exception, E_USER_ERROR);
    } // reportFailure

    /**
     * Handles one request using public methods as actions, without a separate action registry.
     *
     * Resets request state, enforces optional throttling, parses input and invokes an action.
     * Canonical method names exclude lifecycle entry points and dependency accessors from dispatch.
     * Throttled requests receive 429 and Retry-After before body parsing or application hooks.
     * Structured exceptions become client errors; unexpected failures are logged.
     * Cleanup runs after preprocessing starts, even on failure. Response assembly failures
     * produce a minimal response in the handler's format with HTTP status 500.
     * Errors added directly to the message manager select status 400 unless an action,
     * a hook or exception reporting has already selected an error status.
     *
     * @return void
     * Sends either the prepared response or a neutral system-error fallback.
     *
     * @throws \Throwable
     * If failure reporting or fallback transmission fails; committed output cannot be repaired.
     *
     * @author Oleg Schildt
     */
    public function handleRequest(): void
    {
        $preprocessing_started = false;

        try {
            $this->resetRequest();

            $this->enforceRequestThrottling();

            $this->processInputData();

            $this->parseInput();

            if ($this->action === "") {
                throw new SmartException("The API action is missing!", SmartException::ERR_CODE_INVALID_REQUEST_DATA);
            }

            $object = new \ReflectionObject($this);

            if (!$object->hasMethod($this->action)) {
                throw new SmartException("The API action was not found!", SmartException::ERR_CODE_NOT_FOUND);
            }

            $method = $object->getMethod($this->action);

            if (
                in_array(strtolower($method->getName()), [
                    "handlerequest", "init", "setrequestthrottlingmanager", "getrequestthrottlingmanager"
                ], true)
                || $method->isConstructor()
                || $method->isDestructor()
                || !$method->isPublic()
            ) {
                throw new SmartException("The API action is not supported!", SmartException::ERR_CODE_NOT_FOUND);
            }

            $preprocessing_started = true;

            $this->preprocessRequest();

            $method->invoke($this);
        } catch (\Throwable $exception) {
            $this->reportFailure($exception);
        } finally {
            if ($preprocessing_started) {
                try {
                    $this->postprocessRequest();
                } catch (\Throwable $exception) {
                    $this->reportFailure($exception);
                }
            }
        }

        if (messenger()->hasErrors() && $this->response_status < 400) {
            $this->response_status = 400;
        }

        try {
            $this->sendResponse();
        } catch (\Throwable $exception) {
            $this->response_status = 500;

            singleton(IErrorHandler::class)->handleException($exception, E_USER_ERROR);

            // A minimal fallback replaces all queued messages, including those not consumed by a failed hook.
            messenger()->clearAll();

            $this->sendErrorResponse();
        }
    } // handleRequest
} // RequestHandler
