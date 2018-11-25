<?php
/**
 * This file contains the declaration of the interface IEventManager for event management.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\Interfaces;

/**
 * Interface for event management.
 *
 * @author Oleg Schildt
 */
interface IEventManager
{
    /**
     * Adds the handler of an event.
     *
     * @param string $event
     * Event code.
     *
     * @param callable $handler
     * The name or definition of the handler function. The signature of
     * this function is:
     *
     * ```php
     * function (string $event, array $parameters) : void;
     * ```
     *
     * - $event - the event code.
     *
     * - $parameters - parameters passed by the firing of the event.
     *
     * @return boolean
     * Should return true if the adding was successfull, otherwise false.
     *
     * @see deleteHandler()
     * @see deleteHandlers()
     * @see deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function addHandler($event, $handler);
    
    /**
     * Deletes the handler of an event.
     *
     * @param string $event
     * Event code.
     *
     * @param callable $handler
     * The name or definition of the handler function. therwise false.
     *
     * @return boolean
     * Should return true if the deletion was successfull, otherwise false.
     *
     * @see addHandler()
     * @see deleteHandlers()
     * @see deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function deleteHandler($event, $handler);
    
    /**
     * Deletes all handlers of an event.
     *
     * @param string $event
     * Event code.
     *
     * @return boolean
     * Should return true if the deletion was successfull, otherwise false.
     *
     * @see addHandler()
     * @see deleteHandler()
     * @see deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function deleteHandlers($event);
    
    /**
     * Deletes all handlers of all events.
     *
     * @return boolean
     * Should return true if the deletion was successfull, otherwise false.
     *
     * @see addHandler()
     * @see deleteHandler()
     * @see deleteHandlers()
     *
     * @author Oleg Schildt
     */
    public function deleteAllHandlers();
    
    /**
     * Suspends an event.
     *
     * If an event is suspended, its handlers are not called when the event is fired.
     *
     * @param string $event
     * Event code.
     *
     * @return boolean
     * Should return true if the suspesion was successfull, otherwise false.
     *
     * @see resumeEvent()
     * @see resumeAllEvents()
     *
     * @author Oleg Schildt
     */
    public function suspendEvent($event);
    
    /**
     * Resumes a previously suspended event.
     *
     * @param string $event
     * Event code.
     *
     * @return boolean
     * Should return true if the suspesion was successfull, otherwise false.
     *
     * @see suspendEvent()
     * @see resumeAllEvents()
     *
     * @author Oleg Schildt
     */
    public function resumeEvent($event);
    
    /**
     * Resumes all previously suspended events.
     *
     * @return boolean
     * Should return true if the suspesion was successfull, otherwise false.
     *
     * @see suspendEvent()
     * @see resumeEvent()
     *
     * @author Oleg Schildt
     */
    public function resumeAllEvents();
    
    /**
     * Fires and event.
     *
     * @param string $event
     * Event code.
     *
     * @param array $parameters
     * Event code.
     *
     * @return int
     * Should return number of the handlers called for this event.
     *
     * @author Oleg Schildt
     */
    public function fireEvent($event, $parameters);
} // IEventManager
