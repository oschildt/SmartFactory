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

use \SmartFactory\Interfaces\IErrorHandler;

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
 *        throw new SmartException("City is undefined!", "no_city");
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
abstract class XmlRequestHandler extends RequestHandler
{
    /**
     * Internal variable for storing the request XML document.
     *
     * @var \DOMDocument
     *
     * @author Oleg Schildt
     */
    protected \DOMDocument $request_xmldoc;

    /**
     * Internal variable for storing the response XML document.
     *
     * @var \DOMDocument
     *
     * @author Oleg Schildt
     */
    protected \DOMDocument $response_xmldoc;

    /**
     * Constructor.
     *
     * It initializes the XML document variables.
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
     * This is an auxiliary function for parsing the incoming XML data.
     *
     * It validates the content type 'application/xml' and takes the data from RAWDATA.
     *
     * @throws SmartException|\Exception
     * It might throw an exception if the content type oк XML data is invalid.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    protected function parseInput(): void
    {
        try {
            $content_type = get_header("Content-Type");

            if (empty($content_type)) {
                throw new SmartException("Content type header 'Content-Type' is missing, expected 'application/xml; charset=UTF-8'!", SmartException::ERR_CODE_INVALID_CONTENT_TYPE);
            }

            if (!preg_match("/application\/xml/", $content_type)) {
                throw new SmartException(sprintf("Content type 'application/xml; charset=UTF-8' is expected, got '%s'!", $content_type), SmartException::ERR_CODE_INVALID_CONTENT_TYPE);
            }

            $xmldata = trim(file_get_contents("php://input"));

            if (empty($xmldata)) {
                throw new SmartException("The request XML is empty!", SmartException::ERR_CODE_MISSING_REQUEST_DATA);
            }

            $this->request_xmldoc = new \DOMDocument("1.0", "UTF-8");
            $this->request_xmldoc->formatOutput = true;
            if (!@$this->request_xmldoc->loadXML($xmldata)) {
                throw new SmartException("Error by XML parsing!", SmartException::ERR_CODE_XML_PARSE_ERROR);
            }
        } catch (SmartException $ex) {
            throw $ex;
        } catch (\Throwable $ex) {
            singleton(IErrorHandler::class)->handleException($ex, E_USER_ERROR);

            throw new SmartException($ex->getMessage(), SmartException::ERR_CODE_XML_PARSE_ERROR);
        }
    } // parseInput

    /**
     * This is an auxiliary function for sending the response in XML format.
     *
     * It sends the prepared response XML document and the response headers. The header
     * "Content-type: application/xml" ist sent automatically.
     *
     * @return void
     *
     * @throws SmartException
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    protected function sendResponse(): void
    {
        $this->addResponseHeader("Content-type", "application/xml; charset=UTF-8");

        header('Content-type: application/xml');

        if (!empty($this->response_headers)) {
            if (is_array($this->response_headers)) {
                foreach ($this->response_headers as $header) {
                    header($header);
                }
            }
        }

        $this->addMessagesToResponse();

        echo $this->response_xmldoc->saveXML();
    } // sendResponse
} // XmlRequestHandler