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
     * - $parameters - parameters passed by the firing of the event.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @see IEventManager::deleteHandler()
     * @see IEventManager::deleteHandlers()
     * @see IEventManager::deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function addHandler(string $event, callable $handler): void;

    /**
     * Deletes the handler of an event.
     *
     * @param string $event
     * Event code.
     *
     * @param callable $handler
     * The name or definition of the handler function. otherwise false.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @see IEventManager::addHandler()
     * @see IEventManager::deleteHandlers()
     * @see IEventManager::deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function deleteHandler(string $event, callable $handler): void;

    /**
     * Deletes all handlers of an event.
     *
     * @param string $event
     * Event code.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @see IEventManager::addHandler()
     * @see IEventManager::deleteHandler()
     * @see IEventManager::deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function deleteHandlers(string $event): void;

    /**
     * Deletes all handlers of all events.
     *
     * @return void
     *
     * @see IEventManager::addHandler()
     * @see IEventManager::deleteHandler()
     * @see IEventManager::deleteHandlers()
     *
     * @author Oleg Schildt
     */
    public function deleteAllHandlers(): void;

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
     * It might throw an exception in the case of any errors.
     *
     * @see IEventManager::resumeEvent()
     * @see IEventManager::resumeAllEvents()
     *
     * @author Oleg Schildt
     */
    public function suspendEvent(string $event): void;

    /**
     * Resumes a previously suspended event.
     *
     * @param string $event
     * Event code.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @see IEventManager::suspendEvent()
     * @see IEventManager::resumeAllEvents()
     *
     * @author Oleg Schildt
     */
    public function resumeEvent(string $event): void;

    /**
     * Resumes all previously suspended events.
     *
     * @return void
     *
     * @see IEventManager::suspendEvent()
     * @see IEventManager::resumeEvent()
     *
     * @author Oleg Schildt
     */
    public function resumeAllEvents(): void;

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
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function fireEvent(string $event, array $parameters): int;
} // IEventManager
