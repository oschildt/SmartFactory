<?php
/**
 * Implements the XML API request handler and response serializer.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\Interfaces\IErrorHandler;

/**
 * Handles XML API requests using public action methods implemented by a subclass.
 *
 * For example, /api/get_rooms/ invokes get_rooms(), which reads request_xmldoc:
 *
 * ```php
 * public function get_rooms(): void
 * {
 *     $xpath = new \DOMXPath($this->request_xmldoc);
 *     $nodes = $xpath->evaluate("/Request/City");
 *
 *     if ($nodes->length === 0) {
 *         throw new SmartException("City is undefined!", "no_city");
 *     }
 *
 *     $city = $nodes->item(0)->nodeValue;
 *     $response = $this->response_xmldoc->createElement("Response");
 *
 *     $this->response_xmldoc->appendChild($response);
 *
 *     // Add response elements using createTextNode() for user-supplied values.
 * } // get_rooms
 * ```
 *
 * The XML declaration or byte-order mark determines the input encoding. Responses use UTF-8.
 * Fresh documents are created for each request; helpers and message assembly remain protected.
 *
 * @author Oleg Schildt
 */
abstract class XmlRequestHandler extends RequestHandler
{
    /**
     * Stores the parsed request document.
     *
     * @var \DOMDocument
     *
     * @author Oleg Schildt
     */
    protected \DOMDocument $request_xmldoc;

    /**
     * Stores the response document, which is serialized as UTF-8.
     *
     * @var \DOMDocument
     *
     * @author Oleg Schildt
     */
    protected \DOMDocument $response_xmldoc;

    /**
     * Retains the response template configured before this instance handles its first request.
     *
     * @var \DOMDocument|null
     *
     * @author Oleg Schildt
     */
    private ?\DOMDocument $initial_response_xmldoc = null;

    /**
     * Initializes the request and response documents.
     *
     * @author Oleg Schildt
     */
    public function __construct()
    {
        $this->request_xmldoc = new \DOMDocument("1.0", "UTF-8");
        $this->request_xmldoc->formatOutput = true;
        $this->response_xmldoc = new \DOMDocument("1.0", "UTF-8");
        $this->response_xmldoc->formatOutput = true;
    } // __construct

    /**
     * Clears routing state and creates fresh request and response documents.
     *
     * The response is a deep clone of the template present before the first request,
     * preserving document configuration or nodes initialized by a subclass constructor.
     *
     * @return void
     * Removes document content from any earlier request handled by this instance.
     *
     * @author Oleg Schildt
     */
    protected function resetRequest(): void
    {
        parent::resetRequest();

        $this->request_xmldoc = new \DOMDocument("1.0", "UTF-8");
        $this->request_xmldoc->formatOutput = true;
        $this->initial_response_xmldoc ??= clone $this->response_xmldoc;
        $this->response_xmldoc = clone $this->initial_response_xmldoc;
    } // resetRequest

    /**
     * Validates the XML media type and parses the original request bytes.
     *
     * @return void
     * Loads the request document using the encoding declared in its XML data.
     *
     * @throws SmartException
     * If the media type is invalid, the body is empty or malformed, or input access fails.
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
            $this->validateContentType("application/xml");

            $this->parseXml($this->readInput());
        } catch (SmartException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            singleton(IErrorHandler::class)->handleException($exception, E_USER_ERROR);

            throw new SmartException(
                "The request XML could not be read or parsed!",
                SmartException::ERR_CODE_SYSTEM_ERROR,
                technical_info: $exception->getMessage(),
                previous: $exception
            );
        }
    } // parseInput

    /**
     * Loads an XML body without trimming bytes or suppressing parser diagnostics.
     *
     * @param string $xml
     * The original XML bytes, with their declaration or byte-order mark intact.
     *
     * @return void
     * Replaces the request document and restores the previous libxml error-handling mode.
     * Collected libxml diagnostics are cleared after parsing.
     *
     * @throws SmartException
     * If the body is empty or malformed; parser messages, lines and columns are retained as technical details.
     *
     * @throws \Throwable
     * If DOM construction or loading raises an unexpected failure; the previous libxml mode is restored.
     *
     * @author Oleg Schildt
     */
    protected function parseXml(string $xml): void
    {
        if ($xml === "" || strspn($xml, " \t\r\n") === strlen($xml)) {
            throw new SmartException("The request XML is empty!", SmartException::ERR_CODE_MISSING_REQUEST_DATA);
        }

        $previous_mode = libxml_use_internal_errors(true);

        libxml_clear_errors();

        try {
            $document = new \DOMDocument("1.0", "UTF-8");
            $document->formatOutput = true;
            $loaded = $document->loadXML($xml, LIBXML_NONET);

            if (!$loaded) {
                $diagnostics = [];

                foreach (libxml_get_errors() as $error) {
                    $diagnostics[] = sprintf(
                        "%s (line %d, column %d)",
                        trim($error->message),
                        $error->line,
                        $error->column
                    );
                }

                throw new SmartException(
                    "The request XML is invalid!",
                    SmartException::ERR_CODE_XML_PARSE_ERROR,
                    technical_info: implode("\n", $diagnostics)
                );
            }

            $this->request_xmldoc = $document;
        } finally {
            libxml_clear_errors();

            libxml_use_internal_errors($previous_mode);
        }
    } // parseXml

    /**
     * Assembles and serializes the XML response before sending headers.
     *
     * @return void
     * Sends application/xml with UTF-8 encoding. An empty document receives a Response root.
     *
     * @throws \RuntimeException
     * If XML serialization fails.
     *
     * @throws \Throwable
     * If message assembly, DOM construction or header transmission fails.
     *
     * @author Oleg Schildt
     */
    protected function sendResponse(): void
    {
        $this->addMessagesToResponse();

        if ($this->response_xmldoc->documentElement === null) {
            $this->response_xmldoc->appendChild($this->response_xmldoc->createElement("Response"));
        }

        $this->response_xmldoc->encoding = "UTF-8";
        $xml = $this->response_xmldoc->saveXML();

        if ($xml === false) {
            throw new \RuntimeException("The XML response could not be serialized.");
        }

        $this->addResponseHeader("Content-Type", "application/xml; charset=UTF-8");

        $this->sendResponseHeaders();

        echo $xml;
    } // sendResponse

    /**
     * Sends a fixed XML system error without reusing application documents or message hooks.
     *
     * @return void
     * Emits a valid XML error after handleRequest() selects HTTP status 500.
     *
     * @throws \Throwable
     * If fallback headers cannot be added or transmitted.
     *
     * @author Oleg Schildt
     */
    protected function sendErrorResponse(): void
    {
        $this->addResponseHeader("Content-Type", "application/xml; charset=UTF-8");

        $this->sendResponseHeaders();

        echo '<?xml version="1.0" encoding="UTF-8"?>' .
            '<Errors><Error Code="system_error">System error occurred!</Error></Errors>';
    } // sendErrorResponse
} // XmlRequestHandler
