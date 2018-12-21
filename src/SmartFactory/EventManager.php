<?php
/**
 * This file contains the implementation of the interface IEventManager
 * in the class EventManager for event management.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\Interfaces\IEventManager;

/**
 * Class for event management.
 *
 * @author Oleg Schildt
 */
class EventManager implements IEventManager
{
    /**
     * Internal array for storing the event handler mappings.
     *
     * @var array
     *
     * @see addHandler()
     * @see deleteHandler()
     * @see deleteHandlers()
     * @see deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    protected static $event_table = [];
    
    /**
     * Internal array for storing the suspended events.
     *
     * @var array
     *
     * @see suspendEvent()
     * @see resumeEvent()
     * @see resumeAllEvents()
     *
     * @author Oleg Schildt
     */
    protected static $suspended_events = [];
    
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
     * Returns true if the adding was successfull, otherwise false.
     *
     * @throws SmartException
     * It might throw the following exceptions in the case of any errors:
     *
     * - missing_data_error - if the event name is not specified.
     * - invalid_data_error - if the event handler is not valid.
     * - system_error - if the creation of the handler fails.
     *
     * @see deleteHandler()
     * @see deleteHandlers()
     * @see deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function addHandler($event, $handler)
    {
        if (empty($event)) {
            throw new SmartException("Event is not specified!", "missing_data_error");
        }
        
        if (!is_callable($handler, true)) {
            throw new SmartException("Event handler is not valid!", "invalid_data_error");
        }
        
        try {
            $f = new \ReflectionFunction($handler);
        } catch (\Exception $ex) {
            throw new SmartException($ex->getMessage(), "system_error");
        }
        
        self::$event_table[$event][$f->__toString()] = $f;
        
        return true;
    } // addEvent
    
    /**
     * Deletes the handler of an event.
     *
     * @param string $event
     * Event code.
     *
     * @param callable $handler
     * The name or definition of the handler function.
     *
     * @return boolean
     * Returns true if the deletion was successfull, otherwise false.
     *
     * @throws SmartException
     * It might throw the following exceptions in the case of any errors:
     *
     * - missing_data_error - if the event name is not specified.
     * - invalid_data_error - if the event handler is not valid.
     * - system_error - if the creation of the handler fails.
     *
     * @see addHandler()
     * @see deleteHandlers()
     * @see deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function deleteHandler($event, $handler)
    {
        if (empty($event)) {
            throw new SmartException("Event is not specified!", "missing_data_error");
        }
        
        if (!is_callable($handler)) {
            throw new SmartException("Event handler is not valid!", "invalid_data_error");
        }
        
        try {
            $f = new \ReflectionFunction($handler);
        } catch (\Exception $ex) {
            throw new SmartException($ex->getMessage(), "system_error");
        }
        
        if (isset(self::$event_table[$event][$f->__toString()])) {
            unset(self::$event_table[$event][$f->__toString()]);
        }
        
        return true;
    } // deleteEvent
    
    /**
     * Deletes all handlers of an event.
     *
     * @param string $event
     * Event code.
     *
     * @return boolean
     * Returns true if the deletion was successfull, otherwise false.
     *
     * @throws SmartException
     * It might throw the following exceptions in the case of any errors:
     *
     * - missing_data_error - if the event name is not specified.
     *
     * @see addHandler()
     * @see deleteHandler()
     * @see deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function deleteHandlers($event)
    {
        if (empty($event)) {
            throw new SmartException("Event is not specified!", "missing_data_error");
        }
        
        if (isset(self::$event_table[$event])) {
            unset(self::$event_table[$event]);
        }
        
        return true;
    } // deleteHandlers
    
    /**
     * Deletes all handlers of all events.
     *
     * @return boolean
     * Returns true if the deletion was successfull, otherwise false.
     *
     * @see addHandler()
     * @see deleteHandler()
     * @see deleteHandlers()
     *
     * @author Oleg Schildt
     */
    public function deleteAllHandlers()
    {
        self::$event_table = [];
        
        return true;
    } // deleteAllHandlers
    
    /**
     * Suspends an event.
     *
     * If an event is suspended, its handlers are not called when the event is fired.
     *
     * @param string $event
     * Event code.
     *
     * @return boolean
     * Returns true if the suspesion was successfull, otherwise false.
     *
     * @throws SmartException
     * It might throw the following exceptions in the case of any errors:
     *
     * - missing_data_error - if the event name is not specified.
     *
     * @see resumeEvent()
     * @see resumeAllEvents()
     *
     * @author Oleg Schildt
     */
    public function suspendEvent($event)
    {
        if (empty($event)) {
            throw new SmartException("Event is not specified!", "missing_data_error");
        }
        
        self::$suspended_events[$event] = $event;
        
        return true;
    } // suspendEvent
    
    /**
     * Resumes a previously suspended event.
     *
     * @param string $event
     * Event code.
     *
     * @return boolean
     * Returns true if the suspesion was successfull, otherwise false.
     *
     * @throws SmartException
     * It might throw the following exceptions in the case of any errors:
     *
     * - missing_data_error - if the event name is not specified.
     *
     * @see suspendEvent()
     * @see resumeAllEvents()
     *
     * @author Oleg Schildt
     */
    public function resumeEvent($event)
    {
        if (empty($event)) {
            throw new SmartException("Event is not specified!", "missing_data_error");
        }
        
        if (isset(self::$suspended_events[$event])) {
            unset(self::$suspended_events[$event]);
        }
        
        return true;
    } // resumeEvent
    
    /**
     * Resumes all previously suspended events.
     *
     * @return boolean
     * Returns true if the suspesion was successfull, otherwise false.
     *
     * @see suspendEvent()
     * @see resumeEvent()
     *
     * @author Oleg Schildt
     */
    public function resumeAllEvents()
    {
        self::$suspended_events = [];
        
        return true;
    } // resumeAllEvents
    
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
     * Returns number of the handlers called for this event.
     *
     * @throws SmartException
     * It might throw the following exceptions in the case of any errors:
     *
     * - missing_data_error - if the event name is not specified.
     * - system_error - if the creation of the handler fails.
     *
     * @author Oleg Schildt
     */
    public function fireEvent($event, $parameters)
    {
        if (empty($event)) {
            throw new SmartException("Event is not specified!", "missing_data_error");
        }
        
        if (!empty(self::$suspended_events[$event])) {
            return true;
        }
        
        if (empty(self::$event_table[$event])) {
            return true;
        }
        
        $cnt = 0;
        
        try {
            foreach (self::$event_table[$event] as $f) {
                $cnt++;
                $f->invoke($event, $parameters);
            }
        } catch (\Exception $ex) {
            throw new SmartException($ex->getMessage(), "system_error");
        }
        
        return $cnt;
    } // fireEvent
} // EventManager
