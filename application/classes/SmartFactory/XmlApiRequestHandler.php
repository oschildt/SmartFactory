<?php
/**
 * This file contains the implementation of the interface IApiRequestHandler 
 * in the class XmlApiRequestHandler for handling XML requests.
 *
 * @package System
 *
 * @author Oleg Schildt 
 */

namespace SmartFactory;

use SmartFactory\Interfaces\IApiRequestHandler;

/**
 * Class for handling JSON requests.
 *
 * @author Oleg Schildt 
 */
abstract class XmlApiRequestHandler implements IApiRequestHandler
{
  /**
   * @var array
   * Internal array for storing the handler mappings.
   *
   * @see registerApiRequestHandler
   *
   * @author Oleg Schildt 
   */
  protected static $handler_table = [];
  
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
   * For that reason the method {@see parseXML} is used.
   *
   * @return null
   *
   * @see parseXML
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
   * @param callable $handler
   * The name or definition of the handler function. The signature of 
   * this function is:
   *
   * ```
   * function (IApiRequestHandler $handler, string $api_request, &$xmldoc) : boolean;
   * ```
   *
   * - $handler - the reference to the current handler object.
   *
   * - $api_request - the name of the API request for what it was called.
   *
   * - $xmldoc - the DOM document of the incoming XML data.
   *
   * - The handler function should return true if the request has been successfully 
   * handled, otherwise false.
   *
   * @return boolean
   * Returns true if the registration was successfull, otherwise false.
   *
   * @author Oleg Schildt 
   */
  public function registerApiRequestHandler($api_request, $handler)
  {
    if(empty($api_request)) 
    {
      trigger_error("The API request is undefined (empty)!", E_USER_ERROR);
      return false;
    }
    
    if(!is_callable($handler)) 
    {
      trigger_error("The handler for the API request '$api_request' is not a fucntion!", E_USER_ERROR);
      return false;
    }
    
    if(!empty(self::$handler_table[$api_request])) 
    {
      trigger_error("A handler for the API request '$api_request' was already registered!", E_USER_ERROR);
      return false;
    }
    
    self::$handler_table[$api_request] = new \ReflectionFunction($handler);
    
    return true;
  } // registerApiRequestHandler

  /**
   * Handles an API request call trying to call the handler registered
   * for this API request.
   *
   * @return boolean
   * Returns true if the handling was successfull, otherwise false.
   *
   * @author Oleg Schildt 
   */
  public function handleApiRequest()
  {
    $api_request = "";
    $response_data = [];
    $xmldoc = null;
    
    if(!$this->parseXML($api_request, $xmldoc)) return false;

    if(empty($api_request)) 
    {
      $response_data["errors"] = [
        ["error_code" => "api_request_empy", "error_text" => "Wrong implementation of the method parseXML: the API request is undefined (empty)!"]
      ];
      
      $this->reportErrors($response_data);
      
      return false;
    }

    if(empty($xmldoc)) 
    {
      $response_data["errors"] = [
        ["error_code" => "no_xml_doc", "error_text" => "Wrong implementation of the method parseXML: no valid XML DOMDocument provided!"]
      ];
      
      $this->reportErrors($response_data);
      
      return false;
    }

    if(empty(self::$handler_table[$api_request])) 
    {
      $response_data["errors"] = [
        ["error_code" => "api_request_no_handler", "error_text" => sprintf("No handler is defined for the XML API request '%s'!", $api_request)]
      ];
      
      $this->reportErrors($response_data);
      
      return false;
    }
    
    return self::$handler_table[$api_request]->invoke($this, $api_request, $xmldoc);    
  } // handleApiRequest
} // XmlApiRequestHandler
