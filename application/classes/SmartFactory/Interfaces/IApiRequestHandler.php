<?php
/**
 * This file contains the declaration of the interface IApiRequestHandler for handling API requests.
 *
 * @package System
 *
 * @author Oleg Schildt 
 */
 
namespace SmartFactory\Interfaces;

/**
 * Interface for handling API requests.
 *
 * @author Oleg Schildt 
 */
interface IApiRequestHandler
{
  /**
   * Returns current API request name.
   *
   * @return string
   * Returns current API request name.
   *
   * @author Oleg Schildt 
   */
  public function getApiRequest();
  
  /**
   * Registeres a handler for an API request.
   *
   * @param string $api_request
   * The API request name.
   *
   * @param callable $handler
   * The name or definition of the handler function. The signature of 
   * this function is:
   *
   * ```
   * function (IApiRequestHandler $handler, string $api_request) : boolean;
   * ```
   *
   * - $handler - the current instance of the handler object.
   *
   * - $api_request - the API request name.
   *
   * - The handler function should return true, if the request has been successfully 
   * handled. Otherwise it should return false to signilize the handler that 
   * an error occured.
   *
   * @return boolean
   * Returns true if the handler has been successfully registered, otherwise false.   
   *
   * @author Oleg Schildt 
   */
  public function registerApiRequestHandler($api_request, $handler);

  /**
   * Handles the API requests.
   *
   * It is called every time a request comes.
   *
   * @return boolean
   * Returns true, if the request has been successfully handled. Otherwise returns false.
   *
   * @author Oleg Schildt 
   */
  public function handleApiRequest();
} // IApiRequestHandler
