<?php
/**
 * This file contains the declaration of the interface IMessageManager for working with messages.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\Interfaces;

use \SmartFactory\SmartException;

/**
 * Interface for working with messages.
 *
 * @author Oleg Schildt
 */
interface IMessageManager extends IInitable
{
    /**
     * Initializes the message manager with parameters.
     *
     * @param array $parameters
     * The parameters may vary for each message manager.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any system errors.
     *
     * @author Oleg Schildt
     */
    public function init($parameters);

    /**
     * Stores the error to be reported.
     *
     * @param string $message
     * The error message to be reported.
     *
     * @param array $details
     * The details might be useful if the message translations are provided on the client, not
     * on the server, and the message should contain some details that may vary from case to case.
     * In that case, the servers return the message id instead of final text and the details, the client
     * uses the message id, gets the final translated text and substitutes the parameters through the details.
     *
     * @param string $related_element
     * The error element acciciated with the error.
     *
     * @param string $code
     * The error code to be reported.
     *
     * @param string $technical_info
     * The technical information for the error. Displaying
     * of this part might be controlled over an option
     * "debug_mode".
     *
     * @param string $file
     * The source file where the error occured. Per default, the file where the adding error called.
     *
     * @param string $line
     * The source file line where the error occured. Per default, the file line where the adding error called.
     *
     * @return void
     *
     * @see IMessageManager::addWarning()
     * @see IMessageManager::addProgWarning()
     * @see IMessageManager::addDebugMessage()
     * @see IMessageManager::addInfoMessage()
     * @see IMessageManager::addBubbleMessage()
     * @see IMessageManager::getErrors()
     * @see IMessageManager::hasErrors()
     * @see IMessageManager::clearErrors()
     *
     * @author Oleg Schildt
     */
    public function addError($message, $details = [], $related_element = "", $code = "", $technical_info = "", $file = "", $line = "");

    /**
     * Clears the stored error messages.
     *
     * @return void
     *
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::clearInfoMessages()
     * @see IMessageManager::clearBubbleMessages()
     * @see IMessageManager::clearAll()
     * @see IMessageManager::addError()
     *
     * @author Oleg Schildt
     */
    public function clearErrors();
    
    /**
     * Checks whether a stored error message exist.
     *
     * @return boolean
     * Returns true if the stored error message exists, otherwise false.
     *
     * @see IMessageManager::hasWarnings()
     * @see IMessageManager::hasProgWarnings()
     * @see IMessageManager::hasDebugMessages()
     * @see IMessageManager::hasInfoMessages()
     * @see IMessageManager::hasBubbleMessages()
     * @see IMessageManager::getErrors()
     * @see IMessageManager::addError()
     *
     * @author Oleg Schildt
     */
    public function hasErrors();
    
    /**
     * Returns the array of errors if any have been stored.
     *
     * @return array
     * Returns the array of errors if any have been stored.
     *
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::getInfoMessages()
     * @see IMessageManager::getBubbleMessages()
     * @see IMessageManager::hasErrors()
     * @see IMessageManager::addError()
     *
     * @author Oleg Schildt
     */
    public function getErrors();
    
    /**
     * Stores the warning to be reported.
     *
     * @param string $message
     * The warning message to be reported.
     *
     * @param array $details
     * The details might be useful if the message translations are provided on the client, not
     * on the server, and the message should contain some details that may vary from case to case.
     * In that case, the servers return the message id instead of final text and the details, the client
     * uses the message id, gets the final translated text and substitutes the parameters through the details.
     *
     * @param string $related_element
     * The error element accociated with the warning.
     *
     * @return void
     *
     * @see IMessageManager::addError()
     * @see IMessageManager::addProgWarning()
     * @see IMessageManager::addDebugMessage()
     * @see IMessageManager::addInfoMessage()
     * @see IMessageManager::addBubbleMessage()
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::hasWarnings()
     * @see IMessageManager::clearWarnings()
     *
     * @author Oleg Schildt
     */
    public function addWarning($message, $details = [], $related_element = "");
    
    /**
     * Clears the stored warning messages.
     *
     * @return void
     *
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::clearInfoMessages()
     * @see IMessageManager::clearBubbleMessages()
     * @see IMessageManager::clearAll()
     * @see IMessageManager::addWarning()
     *
     * @author Oleg Schildt
     */
    public function clearWarnings();
    
    /**
     * Checks whether a stored error warning exist.
     *
     * @return boolean
     * Returns true if the stored warning message exists, otherwise false.
     *
     * @see IMessageManager::hasErrors()
     * @see IMessageManager::hasProgWarnings()
     * @see IMessageManager::hasDebugMessages()
     * @see IMessageManager::hasInfoMessages()
     * @see IMessageManager::hasBubbleMessages()
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::addWarning()
     *
     * @author Oleg Schildt
     */
    public function hasWarnings();
    
    /**
     * Returns the array of warnings if any have been stored.
     *
     * @return array
     * Returns the array of warnings if any have been stored.
     *
     * @see IMessageManager::getErrors()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::getInfoMessages()
     * @see IMessageManager::getBubbleMessages()
     * @see IMessageManager::hasWarnings()
     * @see IMessageManager::addWarning()
     *
     * @author Oleg Schildt
     */
    public function getWarnings();
    
    /**
     * Stores the programming warning to be reported.
     *
     * Programming warnings are shown only if the option
     * "show programming warning" is active.
     *
     * @param string $message
     * The programming warning message to be reported.
     *
     * @param string $file
     * The source file where the error occured. Per default, the file where the adding error called.
     *
     * @param string $line
     * The source file line where the error occured. Per default, the file line where the adding error called.
     *
     * @return void
     *
     * @see IMessageManager::addError()
     * @see IMessageManager::addWarning()
     * @see IMessageManager::addDebugMessage()
     * @see IMessageManager::addInfoMessage()
     * @see IMessageManager::addBubbleMessage()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::hasProgWarnings()
     * @see IMessageManager::clearProgWarnings()
     *
     * @author Oleg Schildt
     */
    public function addProgWarning($message, $file = "", $line = "");
    
    /**
     * Clears the stored programming warning messages.
     *
     * @return void
     *
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::clearInfoMessages()
     * @see IMessageManager::clearBubbleMessages()
     * @see IMessageManager::clearAll()
     * @see IMessageManager::addProgWarning()
     *
     * @author Oleg Schildt
     */
    public function clearProgWarnings();
    
    /**
     * Checks whether a stored programming warning exist.
     *
     * @return boolean
     * Returns true if the stored programming warning message exists, otherwise false.
     *
     * @see IMessageManager::hasErrors()
     * @see IMessageManager::hasWarnings()
     * @see IMessageManager::hasDebugMessages()
     * @see IMessageManager::hasInfoMessages()
     * @see IMessageManager::hasBubbleMessages()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::addProgWarning()
     *
     * @author Oleg Schildt
     */
    public function hasProgWarnings();
    
    /**
     * Returns the array of programming warnings if any have been stored.
     *
     * @return array
     * Returns the array of programming warnings if any have been stored.
     *
     * @see IMessageManager::getErrors()
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::getInfoMessages()
     * @see IMessageManager::getBubbleMessages()
     * @see IMessageManager::hasProgWarnings()
     * @see IMessageManager::addProgWarning()
     *
     * @author Oleg Schildt
     */
    public function getProgWarnings();
    
    /**
     * Stores the debugging message to be reported.
     *
     * Displaying of the debugging messages might be
     * implemented to simplify the debugging process,
     * e.g. to the browser console or in a lightbox.
     *
     * @param string $message
     * The debugging message to be reported.
     *
     * @param string $file
     * The source file where the debug output occured. Per default, the file where the debug output called.
     *
     * @param string $line
     * The source file line where the debug output occured. Per default, the file line where the debug output called.
     * 
     * @return void
     *
     * @see IMessageManager::addError()
     * @see IMessageManager::addWarning()
     * @see IMessageManager::addProgWarning()
     * @see IMessageManager::addInfoMessage()
     * @see IMessageManager::addBubbleMessage()
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::hasDebugMessages()
     * @see IMessageManager::clearDebugMessages()
     *
     * @author Oleg Schildt
     */
    public function addDebugMessage($message, $file = "", $line = "");
    
    /**
     * Clears the stored debugging messages.
     *
     * @return void
     *
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::clearInfoMessages()
     * @see IMessageManager::clearBubbleMessages()
     * @see IMessageManager::clearAll()
     * @see IMessageManager::addDebugMessage()
     *
     * @author Oleg Schildt
     */
    public function clearDebugMessages();
    
    /**
     * Checks whether a stored debugging message exist.
     *
     * @return boolean
     * Returns true if the stored debugging message exists, otherwise false.
     *
     * @see IMessageManager::hasErrors()
     * @see IMessageManager::hasWarnings()
     * @see IMessageManager::hasProgWarnings()
     * @see IMessageManager::hasInfoMessages()
     * @see IMessageManager::hasBubbleMessages()
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::addDebugMessage()
     *
     * @author Oleg Schildt
     */
    public function hasDebugMessages();
    
    /**
     * Returns the array of debugging messages if any have been stored.
     *
     * @return array
     * Returns the array of debugging messages if any have been stored.
     *
     * @see IMessageManager::getErrors()
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::getInfoMessages()
     * @see IMessageManager::getBubbleMessages()
     * @see IMessageManager::hasDebugMessages()
     * @see IMessageManager::addDebugMessage()
     *
     * @author Oleg Schildt
     */
    public function getDebugMessages();
    
    /**
     * Stores the information message to be reported.
     *
     * @param string $message
     * The information message to be reported.
     *
     * @param array $details
     * The details might be useful if the message translations are provided on the client, not
     * on the server, and the message should contain some details that may vary from case to case.
     * In that case, the servers return the message id instead of final text and the details, the client
     * uses the message id, gets the final translated text and substitutes the parameters through the details.
     *
     * @param boolean $autoclose
     * The flag that controls whether the message box should be closed
     * automatically after a time.
     *
     * @return void
     *
     * @see IMessageManager::addError()
     * @see IMessageManager::addWarning()
     * @see IMessageManager::addProgWarning()
     * @see IMessageManager::addDebugMessage()
     * @see IMessageManager::addBubbleMessage()
     * @see IMessageManager::getInfoMessages()
     * @see IMessageManager::hasInfoMessages()
     * @see IMessageManager::clearInfoMessages()
     *
     * @author Oleg Schildt
     */
    public function addInfoMessage($message, $details = [], $autoclose = false);
    
    /**
     * Clears the stored information messages.
     *
     * @return void
     *
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::clearBubbleMessages()
     * @see IMessageManager::clearAll()
     * @see IMessageManager::addInfoMessage()
     *
     * @author Oleg Schildt
     */
    public function clearInfoMessages();
    
    /**
     * Checks whether an information message exist.
     *
     * @return boolean
     * Returns true if the information message exists, otherwise false.
     *
     * @see IMessageManager::hasErrors()
     * @see IMessageManager::hasWarnings()
     * @see IMessageManager::hasProgWarnings()
     * @see IMessageManager::hasDebugMessages()
     * @see IMessageManager::hasBubbleMessages()
     * @see IMessageManager::getInfoMessages()
     * @see IMessageManager::addInfoMessage()
     *
     * @author Oleg Schildt
     */
    public function hasInfoMessages();
    
    /**
     * Returns the array of information messages if any have been stored.
     *
     * @return array
     * Returns the array of information messages if any have been stored.
     *
     * @see IMessageManager::getErrors()
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::getBubbleMessages()
     * @see IMessageManager::hasInfoMessages()
     * @see IMessageManager::addInfoMessage()
     *
     * @author Oleg Schildt
     */
    public function getInfoMessages();

    /**
     * Stores the information message to be reported.
     *
     * @param string $message
     * The information message to be reported.
     *
     * @param array $details
     * The details might be useful if the message translations are provided on the client, not
     * on the server, and the message should contain some details that may vary from case to case.
     * In that case, the servers return the message id instead of final text and the details, the client
     * uses the message id, gets the final translated text and substitutes the parameters through the details.
     *
     * @param boolean $autoclose
     * The flag that controls whether the message box should be closed
     * automatically after a time.
     *
     * @return void
     *
     * @see IMessageManager::addError()
     * @see IMessageManager::addWarning()
     * @see IMessageManager::addProgWarning()
     * @see IMessageManager::addDebugMessage()
     * @see IMessageManager::addInfoMessage()
     * @see IMessageManager::getBubbleMessages()
     * @see IMessageManager::hasBubbleMessages()
     * @see IMessageManager::clearBubbleMessages()
     *
     * @author Oleg Schildt
     */
    public function addBubbleMessage($message, $details = [], $autoclose = true);

    /**
     * Clears the stored information messages.
     *
     * @return void
     *
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::clearInfoMessages()
     * @see IMessageManager::clearAll()
     * @see IMessageManager::addBubbleMessage()
     *
     * @author Oleg Schildt
     */
    public function clearBubbleMessages();

    /**
     * Checks whether an information message exist.
     *
     * @return boolean
     * Returns true if the information message exists, otherwise false.
     *
     * @see IMessageManager::hasErrors()
     * @see IMessageManager::hasWarnings()
     * @see IMessageManager::hasProgWarnings()
     * @see IMessageManager::hasDebugMessages()
     * @see IMessageManager::hasInfoMessages()
     * @see IMessageManager::getBubbleMessages()
     * @see IMessageManager::addBubbleMessage()
     *
     * @author Oleg Schildt
     */
    public function hasBubbleMessages();

    /**
     * Returns the array of information messages if any have been stored.
     *
     * @return array
     * Returns the array of information messages if any have been stored.
     *
     * @see IMessageManager::getErrors()
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::getInfoMessages()
     * @see IMessageManager::hasBubbleMessages()
     * @see IMessageManager::addBubbleMessage()
     *
     * @author Oleg Schildt
     */
    public function getBubbleMessages();

    /**
     * Clears all stored messages and active elements.
     *
     * @return void
     *
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::clearInfoMessages()
     * @see IMessageManager::clearBubbleMessages()
     *
     * @author Oleg Schildt
     */
    public function clearAll();
} // IMessageManager
