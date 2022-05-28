<?php
/**
 * This file contains the declaration of the interface IMessageManager for working with messages.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\Interfaces;

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
     * @return boolean
     * The method should return true upon successful initialization, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function init($parameters);
    
    /**
     * Sets the element to be focused.
     *
     * @param string $element
     * The ID of the element to be focused.
     *
     * @return void
     *
     * @see IMessageManager::setFocusElement()
     *
     * @author Oleg Schildt
     */
    public function setFocusElement($element);
    
    /**
     * Returns the element to be focused.
     *
     * @return string
     * Returns the ID of the element to be focused.
     *
     * @see IMessageManager::getFocusElement()
     *
     * @author Oleg Schildt
     */
    public function getFocusElement();
    
    /**
     * Sets the tab to be activated.
     *
     * @param string $tab
     * The ID of the tab to be activated.
     *
     * @return void
     *
     * @see IMessageManager::getActiveTab()
     *
     * @author Oleg Schildt
     */
    public function setActiveTab($tab);
    
    /**
     * Returns the tab to be activated.
     *
     * @return string
     * Returns the ID of the tab to be activated.
     *
     * @see IMessageManager::setActiveTab()
     *
     * @author Oleg Schildt
     */
    public function getActiveTab();
    
    /**
     * Sets the field to be highlighted.
     *
     * @param string $element
     * The ID of the field to be highlighted.
     *
     * @return void
     *
     * @see IMessageManager::getErrorElement()
     *
     * @author Oleg Schildt
     */
    public function setErrorElement($element);
    
    /**
     * Returns the field to be highlighted.
     *
     * @return string
     * Returns the ID of the field to be highlighted.
     *
     * @see IMessageManager::setErrorElement()
     *
     * @author Oleg Schildt
     */
    public function getErrorElement();
    
    /**
     * Stores the error to be reported.
     *
     * @param string $message
     * The error message to be reported.
     *
     * @param string $details
     * The error details to be reported. Here, more
     * technical details should be placed. Displaying
     * of this part might be controlled over a option
     * "display message details".
     *
     * @param string $code
     * The error code to be reported.
     *
     * @return void
     *
     * @see IMessageManager::getErrors()
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::errorsExist()
     *
     * @author Oleg Schildt
     */
    public function setError($message, $details = "", $code = "");
    
    /**
     * Clears the stored error messages.
     *
     * @return void
     *
     * @see IMessageManager::setError()
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::clearInfos()
     * @see IMessageManager::clearAll()
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
     * @see IMessageManager::setError()
     * @see IMessageManager::warningsExist()
     * @see IMessageManager::progWarningsExist()
     * @see IMessageManager::debugMessageExists()
     * @see IMessageManager::infosExist()
     *
     * @author Oleg Schildt
     */
    public function errorsExist();
    
    /**
     * Returns the array of errors if any have been stored.
     *
     * @return array
     * Returns the array of errors if any have been stored.
     *
     * @see IMessageManager::setError()
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::getInfos()
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
     * @param string $details
     * The warning details to be reported. Here, more
     * technical details should be placed. Displaying
     * of this part might be controlled over a option
     * "display message details".
     *
     * @return void
     *
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::warningsExist()
     *
     * @author Oleg Schildt
     */
    public function setWarning($message, $details = "");
    
    /**
     * Clears the stored warning messages.
     *
     * @return void
     *
     * @see IMessageManager::setWarning()
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::clearInfos()
     * @see IMessageManager::clearAll()
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
     * @see IMessageManager::setWarning()
     * @see IMessageManager::errorsExist()
     * @see IMessageManager::progWarningsExist()
     * @see IMessageManager::debugMessageExists()
     * @see IMessageManager::infosExist()
     *
     * @author Oleg Schildt
     */
    public function warningsExist();
    
    /**
     * Returns the array of warnings if any have been stored.
     *
     * @return array
     * Returns the array of warnings if any have been stored.
     *
     * @see IMessageManager::setWarning()
     * @see IMessageManager::getErrors()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::getInfos()
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
     * @param string $details
     * The programming warning details to be reported. Here, more
     * technical details should be placed. Displaying
     * of this part might be controlled over a option
     * "display message details".
     *
     * @return void
     *
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::progWarningsExist()
     *
     * @author Oleg Schildt
     */
    public function setProgWarning($message, $details = "");
    
    /**
     * Clears the stored programming warning messages.
     *
     * @return void
     *
     * @see IMessageManager::setProgWarning()
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::clearInfos()
     * @see IMessageManager::clearAll()
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
     * @see IMessageManager::setProgWarning()
     * @see IMessageManager::errorsExist()
     * @see IMessageManager::warningsExist()
     * @see IMessageManager::debugMessageExists()
     * @see IMessageManager::infosExist()
     *
     * @author Oleg Schildt
     */
    public function progWarningsExist();
    
    /**
     * Returns the array of programming warnings if any have been stored.
     *
     * @return array
     * Returns the array of programming warnings if any have been stored.
     *
     * @see IMessageManager::setProgWarning()
     * @see IMessageManager::getErrors()
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::getInfos()
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
     * The debugging message message to be reported.
     *
     * @param string $details
     * The debugging message details to be reported. Here, more
     * technical details should be placed. Displaying
     * of this part might be controlled over a option
     * "display message details".
     *
     * @return void
     *
     * @see IMessageManager::getDebugMessages()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::debugMessageExists()
     *
     * @author Oleg Schildt
     */
    public function setDebugMessage($message, $details = "");
    
    /**
     * Clears the stored debugging messages.
     *
     * @return void
     *
     * @see IMessageManager::setDebugMessage()
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::clearInfos()
     * @see IMessageManager::clearAll()
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
     * @see IMessageManager::setDebugMessage()
     * @see IMessageManager::errorsExist()
     * @see IMessageManager::warningsExist()
     * @see IMessageManager::progWarningsExist()
     * @see IMessageManager::infosExist()
     *
     * @author Oleg Schildt
     */
    public function debugMessageExists();
    
    /**
     * Returns the array of debugging messages if any have been stored.
     *
     * @return array
     * Returns the array of debugging messages if any have been stored.
     *
     * @see IMessageManager::setDebugMessage()
     * @see IMessageManager::getErrors()
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::getInfos()
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
     * @param string $details
     * The information message details to be reported. Here, more
     * technical details should be placed. Displaying
     * of this part might be controlled over a option
     * "display message details".
     *
     * @param boolean $auto_hide
     * The flag that controls whether the message box should be closed
     * automatically after a time defined by the initialization.
     *
     * @return void
     *
     * @see IMessageManager::getInfos()
     * @see IMessageManager::clearInfos()
     * @see IMessageManager::infosExist()
     *
     * @author Oleg Schildt
     */
    public function setInfo($message, $details = "", $auto_hide = false);
    
    /**
     * Clears the stored information messages.
     *
     * @return void
     *
     * @see IMessageManager::setInfo()
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::clearAll()
     *
     * @author Oleg Schildt
     */
    public function clearInfos();
    
    /**
     * Checks whether an information message exist.
     *
     * @return boolean
     * Returns true if the information message exists, otherwise false.
     *
     * @see IMessageManager::setInfo()
     * @see IMessageManager::errorsExist()
     * @see IMessageManager::warningsExist()
     * @see IMessageManager::progWarningsExist()
     * @see IMessageManager::debugMessageExists()
     *
     * @author Oleg Schildt
     */
    public function infosExist();
    
    /**
     * Returns the array of information messages if any have been stored.
     *
     * @return array
     * Returns the array of information messages if any have been stored.
     *
     * @see IMessageManager::setInfo()
     * @see IMessageManager::getErrors()
     * @see IMessageManager::getWarnings()
     * @see IMessageManager::getProgWarnings()
     * @see IMessageManager::getDebugMessages()
     *
     * @author Oleg Schildt
     */
    public function getInfos();
    
    /**
     * Clears all stored messages and active elements.
     *
     * @return void
     *
     * @see IMessageManager::clearErrors()
     * @see IMessageManager::clearWarnings()
     * @see IMessageManager::clearProgWarnings()
     * @see IMessageManager::clearDebugMessages()
     * @see IMessageManager::clearInfos()
     *
     * @author Oleg Schildt
     */
    public function clearAll();
    
    /**
     * Returns the auto hide time in seconds for the info
     * messages with flag atuo_hide = true.
     *
     * @return int
     * Returns the auto hide time in seconds for the info
     * messages with flag atuo_hide = true.
     *
     * @author Oleg Schildt
     */
    public function getAutoHideTime();
    
    /**
     * Add all stored existing messages to the response.
     *
     * @param string &$response
     * The target array where the messages should be added.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function addMessagesToResponse(&$response);
    
    /**
     * Returns the state whether the display of the message details is active or not.
     *
     * If the display of the message details is active, they are shown in the frontend.
     *
     * @return boolean
     * Returns the state whether the display of the message details is active or not.
     *
     * @see IMessageManager::enableDetails()
     * @see IMessageManager::disableDetails()
     *
     * @author Oleg Schildt
     */
    public function detailsActive();
    
    /**
     * Enables the display of the message details.
     *
     * If the display of the message details is active, they are shown in the frontend.
     *
     * @return void
     *
     * @see IMessageManager::detailsActive()
     * @see IMessageManager::disableDetails()
     *
     * @author Oleg Schildt
     */
    public function enableDetails();
    
    /**
     * Disables the display of the message details.
     *
     * If the display of the message details is active, they are shown in the frontend.
     *
     * @return void
     *
     * @see IMessageManager::detailsActive()
     * @see IMessageManager::enableDetails()
     *
     * @author Oleg Schildt
     */
    public function disableDetails();

    /**
     * Returns the state whether the display of the programming warnings is active or not.
     *
     * If the display of the programming warnings is active, they are shown in the frontend.
     *
     * @return boolean
     * Returns the state whether the display of the programming warnings is active or not.
     *
     * @see IMessageManager::enableProgWarnings()
     * @see IMessageManager::disableProgWarnings()
     *
     * @author Oleg Schildt
     */
    public function progWarningsActive();
    
    /**
     * Enables the display of the programming warnings.
     *
     * If the display of the programming warnings is active, they are shown in the frontend.
     *
     * @return void
     *
     * @see IMessageManager::progWarningsActive()
     * @see IMessageManager::disableProgWarnings()
     *
     * @author Oleg Schildt
     */
    public function enableProgWarnings();
    
    /**
     * Disables the display of the programming warnings.
     *
     * If the display of the programming warnings is active, they are shown in the frontend.
     *
     * @return void
     *
     * @see IMessageManager::progWarningsActive()
     * @see IMessageManager::enableProgWarnings()
     *
     * @author Oleg Schildt
     */
    public function disableProgWarnings();
} // IMessageManager
