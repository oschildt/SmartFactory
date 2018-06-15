<?php
/**
 * This file contains the declaration of the interface IJsonApiRequestHandlerAction.
 *
 * @package System
 *
 * @author Oleg Schildt 
 */
 
namespace SmartFactory\Interfaces;

use SmartFactory\JsonApiRequestManager;

/**
 * Interface for creation of the JSON hanling actions.
 *
 * You should implement this interface in your action classes.
 *
 * @author Oleg Schildt 
 */
interface IJsonApiRequestHandler
{
  /**
   * Method that is called to handle the request.
   *
   * @param JsonApiRequestManager $rmanager
   * The reference to the request manager.
   *
   * @param string $api_request
   * The name of the API request.
   *
   * @return boolean
   * The method should return true upon successful handling, otherwise false.   
   *
   * @author Oleg Schildt 
   */
  public function handle($rmanager, $api_request);
} // IJsonApiRequestHandler
