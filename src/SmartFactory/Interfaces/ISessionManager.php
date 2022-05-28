<?php
/**
 * This file contains the declaration of the interface ISessionManager for working with sessions.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\Interfaces;

/**
 * Interface for working with sessions.
 *
 * @author Oleg Schildt
 */
interface ISessionManager
{
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
    public function startSession($readonly = false, $context = "default");
    
    /**
     * Changes the session context.
     *
     * If many instances of the application should run in parallel
     * subfolders, and all subfolders are within the same session,
     * and the provider does not let you to change the session path,
     * then you can use different $context in each instance to ensure
     * that the session data of these instances does not mix.
     *
     * @param string $context
     * The session context.
     *
     * @return void
     *
     * @see ISessionManager::getContext()
     *
     * @author Oleg Schildt
     */
    public function switchContext($context);
    
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
     * @see ISessionManager::switchContext()
     *
     * @author Oleg Schildt
     */
    public function getContext();
    
    /**
     * Saves all unsaved session data and closes the session.
     *
     * @return boolean
     * Returns true if the session has been successfully closed, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function writeCloseSession();
    
    /**
     * Destroys the session.
     *
     * @return boolean
     * Returns true if the session has been successfully destroyed, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function destroySession();
    
    /**
     * Returns the current session variable name.
     *
     * @return string
     * Returns the current session variable name.
     *
     * @see ISessionManager::setSessionName()
     *
     * @author Oleg Schildt
     */
    public function getSessionName();
    
    /**
     * Sets the session variable name.
     *
     * @param string $name
     * The new session variable name.
     *
     * @return boolean
     * Returns true if the session variable name has been successfully set, otherwise false.
     *
     * @see ISessionManager::getSessionName()
     *
     * @author Oleg Schildt
     */
    public function setSessionName($name);
    
    /**
     * Returns the ID of the current session.
     *
     * @return string
     * Returns the ID of the current session.
     *
     * @see ISessionManager::setSessionId()
     *
     * @author Oleg Schildt
     */
    public function getSessionId();
    
    /**
     * Sets the ID of the current session.
     *
     * @param string $id
     * The new ID of the current session.
     *
     * @return boolean
     * Returns true if the session ID has been successfully set, otherwise false.
     *
     * @see ISessionManager::getSessionId()
     *
     * @author Oleg Schildt
     */
    public function setSessionId($id);
    
    /**
     * Clears the session data.
     *
     * @return boolean
     * Returns true if the session data has been successfully cleared, otherwise false.
     *
     * @author Oleg Schildt
     */
    public function clearSession();
    
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
    public function &vars();
} // ISessionManager
