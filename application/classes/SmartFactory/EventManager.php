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
   * @var array
   * Internal array for storing the event handler mappings.
   *
   * @see addHandler
   * @see deleteHandler
   * @see deleteHandlers
   * @see deleteAllHandlers
   *
   * @author Oleg Schildt 
   */
  protected static $event_table = [];

  /**
   * @var array
   * Internal array for storing the suspended events.
   *
   * @see suspendEvent
   * @see resumeEvent
   * @see resumeAllEvents
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
   * ```
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
   * @see deleteHandler
   * @see deleteHandlers
   * @see deleteAllHandlers
   *
   * @author Oleg Schildt 
   */
  public function addHandler($event, $handler)
  {
    if(empty($event)) return false;

    if(!is_callable($handler)) return false;

    $f = new \ReflectionFunction($handler);
    
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
   * @see addHandler
   * @see deleteHandlers
   * @see deleteAllHandlers
   *
   * @author Oleg Schildt 
   */
  public function deleteHandler($event, $handler)
  {
    if(empty($event)) return false;

    if(!is_callable($handler)) return false;

    $f = new \ReflectionFunction($handler);
    
    if(isset(self::$event_table[$event][$f->__toString()])) unset(self::$event_table[$event][$f->__toString()]);
    
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
   * @see addHandler
   * @see deleteHandler
   * @see deleteAllHandlers
   *
   * @author Oleg Schildt 
   */
  public function deleteHandlers($event)
  {
    if(empty($event)) return false;
    
    if(isset(self::$event_table[$event])) unset(self::$event_table[$event]);
    
    return true;
  } // deleteHandlers

  /**
   * Deletes all handlers of all events.
   *
   * @return boolean
   * Returns true if the deletion was successfull, otherwise false.
   *
   * @see addHandler
   * @see deleteHandler
   * @see deleteHandlers
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
   * @see resumeEvent
   * @see resumeAllEvents
   *
   * @author Oleg Schildt 
   */
  public function suspendEvent($event)
  {
    if(empty($event)) return false;
    
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
   * @see suspendEvent
   * @see resumeAllEvents
   *
   * @author Oleg Schildt 
   */
  public function resumeEvent($event)
  {
    if(empty($event)) return false;
    
    if(isset(self::$suspended_events[$event])) unset(self::$suspended_events[$event]);
    
    return true;
  } // resumeEvent

  /**
   * Resumes all previously suspended events.
   *
   * @return boolean
   * Returns true if the suspesion was successfull, otherwise false.
   *
   * @see suspendEvent
   * @see resumeEvent
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
   * @author Oleg Schildt 
   */
  public function fireEvent($event, $parameters)
  {
    if(empty($event)) return false;
    
    if(!empty(self::$suspended_events[$event])) return true;
    
    if(empty(self::$event_table[$event])) return true;
    
    $cnt = 0;
    
    foreach(self::$event_table[$event] as $f)
    {
      $cnt++;
      $f->invoke($event, $parameters);
    }

    return $cnt;
  } // fireEvent
} // EventManager
