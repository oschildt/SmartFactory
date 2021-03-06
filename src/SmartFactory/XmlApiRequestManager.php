<?php
/**
 * This file contains the class XmlApiRequestManager for handling XML requests.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\Interfaces\IXmlApiRequestHandler;

/**
 * Class for for handling XML requests.
 *
 * @see IXmlApiRequestHandler
 *
 * @author Oleg Schildt
 */
abstract class XmlApiRequestManager
{
    /**
     * Internal array for storing the handler mappings.
     *
     * @var array
     *
     * @see registerApiRequestHandler()
     *
     * @author Oleg Schildt
     */
    protected $handler_table = [];
    
    /**
     * Parses the incoming XML data of the API request.
     *
     * This method should be implemented by the user.
     * This method is called every time a new API request comes. The user
     * should parse the incoming data. The result of parsing
     * should be the detected API request name and the DOM document.
     *
     * @param string $api_request
     * The detected DOM document.
     *
     * @param \DOMDocument $xmldoc
     * The resulting DOM document.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    protected abstract function parseXML(&$api_request, &$xmldoc);
    
    /**
     * Reports an errors in the response in XML format.
     *
     * This method should be implemented by the user. The user
     * should create error response in the desired format.
     *
     * @param array $response_data
     * The array with response data that contains error details.
     *
     * @param array $headers
     * The array of additional headers if necessary. The header
     * 'Content-type: text/xml; charset=UTF-8' should be send automatically
     * in the method and should not be passed over this paramter.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public abstract function reportErrors($response_data, $headers = []);
    
    /**
     * This is an auxiliary function for sending the response in XML
     * format.
     *
     * @param \DOMDocument $outxmldoc
     * The DOM document.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function sendXMLResponse($outxmldoc)
    {
        header('Content-type: text/xml; charset=UTF-8');
        
        echo $outxmldoc->saveXML();
    } // sendXMLResponse
    
    /**
     * Not implemented in this class since the API call name is defined
     * not based on the URL but on a tag name in the incoming XML data.
     *
     * For that reason the method {@see XmlApiRequestManager::parseXML()} is used.
     *
     * @return null
     *
     * @see parseXML()
     *
     * @author Oleg Schildt
     */
    public function getApiRequest()
    {
        return null;
    } // getApiRequest
    
    /**
     * Registers a handler function for an API request call.
     *
     * @param string $api_request
     * The target API request.
     *
     * @param string $handler_class_name
     * The name of the class for handling this API request.
     *
     * Important! It should be a name of the class, mneither the class instance
     * nor the class object. It is done to prevent situation that a wrong registration
     * of a handler breaks the handling of all requests.
     *
     * The class instantiating and class loading occurs only if this API request
     * comes.
     *
     * @return boolean
     * Returns true if the registration was successfull, otherwise false.
     *
     * @throws \Exception
     * It might throw the following exceptions in the case of any errors:
     *
     * - if the request name is not specified.
     * - if the request already has a handler.
     *
     * @author Oleg Schildt
     */
    public function registerApiRequestHandler($api_request, $handler_class_name)
    {
        if (empty($api_request)) {
            throw new \Exception("The API request is undefined (empty)!");
        }
        
        if (!empty($this->handler_table[$api_request])) {
            throw new \Exception("The API request '$api_request' has already the handler '" . $this->handler_table[$api_request] . "'!");
        }
        
        $this->handler_table[$api_request] = $handler_class_name;
        
        return true;
    } // registerApiRequestHandler
    
    /**
     * Handles an API request call trying to call the handler registered
     * for this API request.
     *
     * @return boolean
     * Returns true if the handling was successfull, otherwise false.
     *
     * @throws \Exception
     * It might throw the following exceptions in the case of any errors:
     *
     * - if the creation of the handler fails.
     *
     * @uses IXmlApiRequestHandler
     *
     * @author Oleg Schildt
     */
    public function handleApiRequest()
    {
        $api_request = "";
        $response_data = [];
        $xmldoc = null;
        
        if (!$this->parseXML($api_request, $xmldoc)) {
            return false;
        }
        
        if (empty($api_request)) {
            $response_data["errors"] = [
                [
                    "error_code" => "system_error",
                    "error_type" => "programming_error",
                    "error_text" => "Wrong implementation of the method parseXML: the API request is undefined (empty)!"
                ]
            ];
            
            $this->reportErrors($response_data);
            
            return false;
        }
        
        if (empty($xmldoc)) {
            $response_data["errors"] = [
                [
                    "error_code" => "system_error",
                    "error_type" => "programming_error",
                    "error_text" => "Wrong implementation of the method parseXML: no valid XML DOMDocument provided!"
                ]
            ];
            
            $this->reportErrors($response_data);
            
            return false;
        }
        
        if (empty($this->handler_table[$api_request])) {
            $response_data["errors"] = [
                [
                    "error_code" => "system_error",
                    "error_type" => "programming_error",
                    "error_text" => sprintf("No handler is defined for the XML API request '%s'!", $api_request)
                ]
            ];
            
            $this->reportErrors($response_data);
            
            return false;
        }
        
        if (!class_exists($this->handler_table[$api_request])) {
            $response_data["result"] = "error";
            
            $response_data["errors"] = [
                [
                    "error_code" => "system_error",
                    "error_type" => "programming_error",
                    "error_text" => sprintf("The handler class '%s' does not exist!", $this->handler_table[$api_request])
                ]
            ];
            
            $this->reportErrors($response_data);
            
            return false;
        }
        
        $handler_class = new \ReflectionClass($this->handler_table[$api_request]);
        
        if (!$handler_class->isSubclassOf("SmartFactory\Interfaces\IXmlApiRequestHandler")) {
            $response_data["result"] = "error";
            
            $response_data["errors"] = [
                [
                    "error_code" => "system_error",
                    "error_type" => "programming_error",
                    "error_text" => sprintf("The handler class '%s' does not implement the interface '%s'!", $this->handler_table[$api_request], "IXmlApiRequestHandler")
                ]
            ];
            
            $this->reportErrors($response_data);
            
            return false;
        }
        
        $handler = $handler_class->newInstance();
        
        return $handler->handle($this, $api_request, $xmldoc);
    } // handleApiRequest
} // XmlApiRequestManager