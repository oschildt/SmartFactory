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

use \SmartFactory\Interfaces\IEventManager;

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
     * @see EventManager::addHandler()
     * @see EventManager::deleteHandler()
     * @see EventManager::deleteHandlers()
     * @see EventManager::deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    protected static array $event_table = [];
    
    /**
     * Internal array for storing the suspended events.
     *
     * @var array
     *
     * @see EventManager::suspendEvent()
     * @see EventManager::resumeEvent()
     * @see EventManager::resumeAllEvents()
     *
     * @author Oleg Schildt
     */
    protected static array $suspended_events = [];
    
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
     * @return void
     *
     * @throws \Exception
     * It might throw the following exceptions in the case of any errors:
     *
     * - if the event name is not specified.
     * - if the event handler is not valid.
     * - if the creation of the handler fails.
     *
     * @see EventManager::deleteHandler()
     * @see EventManager::deleteHandlers()
     * @see EventManager::deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function addHandler(string $event, callable $handler): void
    {
        if (empty($event)) {
            throw new \Exception("Event is not specified!");
        }
        
        if (!is_callable($handler, true)) {
            throw new \Exception("Event handler is not valid!");
        }
        
        $f = new \ReflectionFunction($handler);
        
        self::$event_table[$event][$f->__toString()] = $f;
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
     * @return void
     *
     * @throws \Exception
     * It might throw the following exceptions in the case of any errors:
     *
     * - if the event name is not specified.
     * - if the event handler is not valid.
     * - if the creation of the handler fails.
     *
     * @see EventManager::addHandler()
     * @see EventManager::deleteHandlers()
     * @see EventManager::deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function deleteHandler(string $event, callable $handler): void
    {
        if (empty($event)) {
            throw new \Exception("Event is not specified!");
        }
        
        if (!is_callable($handler)) {
            throw new \Exception("Event handler is not valid!");
        }
        
        $f = new \ReflectionFunction($handler);
        
        if (isset(self::$event_table[$event][$f->__toString()])) {
            unset(self::$event_table[$event][$f->__toString()]);
        }
    } // deleteEvent
    
    /**
     * Deletes all handlers of an event.
     *
     * @param string $event
     * Event code.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw the following exceptions in the case of any errors:
     *
     * - if the event name is not specified.
     *
     * @see EventManager::addHandler()
     * @see EventManager::deleteHandler()
     * @see EventManager::deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function deleteHandlers(string $event): void
    {
        if (empty($event)) {
            throw new \Exception("Event is not specified!");
        }
        
        if (isset(self::$event_table[$event])) {
            unset(self::$event_table[$event]);
        }
    } // deleteHandlers
    
    /**
     * Deletes all handlers of all events.
     *
     * @return void
     * Returns true if the deletion was successful, otherwise false.
     *
     * @see EventManager::addHandler()
     * @see EventManager::deleteHandler()
     * @see EventManager::deleteHandlers()
     *
     * @author Oleg Schildt
     */
    public function deleteAllHandlers(): void
    {
        self::$event_table = [];
    } // deleteAllHandlers
    
    /**
     * Suspends an event.
     *
     * If an event is suspended, its handlers are not called when the event is fired.
     *
     * @param string $event
     * Event code.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw the following exceptions in the case of any errors:
     *
     * - if the event name is not specified.
     *
     * @see EventManager::resumeEvent()
     * @see EventManager::resumeAllEvents()
     *
     * @author Oleg Schildt
     */
    public function suspendEvent(string $event): void
    {
        if (empty($event)) {
            throw new \Exception("Event is not specified!");
        }
        
        self::$suspended_events[$event] = $event;
    } // suspendEvent
    
    /**
     * Resumes a previously suspended event.
     *
     * @param string $event
     * Event code.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw the following exceptions in the case of any errors:
     *
     * - if the event name is not specified.
     *
     * @see EventManager::suspendEvent()
     * @see EventManager::resumeAllEvents()
     *
     * @author Oleg Schildt
     */
    public function resumeEvent(string $event): void
    {
        if (empty($event)) {
            throw new \Exception("Event is not specified!");
        }
        
        if (isset(self::$suspended_events[$event])) {
            unset(self::$suspended_events[$event]);
        }
    } // resumeEvent
    
    /**
     * Resumes all previously suspended events.
     *
     * @return void
     *
     * @see EventManager::suspendEvent()
     * @see EventManager::resumeEvent()
     *
     * @author Oleg Schildt
     */
    public function resumeAllEvents(): void
    {
        self::$suspended_events = [];
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
     * @throws \Exception
     * It might throw the following exceptions in the case of any errors:
     *
     * - if the event name is not specified.
     * - if the creation of the handler fails.
     *
     * @author Oleg Schildt
     */
    public function fireEvent(string $event, array $parameters): int
    {
        if (empty($event)) {
            throw new \Exception("Event is not specified!");
        }
        
        if (!empty(self::$suspended_events[$event])) {
            return true;
        }
        
        if (empty(self::$event_table[$event])) {
            return true;
        }
        
        $cnt = 0;
        
        foreach (self::$event_table[$event] as $f) {
            $cnt++;
            $f->invoke($event, $parameters);
        }
        
        return $cnt;
    } // fireEvent
} // EventManager
