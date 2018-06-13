<?php
/**
 * This file contains the implementation of the interface IApiRequestHandler 
 * in the class JsonApiRequestHandler for handling JSON requests.
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
class JsonApiRequestHandler implements IApiRequestHandler
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
  protected static $handler_table = [];

  /**
   * This is an auxiliary function for sending the response in JSON
   * format.
   *
   * @param array $response_data
   * The array with response data.
   *
   * @return void
   *
   * @author Oleg Schildt 
   */
  public function sendJsonResponse(&$response_data)
  {
    header('Content-type: application/json');
    
    echo array_to_json($response_data);
  } // sendJsonResponse

  /**
   * This is an auxiliary function for reporting errors in JSON
   * format and with additional headers if necessary.
   *
   * @param array $response_data
   * The array with response data that contains error details.
   *
   * @param array $headers
   * The array of additional headers if necessary. The header
   * 'Content-type: application/json' is sent automatically
   * and need not be listed explicitly.
   *
   * @return void
   *
   * @author Oleg Schildt 
   */
  public function reportErrors(&$response_data, $headers = [])
  {
    header('Content-type: application/json');
    
    if(!empty($headers))
    {
      if(is_array($headers))
      {
        foreach($headers as $header) header($header);
      }
    }    
    
    echo array_to_json($response_data);
  } // reportErrors
  
  /**
   * Defines the current API request name.
   *
   * @return string
   * Returns the current API request.
   *
   * @author Oleg Schildt 
   */
  public function getApiRequest()
  {
    if(empty($_SERVER['REQUEST_URI'])) return "";

    $api_request = $_SERVER['REQUEST_URI'];
    
    if(!empty($_SERVER['QUERY_STRING'])) $api_request = str_replace($_SERVER['QUERY_STRING'], "", $api_request);
    
    $api_request = rtrim($api_request, "/?");
    
    $api_base = str_replace(basename($_SERVER['SCRIPT_NAME']), "", $_SERVER['SCRIPT_NAME']);

    $api_base = rtrim($api_base, "/");
    
    //echo $api_request . "<br>";
    //echo $api_base . "<br>";
    
    return trim(str_replace($api_base, "", $api_request), "/");
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
   * ```php
   * function (IApiRequestHandler $handler, string $api_request) : boolean;
   * ```
   *
   * - $handler - the reference to the current handler object.
   *
   * - $api_request - the name of the API request for what it was called.
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
    $api_request = $this->getApiRequest();
    
    $response_data = [];
    
    if(empty($api_request))
    {
      $response_data["result"] = "error";
      
      $response_data["errors"] = [
        ["error_code" => "api_request_empy", "error_text" => "The API request is undefined (empty)!"]
      ];
      
      $this->reportErrors($response_data);
      
      return false;
    }    
    
    if(empty(self::$handler_table[$api_request])) 
    {
      $response_data["result"] = "error";
      
      $response_data["errors"] = [
        ["error_code" => "api_request_no_handler", "error_text" => sprintf("No handler is defined for the API request '%s'!", $api_request)]
      ];
      
      $this->reportErrors($response_data);
      
      return false;
    }
    
    return self::$handler_table[$api_request]->invoke($this, $api_request);    
  } // handleApiRequest
} // JsonApiRequestHandler
