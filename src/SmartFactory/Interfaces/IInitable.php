<?php
/**
 * This file contains the declaration of the interface IInitable.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\Interfaces;

/**
 * Interface for universal standard way of the object initialization
 * through the array of parameters.
 *
 * You should implement this interface in your classes if they have to be initialized.
 *
 * @author Oleg Schildt
 */
interface IInitable
{
    /**
     * Initialization method.
     *
     * @param array $parameters
     * This array may contain any data required for the initialization
     * of the objects of your class.
     *
     * @return boolean
     * The method should return true upon successful initialization, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function init($parameters);
} // IInitable
