<?php
/**
 * This file contains the implementation of the SmartException
 * with the string error code. The system Exception supports
 * only the number error codes.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

/**
 * Class for the exception with the string error code. The system Exception supports
 * only the number error codes.
 *
 * @author Oleg Schildt
 */
class SmartException extends \Exception
{
    /**
     * Internal property for storing the string error code. Predefined codes are:
     *
     * - system_error
     * - missing_data_error
     * - invalid_data_error
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected $error_code = null;
    
    /**
     * Constructor for creation of the exception with message and string error code.
     *
     * @param string $message
     * The message of the exception.
     *
     * @param string $error_code
     * The error code of the exception.
     *
     * @author Oleg Schildt
     */
    public function __construct($message, $error_code = null)
    {
        parent::__construct($message);
        
        $this->error_code = $error_code;
    }
    
    /**
     * Function for getting the string error code of the exception.
     *
     * @return string
     * Returns the string error code of the exception.
     *
     * @author Oleg Schildt
     */
    public function getErrorCode()
    {
        return $this->error_code;
    }
}