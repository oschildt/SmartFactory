<?php
/**
 * This file contains the declaration of the interface IRequestHandler for handling the API requests.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\Interfaces;

/**
 * Initializes API handlers and optionally supplies a request-throttling service.
 *
 * @author Oleg Schildt
 */
interface IRequestHandler extends IInitable
{
    /**
     * Applies optional dependencies to the request handler.
     *
     * @param array{throttling_manager?: IRequestThrottlingManager|null, user_identificator_getter?: (callable(): (string|int|null))|null} $parameters
     * An optional throttling manager. Null disables throttling; an omitted entry keeps the current manager.
     * The optional zero-argument identity getter is evaluated on each throttled request; a nonempty
     * string or integer identifies the user, while null or an empty string falls back to the peer IP.
     * Omitted getter settings retain the current getter; null removes it.
     * Implementations may support additional application parameters.
     *
     * @return void
     * Updates the supplied dependencies without requiring initialization for unthrottled requests.
     *
     * @throws \InvalidArgumentException
     * If throttling_manager is neither null nor an IRequestThrottlingManager implementation,
     * or user_identificator_getter is neither callable nor null.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void;

    /**
     * Assigns the already initialized service that limits incoming requests.
     *
     * @param IRequestThrottlingManager|null $throttling_manager
     * The throttling service, or null to disable throttling.
     *
     * @return void
     * Replaces the manager without reinitializing it or clearing its shared request history.
     *
     * @author Oleg Schildt
     */
    public function setRequestThrottlingManager(?IRequestThrottlingManager $throttling_manager): void;

    /**
     * Retrieves the service currently used to limit incoming requests.
     *
     * @return IRequestThrottlingManager|null
     * Returns the configured service, or null when throttling is disabled.
     *
     * @author Oleg Schildt
     */
    public function getRequestThrottlingManager(): ?IRequestThrottlingManager;

    /**
     * Method that is called to handle the request.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function handleRequest(): void;
} // IRequestHandler
