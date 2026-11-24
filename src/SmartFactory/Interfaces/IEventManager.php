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
 * Defines synchronous event dispatch with immediate subscription and suspension changes.
 *
 * Handler exceptions and errors stop dispatch and are propagated unchanged to the caller.
 *
 * @author Oleg Schildt
 */
interface IEventManager
{
    /**
     * Adds an event handler unless the same callable is already registered for this event.
     *
     * Changes take effect immediately, including during an active dispatch. Newly added
     * handlers run after the remaining registered handlers. Closures are identified by
     * object identity; other callables by their function or method and target object/class.
     *
     * @param string $event
     * Nonempty event code. The string "0" is valid.
     *
     * @param callable(string, array<array-key, mixed>): mixed $handler
     * A function, closure, object method, static method or invokable object accepting the
     * event code and event data. Its return value is ignored. A typical signature is:
     *
     * ```php
     * function (string $event, array $parameters) : void;
     * ```
     *
     * - $event - the event code.
     * - $parameters - the data supplied to fireEvent().
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     * If the event code is an empty string.
     *
     * @see IEventManager::deleteHandler()
     * @see IEventManager::deleteHandlers()
     * @see IEventManager::deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function addHandler(string $event, callable $handler): void;

    /**
     * Deletes an event handler if it is registered.
     *
     * Removal takes effect immediately: a pending invocation in an active dispatch
     * is skipped. An invocation already in progress is allowed to finish.
     *
     * @param string $event
     * Nonempty event code. The string "0" is valid.
     *
     * @param callable(string, array<array-key, mixed>): mixed $handler
     * The original closure or the function, method or invokable object to remove.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     * If the event code is an empty string.
     *
     * @see IEventManager::addHandler()
     * @see IEventManager::deleteHandlers()
     * @see IEventManager::deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function deleteHandler(string $event, callable $handler): void;

    /**
     * Deletes all handlers of an event, including pending invocations in active dispatches.
     *
     * @param string $event
     * Nonempty event code. The string "0" is valid.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     * If the event code is an empty string.
     *
     * @see IEventManager::addHandler()
     * @see IEventManager::deleteHandler()
     * @see IEventManager::deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function deleteHandlers(string $event): void;

    /**
     * Deletes all handlers of all events for this instance immediately.
     *
     * Pending invocations in active dispatches are skipped. Suspension state is preserved.
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
     * Suspension takes effect immediately. No further handlers are called in an active
     * dispatch while the event remains suspended; a handler already running can finish.
     *
     * @param string $event
     * Nonempty event code. The string "0" is valid.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     * If the event code is an empty string.
     *
     * @see IEventManager::resumeEvent()
     * @see IEventManager::resumeAllEvents()
     *
     * @author Oleg Schildt
     */
    public function suspendEvent(string $event): void;

    /**
     * Resumes an event immediately without replaying previously skipped invocations.
     *
     * @param string $event
     * Nonempty event code. The string "0" is valid.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     * If the event code is an empty string.
     *
     * @see IEventManager::suspendEvent()
     * @see IEventManager::resumeAllEvents()
     *
     * @author Oleg Schildt
     */
    public function resumeEvent(string $event): void;

    /**
     * Resumes all events for this instance immediately without replaying skipped invocations.
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
     * Fires an event synchronously, invoking handlers in registration order.
     *
     * Subscription changes take effect immediately during dispatch. Removed handlers
     * are skipped, newly registered handlers are appended, and suspension stops further
     * invocations while it remains in effect. Removing and registering a handler again
     * creates a new registration at the end, even if it has already run in this dispatch.
     * Recursive dispatches maintain independent invocation counts.
     *
     * Any exception or error from a handler stops dispatch immediately and is propagated
     * unchanged to the caller. The remaining handlers are not invoked.
     *
     * @param string $event
     * Nonempty event code. The string "0" is valid.
     *
     * @param array<array-key, mixed> $parameters
     * Event data passed to each handler as its second argument.
     *
     * @return int
     * Returns the number of handlers invoked by this dispatch, or zero if none ran.
     *
     * @throws \InvalidArgumentException
     * If the event code is an empty string.
     *
     * @throws \Throwable
     * If a handler throws an exception or error; dispatch stops and the same throwable is propagated.
     *
     * @author Oleg Schildt
     */
    public function fireEvent(string $event, array $parameters): int;
} // IEventManager
