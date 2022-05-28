<?php
/**
 * This file contains the implementation of the interface ISessionManager
 * in the class SessionManager for session management.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\Interfaces\ISessionManager;

/**
 * Class for session management.
 *
 * @author Oleg Schildt
 */
class SessionManager extends \SessionHandler implements ISessionManager
{
    /**
     * Internal variable for storing the state whether the session is started
     * as readonly.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected static $readonly = false;

    /**
     * Internal variable for storing the current context.
     *
     * If many instances of the application should run in parallel
     * subfolders, and all subfolders are within the same session,
     * and the provider does not let you change the session path,
     * then you can use different $context in each instance to ensure
     * that the session data of these instances does not mix.
     *
     * @var string
     *
     * @see SessionManager::getContext()
     * @see SessionManager::switchContext()
     *
     * @author Oleg Schildt
     */
    protected static $context = "default";

    /**
     * Reimplementation of the method \SessionHandler::close.
     *
     * SessionManager extends the \SessionHandler. You can reimplement this
     * method if wan to change the way the session is handled internally, e.g.
     * stroe the session data in Amazon Redis for quicker access.
     *
     * @return boolean
     * The return value (usually true on success, false on failure).
     * Note this value is returned internally to PHP for processing.
     *
     * @author Oleg Schildt
     */
    public function close(): bool
    {
        return parent::close();
    } // close

    /**
     * Reimplementation of the method \SessionHandler::create_sid.
     *
     * SessionManager extends the \SessionHandler. You can reimplement this
     * method if wan to change the way the session is handled internally, e.g.
     * stroe the session data in Amazon Redis for quicker access.
     *
     * @return string
     * Should return session ID valid for the default session handler.
     *
     * @author Oleg Schildt
     */
    public function create_sid(): string
    {
        $sid = parent::create_sid();

        return $sid;
    } // create_sid

    /**
     * Reimplementation of the method \SessionHandler::destroy.
     *
     * SessionManager extends the \SessionHandler. You can reimplement this
     * method if wan to change the way the session is handled internally, e.g.
     * stroe the session data in Amazon Redis for quicker access.
     *
     * @param string $session_id
     * The session ID being destroyed.
     *
     * @return boolean
     * The return value (usually true on success, false on failure).
     * Note this value is returned internally to PHP for processing.
     *
     * @author Oleg Schildt
     */
    public function destroy($session_id): bool
    {
        return parent::destroy($session_id);
    } // destroy

    /**
     * Reimplementation of the method \SessionHandler::gc.
     *
     * SessionManager extends the \SessionHandler. You can reimplement this
     * method if wan to change the way the session is handled internally, e.g.
     * stroe the session data in Amazon Redis for quicker access.
     *
     * @param string $maxlifetime
     * Sessions that have not updated for the last maxlifetime seconds will be removed.
     *
     * @return int|false
     * The return value (usually positive on success, false on failure).
     * Note this value is returned internally to PHP for processing.
     *
     * @author Oleg Schildt
     */
    public function gc($maxlifetime): int|false
    {
        return parent::gc($maxlifetime);
    } // gc

    /**
     * Reimplementation of the method \SessionHandler::open.
     *
     * SessionManager extends the \SessionHandler. You can reimplement this
     * method if wan to change the way the session is handled internally, e.g.
     * stroe the session data in Amazon Redis for quicker access.
     *
     * @param string $save_path
     * The path where to store/retrieve the session.
     *
     * @param string $session_name
     * The session name.
     *
     * @return boolean
     * The return value (usually true on success, false on failure).
     * Note this value is returned internally to PHP for processing.
     *
     * @author Oleg Schildt
     */
    public function open($save_path, $session_name): bool
    {
        return parent::open($save_path, $session_name);
    } // open

    /**
     * Reimplementation of the method \SessionHandler::read.
     *
     * SessionManager extends the \SessionHandler. You can reimplement this
     * method if wan to change the way the session is handled internally, e.g.
     * stroe the session data in Amazon Redis for quicker access.
     *
     * @param string $session_id
     * The session id to read data for.
     *
     * @return string
     * Returns an encoded string of the read data. If nothing was read,
     * it must return an empty string. Note this value is returned
     * internally to PHP for processing. .
     *
     * @author Oleg Schildt
     */
    public function read($session_id): string
    {
        return parent::read($session_id);
    } // read

    /**
     * Reimplementation of the method \SessionHandler::write.
     *
     * SessionManager extends the \SessionHandler. You can reimplement this
     * method if wan to change the way the session is handled internally, e.g.
     * stroe the session data in Amazon Redis for quicker access.
     *
     * @param string $session_id
     * The session id to write data for.
     *
     * @param string $session_data
     * The encoded session data. This data is the result of the PHP internally
     * encoding the $_SESSION superglobal to a serialized string and passing
     * it as this parameter.
     *
     * @return boolean
     * The return value (usually true on success, false on failure).
     * Note this value is returned internally to PHP for processing.
     *
     * @author Oleg Schildt
     */
    public function write($session_id, $session_data): bool
    {
        return parent::write($session_id, $session_data);
    } // write

    /**
     * Starts the session.
     *
     * @param boolean $readonly
     * This paramters specifies whether the session should be started
     * in the readonly mode or not.
     *
     * The parameter $readonly starts the session in non-blocking
     * readonly mode. It can be used in the asynchronous ajax requests
     * So that they are not blocked by the main process and by each
     * ohter while the write lock is held on the session file.
     *
     * @param string $context
     * The session context.
     *
     * If many instances of the application should run in parallel
     * subfolders, and all subfolders are within the same session,
     * and the provider does not let you to change the session path,
     * then you can use different $context in each instance to ensure
     * that the session data of these instances does not mix.
     *
     * @return boolean
     * Returns true if the session has been successfully started, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function startSession($readonly = false, $context = "default")
    {
        self::$context = $context;
        self::$readonly = $readonly;

        if (!$readonly) {
            return session_start();
        }

        // we read directly from the session file without blocking it

        global $_SESSION;

        $session_path = session_save_path();
        if (empty($session_path)) {
            $session_path = sys_get_temp_dir();
        }

        $session_path = rtrim($session_path, '/\\');

        if (get_cookie(session_name()) == "") {
            session_start();
            session_write_close();
            return false;
        }

        $session_name = preg_replace('/[^\da-z]/i', '', get_cookie(session_name()));

        if (!file_exists($session_path . '/sess_' . $session_name)) {
            session_start();
            session_write_close();
            return false;
        }

        $session_data = file_get_contents($session_path . '/sess_' . $session_name);
        if (empty($session_data)) {
            session_start();
            session_write_close();
            return false;
        }

        $offset = 0;

        while ($offset < strlen($session_data)) {
            if (!strstr(substr($session_data, $offset), "|")) {
                break;
            }

            $pos = strpos($session_data, "|", $offset);
            $num = $pos - $offset;
            $varname = substr($session_data, $offset, $num);
            $offset += $num + 1;
            $data = unserialize(substr($session_data, $offset));
            $_SESSION[$varname] = $data;
            $offset += strlen(serialize($data));
        }

        return true;
    } // startSession

    /**
     * Changes the session context.
     *
     * If many instances of the application should run in parallel
     * subfolders, and all subfolders are within the same session,
     * and the provider does not let you change the session path,
     * then you can use different $context in each instance to ensure
     * that the session data of these instances does not mix.
     *
     * @param string $context
     * The session context.
     *
     * @return void
     *
     * @see SessionManager::getContext()
     *
     * @author Oleg Schildt
     */
    public function switchContext($context)
    {
        self::$context = $context;
    } // switchContext

    /**
     * Returns the current session context.
     *
     * If many instances of the application should run in parallel
     * subfolders, and all subfolders are within the same session,
     * and the provider does not let you to change the session path,
     * then you can use different $context in each instance to ensure
     * that the session data of these instances does not mix.
     *
     * @return string
     * Returns the current session context.
     *
     * @see SessionManager::switchContext()
     *
     * @author Oleg Schildt
     */
    public function getContext()
    {
        return self::$context;
    } // getContext

    /**
     * Saves all unsaved session data and closes the session.
     *
     * @return boolean
     * Returns true if the session has been successfully closed, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function writeCloseSession()
    {
        // in the readonly modus, the session data cannot be modified or
        // deleted globally
        if (self::$readonly) {
            return false;
        }

        return session_write_close();
    } // writeCloseSession

    /**
     * Destroys the session.
     *
     * @return boolean
     * Returns true if the session has been successfully destroyed, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function destroySession()
    {
        // in the readonly modus, the session data cannot be modified or
        // deleted globally
        if (self::$readonly) {
            return false;
        }

        $_SESSION[$this->getContext()] = [];

        return session_destroy();
    } // destroySession

    /**
     * Returns the current session variable name.
     *
     * @return string
     * Returns the current session variable name.
     *
     * @see SessionManager::setSessionName()
     *
     * @author Oleg Schildt
     */
    public function getSessionName()
    {
        return session_name();
    } // getSessionName

    /**
     * Sets the session variable name.
     *
     * @param string $name
     * The new session variable name.
     *
     * @return boolean
     * Returns true if the session variable name has been successfully set, otherwise false.
     *
     * @see SessionManager::getSessionName()
     *
     * @author Oleg Schildt
     */
    public function setSessionName($name)
    {
        return session_name($name);
    } // setSessionName

    /**
     * Returns the ID of the current session.
     *
     * @return string
     * Returns the ID of the current session.
     *
     * @see SessionManager::setSessionId()
     *
     * @author Oleg Schildt
     */
    public function getSessionId()
    {
        if (self::$readonly) {
            return get_cookie(session_name());
        }

        return session_id();
    } // getSessionId

    /**
     * Sets the ID of the current session.
     *
     * @param string $id
     * The new ID of the current session.
     *
     * @return boolean
     * Returns true if the session ID has been successfully set, otherwise false.
     *
     * @see SessionManager::getSessionId()
     *
     * @author Oleg Schildt
     */
    public function setSessionId($id)
    {
        return session_id($id);
    } // setSessionId

    /**
     * Clears the session data.
     *
     * @return boolean
     * Returns true if the session data has been successfully cleared, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function clearSession()
    {
        $_SESSION[$this->getContext()] = [];

        return true;
    } // clearSession

    /**
     * Returns the reference to the array of the session variables.
     *
     * @return array
     * Returns the reference to the array of the session variables.
     *
     * The reason of this interacfe is to wrap the standard session
     * handling to be able to flexibly change the implementation without
     * any change in the code where it is used.
     *
     * Thus, we cannot use $_SESSION directly, a new implementation
     * might not use it at all. But we need a comfort way to set and
     * get the session variables including the multidimesional arrays.
     *
     * Operator overloading through ArrayAccess interface supports
     * only one dimensional arrays, thus, it is not applicable for us.
     *
     * We return the reference to the internal data. It allows setting
     * multidimesional arrays as follows:
     *
     * ```php
     * $smanager->vars()["user"]["name"] = "Alex";
     * $smanager->vars()["user"]["age"] = "22";
     * ```
     *
     * @author Oleg Schildt
     */
    public function &vars()
    {
        if (empty($_SESSION[$this->getContext()])) {
            $_SESSION[$this->getContext()] = [];
        }

        return $_SESSION[$this->getContext()];
    } // getSessionVariables
} // class
