<?php
/**
 * Declares storage-independent request admission for API throttling.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\Interfaces;

/**
 * Atomically enforces every configured rolling-window limit for an opaque caller key.
 *
 * Storage implementations may use files, databases or Redis. Checking limits and recording
 * an allowed request must be one atomic operation across all processes sharing the storage.
 * A limit is [maximum request count, period in seconds]. All limits apply simultaneously
 * to the interval (current time - period, current time]. Rejected requests are not recorded.
 * Clock rollbacks may retain future records conservatively instead of resetting budgets.
 * The abstract base class provides the shared algorithm for backends that only
 * need to implement history reading, persistence and atomic execution.
 *
 * @see \SmartFactory\AbstractRequestThrottlingManager
 *
 * @author Oleg Schildt
 */
interface IRequestThrottlingManager extends IInitable
{
    /**
     * Provides the demo admission policy when no explicit limits are supplied.
     *
     * @var list<array{0: positive-int, 1: positive-int}>
     *
     * @author Oleg Schildt
     */
    public const array DEFAULT_LIMITS = [[3, 1], [15, 10], [1550, 60], [10000, 500]];

    /**
     * Initializes the throttling policy and implementation-specific storage settings.
     *
     * @param array{limits?: list<array{0: positive-int, 1: positive-int}>} $parameters
     * The limits, using positive integer counts and periods in seconds. Omission selects
     * DEFAULT_LIMITS; an empty list disables admission limits. Implementations may require
     * additional entries for their storage connection or file path.
     *
     * @return void
     * Applies the policy without erasing existing shared request history.
     *
     * @throws \InvalidArgumentException
     * If a limit is not an integer pair with positive values or storage settings are invalid.
     *
     * @throws \RuntimeException
     * If the implementation initializes its storage eagerly and that storage is unavailable.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void;

    /**
     * Retrieves all limits currently enforced by the manager.
     *
     * @return list<array{0: positive-int, 1: positive-int}>
     * Returns count/period pairs, or an empty list when limits are disabled.
     *
     * @author Oleg Schildt
     */
    public function getLimits(): array;

    /**
     * Atomically checks every window and records the request only when all windows allow it.
     *
     * @param string $key
     * A nonempty opaque identity, such as a normalized IP address, user ID, API-key identifier
     * or one shared key for a global budget. The same key shares limits across API actions.
     *
     * @return int
     * Returns zero when admitted, or a positive Retry-After delay rounded up to whole seconds
     * until every limit permits another request, assuming no other requests are admitted meanwhile.
     * A rejection must not change request history.
     *
     * @throws \InvalidArgumentException
     * If the caller key is empty.
     *
     * @throws \LogicException
     * If the implementation requires initialization and has not been initialized.
     *
     * @throws \RuntimeException
     * If shared history cannot be read, locked, validated or updated; admission must not be granted.
     *
     * @author Oleg Schildt
     */
    public function registerRequest(string $key): int;
} // IRequestThrottlingManager
