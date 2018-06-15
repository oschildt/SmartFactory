<?php
/**
 * This file contains the declaration of the interface IXmlApiRequestHandler.
 *
 * @package System
 *
 * @author Oleg Schildt 
 */
 
namespace SmartFactory\Interfaces;

use SmartFactory\XmlApiRequestManager;

/**
 * Interface for creation of the XML hanling actions.
 *
 * You should implement this interface in your action classes.
 *
 * @author Oleg Schildt 
 */
interface IXmlApiRequestHandler
{
  /**
   * Method that is called to handle the request.
   *
   * @param XmlApiRequestManager $rmanager
   * The reference to the manager.
   *
   * @param string $api_request
   * The name of the API request.
   *
   * @param \DOMDocument $xmldoc
   * The input XML DOM object.
   *
   * @return boolean
   * The method should return true upon successful handling, otherwise false.   
   *
   * @author Oleg Schildt 
   */
  public function handle($rmanager, $api_request, $xmldoc);
} // IXmlApiRequestHandler
