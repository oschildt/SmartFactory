<?php
/**
 * This file contains the declaration of the interface IRequestHandler for handling the API requests.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\Interfaces;

/**
 * Interface for handling the API requests.
 *
 * @author Oleg Schildt
 */
interface IRequestHandler
{
    /**
     * Method that is called to handle the request.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    function handleRequest();
} // IRequestHandler