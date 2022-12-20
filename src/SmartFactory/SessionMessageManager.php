<?php
/**
 * This file contains the implementation of the interface IMessageManager
 * in the class SessionMessageManager for working with messages - errors, warnings etc.
 * with session persictence.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

/**
 * Class for working with messages - errors, warnings etc. with session persictence.
 * It is suitable for the classical web applications with redirections.
 *
 * @author Oleg Schildt
 */
class SessionMessageManager extends MessageManager
{
    /**
     * Constructor.
     *
     * @author Oleg Schildt
     */
    public function __construct()
    {
        $this->messages = &session()->vars();
    } // __construct

    /**
     * This auxiliary function returns the messages of the desired type.
     *
     * @param array &$messages
     * The messages to be retrieved.
     *
     * @return array
     * Returns the array of messages.
     *
     * @author Oleg Schildt
     */
    protected function getMessages(&$messages)
    {
        $values = array_values($messages);

        $messages = [];

        return $values;
    } // getMessages
} // MessageManager
