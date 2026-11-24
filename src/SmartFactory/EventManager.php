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
 * Dispatches events synchronously using subscriptions and suspension state owned by this instance.
 *
 * @author Oleg Schildt
 */
class EventManager implements IEventManager
{
    /**
     * Stores handlers by event code and callable identity in registration order.
     *
     * @var array<int|string, array<string, array{id: int, handler: \Closure(string, array<array-key, mixed>): mixed}>>
     *
     * @see EventManager::addHandler()
     * @see EventManager::deleteHandler()
     * @see EventManager::deleteHandlers()
     * @see EventManager::deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    protected array $event_table = [];

    /**
     * Stores the event codes suspended for this instance.
     *
     * @var array<int|string, true>
     *
     * @see EventManager::suspendEvent()
     * @see EventManager::resumeEvent()
     * @see EventManager::resumeAllEvents()
     *
     * @author Oleg Schildt
     */
    protected array $suspended_events = [];

    /**
     * Monotonically increasing registration ID, including across subscription resets.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $next_handler_id = 0;

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
     *
     * - $parameters - the data supplied to fireEvent().
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     * If the event code is an empty string.
     *
     * @see EventManager::deleteHandler()
     * @see EventManager::deleteHandlers()
     * @see EventManager::deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function addHandler(string $event, callable $handler): void
    {
        if ($event === '') {
            throw new \InvalidArgumentException("Event is not specified!");
        }

        $key = $this->getHandlerKey($handler);

        if (isset($this->event_table[$event][$key])) {
            return;
        }

        $this->event_table[$event][$key] = [
            'id' => ++$this->next_handler_id,
            'handler' => \Closure::fromCallable($handler),
        ];
    } // addHandler

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
     * @see EventManager::addHandler()
     * @see EventManager::deleteHandlers()
     * @see EventManager::deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function deleteHandler(string $event, callable $handler): void
    {
        if ($event === '') {
            throw new \InvalidArgumentException("Event is not specified!");
        }

        $key = $this->getHandlerKey($handler);

        unset($this->event_table[$event][$key]);

        if (empty($this->event_table[$event])) {
            unset($this->event_table[$event]);
        }
    } // deleteHandler

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
     * @see EventManager::addHandler()
     * @see EventManager::deleteHandler()
     * @see EventManager::deleteAllHandlers()
     *
     * @author Oleg Schildt
     */
    public function deleteHandlers(string $event): void
    {
        if ($event === '') {
            throw new \InvalidArgumentException("Event is not specified!");
        }

        unset($this->event_table[$event]);
    } // deleteHandlers

    /**
     * Deletes all handlers of all events for this instance immediately.
     *
     * Pending invocations in active dispatches are skipped. Suspension state is preserved.
     *
     * @return void
     *
     * @see EventManager::addHandler()
     * @see EventManager::deleteHandler()
     * @see EventManager::deleteHandlers()
     *
     * @author Oleg Schildt
     */
    public function deleteAllHandlers(): void
    {
        $this->event_table = [];
    } // deleteAllHandlers

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
     * @see EventManager::resumeEvent()
     * @see EventManager::resumeAllEvents()
     *
     * @author Oleg Schildt
     */
    public function suspendEvent(string $event): void
    {
        if ($event === '') {
            throw new \InvalidArgumentException("Event is not specified!");
        }

        $this->suspended_events[$event] = true;
    } // suspendEvent

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
     * @see EventManager::suspendEvent()
     * @see EventManager::resumeAllEvents()
     *
     * @author Oleg Schildt
     */
    public function resumeEvent(string $event): void
    {
        if ($event === '') {
            throw new \InvalidArgumentException("Event is not specified!");
        }

        unset($this->suspended_events[$event]);
    } // resumeEvent

    /**
     * Resumes all events for this instance immediately without replaying skipped invocations.
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
        $this->suspended_events = [];
    } // resumeAllEvents

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
    public function fireEvent(string $event, array $parameters): int
    {
        if ($event === '') {
            throw new \InvalidArgumentException("Event is not specified!");
        }

        $cnt = 0;
        $last_id = 0;
        $pending = $this->event_table[$event] ?? [];

        while ($pending !== []) {
            foreach ($pending as $key => $registration) {
                if (isset($this->suspended_events[$event])) {
                    return $cnt;
                }

                // A removed and re-added callable is a new registration, not this pending one.
                if (($this->event_table[$event][$key]['id'] ?? null) !== $registration['id']) {
                    continue;
                }

                $last_id = $registration['id'];

                ($registration['handler'])($event, $parameters);

                $cnt++;
            }

            // Pick up additions in registration order without invoking earlier registrations twice.
            $pending = array_filter(
                $this->event_table[$event] ?? [],
                static fn(array $registration): bool => $registration['id'] > $last_id
            );
        }

        return $cnt;
    } // fireEvent

    /**
     * Builds an identity key without conflating distinct closure or target objects.
     *
     * Object references retained by registered closures prevent object ID reuse while
     * their registrations exist. Reflection resolves declared function/method names and
     * class aliases while preserving the called class of static methods and the spelling
     * of dynamic method names passed to __call() or __callStatic().
     * An explicit [$closure, '__invoke'] callable has the same identity as that closure.
     *
     * @param callable(string, array<array-key, mixed>): mixed $handler
     * The callable whose registration identity is required.
     *
     * @return string
     * Returns a key identifying a closure object, object method, static method or function.
     *
     * @author Oleg Schildt
     */
    protected function getHandlerKey(callable $handler): string
    {
        if (is_array($handler) && $handler[0] instanceof \Closure && strcasecmp($handler[1], '__invoke') === 0) {
            $handler = $handler[0];
        }

        if ($handler instanceof \Closure) {
            return 'closure:' . spl_object_id($handler);
        }

        $reflection = new \ReflectionFunction(\Closure::fromCallable($handler));
        $object = $reflection->getClosureThis();
        $class = $reflection->getClosureCalledClass();
        $name = $reflection->getName();

        if ($object !== null) {
            return 'method:' . spl_object_id($object) . ':' . $name;
        }

        if ($class !== null) {
            return 'static:' . strtolower($class->getName()) . '::' . $name;
        }

        return 'function:' . $name;
    } // getHandlerKey
} // EventManager
