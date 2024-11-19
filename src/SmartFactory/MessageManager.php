<?php
/**
 * This file contains the implementation of the interface IMessageManager
 * in the class MessageManager for working with messages - errors, warnings etc.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\Interfaces\IMessageManager;

/**
 * Class for working with messages - errors, warnings etc.
 *
 * @author Oleg Schildt
 */
class MessageManager implements IMessageManager
{
    /**
     * Internal variable for storing the messages.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $messages = null;

    /**
     * Internal variable for storing the state whether technical details, programmer warnings and debug infromation are also sent to the client.
     *
     * @var boolean
     *
     * @author Oleg Schildt
     */
    protected $debug_mode = false;

    /**
     * Internal variable for storing the flag that defines whether for each debug output the source file and
     * line number are written from where it is called.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected $write_source_file_and_line_by_debug;

    /**
     * Initializes the message manager.
     *
     * @param array $parameters
     * Settings for message management as an associative array in the form key => value:
     *
     * - $parameters["debug_mode"] - if set to True, the technical details, programmer warnings and debug infromation are also sent to the client. Use it only during dubugging!
     *
     * - $parameters["write_source_file_and_line_by_debug"] - the flag that defines whether for each debug output the source file and
     * line number are written from where it is called.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function init($parameters)
    {
        $this->debug_mode = !empty($parameters["debug_mode"]);

        $this->write_source_file_and_line_by_debug = !empty($parameters["write_source_file_and_line_by_debug"]);
    } // init

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
     * The error element accociated with the error.
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
     * @see MessageManager::addWarning()
     * @see MessageManager::addProgWarning()
     * @see MessageManager::addDebugMessage()
     * @see MessageManager::addInfoMessage()
     * @see MessageManager::addBubbleMessage()
     * @see MessageManager::getErrors()
     * @see MessageManager::hasErrors()
     * @see MessageManager::clearErrors()
     *
     * @author Oleg Schildt
     */
    public function addError($message, $details = [], $related_element = "", $code = "", $technical_info = "", $file = "", $line = "")
    {
        if (empty($message)) {
            return;
        }

        if (empty($file) || empty($line)) {
            $backfiles = debug_backtrace();

            if (empty($file)) {
                $file = empty($backfiles[0]['file']) ? "" : $backfiles[0]['file'];
            }

            if (empty($line)) {
                $line = empty($backfiles[0]['line']) ? "" : $backfiles[0]['line'];
            }
        }
        
        if (!$this->write_source_file_and_line_by_debug) {
            $file = "";
            $line = "";
        }

        if (!$this->debug_mode) {
            $technical_info = "";
            $file = "";
            $line = "";
        }

        $this->messages["errors"][$message . implode("#", $details)] = [
            "message" => $message,
            "details" => $details,
            "related_element" => $related_element,
            "code" => $code,
            "technical_info" => $technical_info,
            "file" => $file,
            "line" => $line
        ];
    } // addError

    /**
     * Clears the stored error messages.
     *
     * @return void
     *
     * @see MessageManager::clearWarnings()
     * @see MessageManager::clearProgWarnings()
     * @see MessageManager::clearDebugMessages()
     * @see MessageManager::clearInfoMessages()
     * @see MessageManager::clearBubbleMessages()
     * @see MessageManager::clearAll()
     * @see MessageManager::addError()
     *
     * @author Oleg Schildt
     */
    public function clearErrors()
    {
        unset($this->messages["errors"]);
    } // clearErrors

    /**
     * Checks whether a stored error message exist.
     *
     * @return boolean
     * Returns true if the stored error message exists, otherwise false.
     *
     * @see MessageManager::hasWarnings()
     * @see MessageManager::hasProgWarnings()
     * @see MessageManager::hasDebugMessages()
     * @see MessageManager::hasInfoMessages()
     * @see MessageManager::hasBubbleMessages()
     * @see MessageManager::getErrors()
     * @see MessageManager::addError()
     *
     * @author Oleg Schildt
     */
    public function hasErrors()
    {
        return (isset($this->messages["errors"]) && count($this->messages["errors"]) > 0);
    } // hasErrors

    /**
     * Returns the array of errors if any have been stored.
     *
     * When the messages are requested, it is assumed they will be displayed. Thus,
     * the message array is cleared to avoid displaying of the same messages twice.
     *
     * @return array
     * Returns the array of errors if any have been stored.
     *
     * @see MessageManager::getWarnings()
     * @see MessageManager::getProgWarnings()
     * @see MessageManager::getDebugMessages()
     * @see MessageManager::getInfoMessages()
     * @see MessageManager::getBubbleMessages()
     * @see MessageManager::hasErrors()
     * @see MessageManager::addError()
     *
     * @author Oleg Schildt
     */
    public function getErrors()
    {
        if (!$this->hasErrors()) {
            return [];
        }

        return $this->getMessages($this->messages["errors"]);
    } // getErrors

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
     * @see MessageManager::addError()
     * @see MessageManager::addProgWarning()
     * @see MessageManager::addDebugMessage()
     * @see MessageManager::addInfoMessage()
     * @see MessageManager::addBubbleMessage()
     * @see MessageManager::getWarnings()
     * @see MessageManager::hasWarnings()
     * @see MessageManager::clearWarnings()
     *
     * @author Oleg Schildt
     */
    public function addWarning($message, $details = [], $related_element = "")
    {
        if (empty($message)) {
            return;
        }

        $this->messages["warnings"][$message . implode("#", $details)] = [
            "message" => $message,
            "details" => $details,
            "related_element" => $related_element
        ];
    } // addWarning

    /**
     * Clears the stored warning messages.
     *
     * @return void
     *
     * @see MessageManager::clearErrors()
     * @see MessageManager::clearProgWarnings()
     * @see MessageManager::clearDebugMessages()
     * @see MessageManager::clearInfoMessages()
     * @see MessageManager::clearBubbleMessages()
     * @see MessageManager::clearAll()
     * @see MessageManager::addWarning()
     *
     * @author Oleg Schildt
     */
    public function clearWarnings()
    {
        unset($this->messages["warnings"]);
    } // clearWarnings

    /**
     * Checks whether a stored error warning exist.
     *
     * @return boolean
     * Returns true if the stored warning message exists, otherwise false.
     *
     * @see MessageManager::hasErrors()
     * @see MessageManager::hasProgWarnings()
     * @see MessageManager::hasDebugMessages()
     * @see MessageManager::hasInfoMessages()
     * @see MessageManager::hasBubbleMessages()
     * @see MessageManager::getWarnings()
     * @see MessageManager::addWarning()
     *
     * @author Oleg Schildt
     */
    public function hasWarnings()
    {
        return (isset($this->messages["warnings"]) && count($this->messages["warnings"]) > 0);
    } // hasWarnings

    /**
     * Returns the array of warnings if any have been stored.
     *
     * When the messages are requested, it is assumed they will be displayed. Thus,
     * the message array is cleared to avoid displaying of the same messages twice.
     *
     * @return array
     * Returns the array of warnings if any have been stored.
     *
     * @see MessageManager::getErrors()
     * @see MessageManager::getProgWarnings()
     * @see MessageManager::getDebugMessages()
     * @see MessageManager::getInfoMessages()
     * @see MessageManager::getBubbleMessages()
     * @see MessageManager::hasWarnings()
     * @see MessageManager::addWarning()
     *
     * @author Oleg Schildt
     */
    public function getWarnings()
    {
        if (!$this->hasWarnings()) {
            return [];
        }

        return $this->getMessages($this->messages["warnings"]);
    } // getWarnings

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
     * @see MessageManager::addError()
     * @see MessageManager::addWarning()
     * @see MessageManager::addDebugMessage()
     * @see MessageManager::addInfoMessage()
     * @see MessageManager::addBubbleMessage()
     * @see MessageManager::getProgWarnings()
     * @see MessageManager::hasProgWarnings()
     * @see MessageManager::clearProgWarnings()
     *
     * @author Oleg Schildt
     */
    public function addProgWarning($message, $file = "", $line = "")
    {
        if (!$this->debug_mode) {
            return;
        }

        if (empty($message)) {
            return;
        }

        if (empty($file) || empty($line)) {
            $backfiles = debug_backtrace();

            if (empty($file)) {
                $file = empty($backfiles[0]['file']) ? "" : $backfiles[0]['file'];
            }

            if (empty($line)) {
                $line = empty($backfiles[0]['line']) ? "" : $backfiles[0]['line'];
            }
        }

        $this->messages["prog_warnings"][$message] = [
            "message" => $message,
            "file" => $file,
            "line" => $line
        ];
    } // addProgWarning

    /**
     * Clears the stored programming warning messages.
     *
     * @return void
     *
     * @see MessageManager::clearErrors()
     * @see MessageManager::clearWarnings()
     * @see MessageManager::clearDebugMessages()
     * @see MessageManager::clearInfoMessages()
     * @see MessageManager::clearBubbleMessages()
     * @see MessageManager::clearAll()
     * @see MessageManager::addProgWarning()
     *
     * @author Oleg Schildt
     */
    public function clearProgWarnings()
    {
        unset($this->messages["prog_warnings"]);
    } // clearProgWarnings

    /**
     * Checks whether a stored programming warning exist.
     *
     * @return boolean
     * Returns true if the stored programming warning message exists, otherwise false.
     *
     * @see MessageManager::hasErrors()
     * @see MessageManager::hasWarnings()
     * @see MessageManager::hasDebugMessages()
     * @see MessageManager::hasInfoMessages()
     * @see MessageManager::hasBubbleMessages()
     * @see MessageManager::getProgWarnings()
     * @see MessageManager::addProgWarning()
     *
     * @author Oleg Schildt
     */
    public function hasProgWarnings()
    {
        return (isset($this->messages["prog_warnings"]) && count($this->messages["prog_warnings"]) > 0);
    } // hasProgWarnings

    /**
     * Returns the array of programming warnings if any have been stored.
     *
     * When the messages are requested, it is assumed they will be displayed. Thus,
     * the message array is cleared to avoid displaying of the same messages twice.
     *
     * @return array
     * Returns the array of programming warnings if any have been stored.
     *
     * @see MessageManager::getErrors()
     * @see MessageManager::getWarnings()
     * @see MessageManager::getDebugMessages()
     * @see MessageManager::getInfoMessages()
     * @see MessageManager::getBubbleMessages()
     * @see MessageManager::hasProgWarnings()
     * @see MessageManager::addProgWarning()
     *
     * @author Oleg Schildt
     */
    public function getProgWarnings()
    {
        if (!$this->hasProgWarnings()) {
            return [];
        }

        return $this->getMessages($this->messages["prog_warnings"]);
    } // getProgWarnings

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
     * @see MessageManager::addError()
     * @see MessageManager::addWarning()
     * @see MessageManager::addProgWarning()
     * @see MessageManager::addInfoMessage()
     * @see MessageManager::addBubbleMessage()
     * @see MessageManager::getDebugMessages()
     * @see MessageManager::hasDebugMessages()
     * @see MessageManager::clearDebugMessages()
     *
     * @author Oleg Schildt
     */
    public function addDebugMessage($message, $file = "", $line = "")
    {
        if (!$this->debug_mode) {
            return;
        }

        if (empty($message)) {
            return;
        }


        if (empty($file) || empty($line)) {
            $backfiles = debug_backtrace();

            if (empty($file)) {
                $file = empty($backfiles[0]['file']) ? "" : $backfiles[0]['file'];
            }

            if (empty($line)) {
                $line = empty($backfiles[0]['line']) ? "" : $backfiles[0]['line'];
            }
        }

        if (!$this->write_source_file_and_line_by_debug) {
            $file = "";
            $line = "";
        }

        $this->messages["debug_messages"][$message] = [
            "message" => $message,
            "file" => $file,
            "line" => $line
        ];
    } // addDebugMessage

    /**
     * Clears the stored debugging messages.
     *
     * @return void
     *
     * @see MessageManager::clearErrors()
     * @see MessageManager::clearWarnings()
     * @see MessageManager::clearProgWarnings()
     * @see MessageManager::clearInfoMessages()
     * @see MessageManager::clearBubbleMessages()
     * @see MessageManager::clearAll()
     * @see MessageManager::addDebugMessage()
     *
     * @author Oleg Schildt
     */
    public function clearDebugMessages()
    {
        unset($this->messages["debug_messages"]);
    } // clearDebugMessages

    /**
     * Checks whether a stored debugging message exist.
     *
     * @return boolean
     * Returns true if the stored debugging message exists, otherwise false.
     *
     * @see MessageManager::hasErrors()
     * @see MessageManager::hasWarnings()
     * @see MessageManager::hasProgWarnings()
     * @see MessageManager::hasInfoMessages()
     * @see MessageManager::hasBubbleMessages()
     * @see MessageManager::getDebugMessages()
     * @see MessageManager::addDebugMessage()
     *
     * @author Oleg Schildt
     */
    public function hasDebugMessages()
    {
        return (isset($this->messages["debug_messages"]) && count($this->messages["debug_messages"]) > 0);
    } // hasDebugMessages

    /**
     * Returns the array of debugging messages if any have been stored.
     *
     * When the messages are requested, it is assumed they will be displayed. Thus,
     * the message array is cleared to avoid displaying of the same messages twice.
     *
     * @return array
     * Returns the array of debugging messages if any have been stored.
     *
     * @see MessageManager::getErrors()
     * @see MessageManager::getWarnings()
     * @see MessageManager::getProgWarnings()
     * @see MessageManager::getInfoMessages()
     * @see MessageManager::getBubbleMessages()
     * @see MessageManager::hasDebugMessages()
     * @see MessageManager::addDebugMessage()
     *
     * @author Oleg Schildt
     */
    public function getDebugMessages()
    {
        if (!$this->hasDebugMessages()) {
            return [];
        }

        return $this->getMessages($this->messages["debug_messages"]);
    } // getDebugMessages

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
     * The flag that controls whether the message box should be closed automatically after a time.
     *
     * @return void
     *
     * @see MessageManager::addError()
     * @see MessageManager::addWarning()
     * @see MessageManager::addProgWarning()
     * @see MessageManager::addDebugMessage()
     * @see MessageManager::addBubbleMessage()
     * @see MessageManager::getInfoMessages()
     * @see MessageManager::hasInfoMessages()
     * @see MessageManager::clearInfoMessages()
     *
     * @author Oleg Schildt
     */
    public function addInfoMessage($message, $details = [], $autoclose = false)
    {
        if (empty($message)) {
            return;
        }

        $this->messages["info_messages"][$message . implode("#", $details)] = [
            "message" => $message,
            "details" => $details,
            "autoclose" => $autoclose
        ];
    } // addInfoMessage

    /**
     * Clears the stored information messages.
     *
     * @return void
     *
     * @see MessageManager::clearErrors()
     * @see MessageManager::clearWarnings()
     * @see MessageManager::clearProgWarnings()
     * @see MessageManager::clearDebugMessages()
     * @see MessageManager::clearBubbleMessages()
     * @see MessageManager::clearAll()
     * @see MessageManager::addInfoMessage()
     *
     * @author Oleg Schildt
     */
    public function clearInfoMessages()
    {
        unset($this->messages["info_messages"]);
    } // clearInfoMessages

    /**
     * Checks whether an information message exist.
     *
     * @return boolean
     * Returns true if the information message exists, otherwise false.
     *
     * @see MessageManager::hasErrors()
     * @see MessageManager::hasWarnings()
     * @see MessageManager::hasProgWarnings()
     * @see MessageManager::hasDebugMessages()
     * @see MessageManager::hasBubbleMessages()
     * @see MessageManager::getInfoMessages()
     * @see MessageManager::addInfoMessage()
     *
     * @author Oleg Schildt
     */
    public function hasInfoMessages()
    {
        return (isset($this->messages["info_messages"]) && count($this->messages["info_messages"]) > 0);
    } // hasInfoMessages

    /**
     * Returns the array of information messages if any have been stored.
     *
     * When the messages are requested, it is assumed they will be displayed. Thus,
     * the message array is cleared to avoid displaying of the same messages twice.
     *
     * @return array
     * Returns the array of information messages if any have been stored.
     *
     * @see MessageManager::getErrors()
     * @see MessageManager::getWarnings()
     * @see MessageManager::getProgWarnings()
     * @see MessageManager::getDebugMessages()
     * @see MessageManager::getBubbleMessages()
     * @see MessageManager::hasInfoMessages()
     * @see MessageManager::addInfoMessage()
     *
     * @author Oleg Schildt
     */
    public function getInfoMessages()
    {
        if (!$this->hasInfoMessages()) {
            return [];
        }

        return $this->getMessages($this->messages["info_messages"]);
    } // getInfoMessages

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
     * @see MessageManager::addError()
     * @see MessageManager::addWarning()
     * @see MessageManager::addProgWarning()
     * @see MessageManager::addDebugMessage()
     * @see MessageManager::addInfoMessage()
     * @see MessageManager::getBubbleMessages()
     * @see MessageManager::hasBubbleMessages()
     * @see MessageManager::clearBubbleMessages()
     *
     * @author Oleg Schildt
     */
    public function addBubbleMessage($message, $details = [], $autoclose = true)
    {
        if (empty($message)) {
            return;
        }

        $this->messages["bubble_messages"][$message] = [
            "message" => $message,
            "details" => $details,
            "autoclose" => $autoclose
        ];
    } // addBubbleMessage

    /**
     * Clears the stored information messages.
     *
     * @return void
     *
     * @see MessageManager::clearErrors()
     * @see MessageManager::clearWarnings()
     * @see MessageManager::clearProgWarnings()
     * @see MessageManager::clearDebugMessages()
     * @see MessageManager::clearInfoMessages()
     * @see MessageManager::clearAll()
     * @see MessageManager::addBubbleMessage()
     *
     * @author Oleg Schildt
     */
    public function clearBubbleMessages()
    {
        unset($this->messages["bubble_messages"]);
    } // clearBubbleMessages

    /**
     * Checks whether an information message exist.
     *
     * @return boolean
     * Returns true if the information message exists, otherwise false.
     *
     * @see MessageManager::hasErrors()
     * @see MessageManager::hasWarnings()
     * @see MessageManager::hasProgWarnings()
     * @see MessageManager::hasDebugMessages()
     * @see MessageManager::hasInfoMessages()
     * @see MessageManager::getBubbleMessages()
     * @see MessageManager::addBubbleMessage()
     *
     * @author Oleg Schildt
     */
    public function hasBubbleMessages()
    {
        return (isset($this->messages["bubble_messages"]) && count($this->messages["bubble_messages"]) > 0);
    } // hasBubbleMessages

    /**
     * Returns the array of information messages if any have been stored.
     *
     * When the messages are requested, it is assumed they will be displayed. Thus,
     * the message array is cleared to avoid displaying of the same messages twice.
     *
     * @return array
     * Returns the array of information messages if any have been stored.
     *
     * @see MessageManager::getErrors()
     * @see MessageManager::getWarnings()
     * @see MessageManager::getProgWarnings()
     * @see MessageManager::getDebugMessages()
     * @see MessageManager::getInfoMessages()
     * @see MessageManager::hasBubbleMessages()
     * @see MessageManager::addBubbleMessage()
     *
     * @author Oleg Schildt
     */
    public function getBubbleMessages()
    {
        if (!$this->hasBubbleMessages()) {
            return [];
        }

        return $this->getMessages($this->messages["bubble_messages"]);
    } // getBubbleMessages

    /**
     * Clears all stored messages and active elements.
     *
     * @return void
     *
     * @see MessageManager::clearErrors()
     * @see MessageManager::clearWarnings()
     * @see MessageManager::clearProgWarnings()
     * @see MessageManager::clearDebugMessages()
     * @see MessageManager::clearInfoMessages()
     * @see MessageManager::clearBubbleMessages()
     *
     * @author Oleg Schildt
     */
    public function clearAll()
    {
        $this->clearErrors();
        $this->clearWarnings();
        $this->clearProgWarnings();
        $this->clearDebugMessages();
        $this->clearInfoMessages();
        $this->clearBubbleMessages();
    } // clearAll

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
        return array_values($messages);
    } // getMessages
} // MessageManager
