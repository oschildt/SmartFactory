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
 * @used_by JsonApiRequestManager
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
   * @param array $response_data
   * The array where the response data should be placed.
   *
   * @param array $additional_headers
   * The array where the additional headers be placed.
   *
   * @return void
   *
   * @author Oleg Schildt 
   */
  public function handle($rmanager, $api_request, &$response_data, &$additional_headers);
} // IJsonApiRequestHandler
