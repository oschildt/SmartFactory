<?php
/**
 * Provides storage-independent rolling-window admission for API requests.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\Interfaces\IRequestThrottlingManager;

/**
 * Implements request limits, history expiry and retry delays for interchangeable storage backends.
 *
 * Subclasses supply history reading, persistence and an atomic execution boundary. The boundary
 * must protect the complete read/check/write operation for the same caller key across processes.
 * Caller keys are hashed once and passed explicitly to each storage hook. Rejected requests
 * are not recorded. Backends maintain expiry of inactive callers independently.
 * The interface remains the dependency and factory contract for concrete implementations.
 *
 * @author Oleg Schildt
 */
abstract class AbstractRequestThrottlingManager implements IRequestThrottlingManager
{
    /**
     * Stores maximum-count/period pairs applied simultaneously to each caller.
     *
     * @var list<array{0: positive-int, 1: positive-int}>
     *
     * @author Oleg Schildt
     */
    protected array $limits = self::DEFAULT_LIMITS;

    /**
     * Records whether initialization has successfully applied a policy.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $initialized = false;

    /**
     * Validates and applies rolling-window limits without changing shared request history.
     *
     * Subclasses should validate storage settings before calling this implementation and
     * commit their validated settings afterward, preserving the old configuration on failure.
     *
     * @param array{limits?: list<array{0: positive-int, 1: positive-int}>} $parameters
     * Count/period pairs containing positive integers. Omission selects DEFAULT_LIMITS;
     * an empty list disables limits. Other parameters are reserved for storage implementations.
     *
     * @return void
     * Applies a validated policy and marks the manager initialized; rejected settings change neither.
     *
     * @throws \InvalidArgumentException
     * If limits are not a list of exactly two positive integers per entry.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void
    {
        $limits = array_key_exists("limits", $parameters) ? $parameters["limits"] : self::DEFAULT_LIMITS;

        if (!is_array($limits) || !array_is_list($limits)) {
            throw new \InvalidArgumentException("The limits must be a list of maximum-count/period pairs.");
        }

        foreach ($limits as $limit) {
            if (
                !is_array($limit)
                || !array_is_list($limit)
                || count($limit) !== 2
                || !is_int($limit[0])
                || !is_int($limit[1])
                || $limit[0] <= 0
                || $limit[1] <= 0
            ) {
                throw new \InvalidArgumentException("Each limit must contain a positive integer count and period in seconds.");
            }
        }

        $this->limits = $limits;
        $this->initialized = true;
    } // init

    /**
     * Retrieves the policy applied to every caller key.
     *
     * @return list<array{0: positive-int, 1: positive-int}>
     * Returns count/period pairs, or an empty list when limits are disabled.
     *
     * @author Oleg Schildt
     */
    public function getLimits(): array
    {
        return $this->limits;
    } // getLimits

    /**
     * Reads the clock inside the storage's atomic execution boundary.
     *
     * @return float
     * Returns Unix time in seconds with subsecond precision.
     *
     * @author Oleg Schildt
     */
    protected function getCurrentTime(): float
    {
        return microtime(true);
    } // getCurrentTime

    /**
     * Removes timestamps outside the longest configured window for one caller.
     *
     * @param list<float> $timestamps
     * Validated, chronologically ordered Unix timestamps belonging to one caller.
     *
     * @param float $now
     * A finite, nonnegative reference time in Unix seconds.
     *
     * @return list<float>
     * Returns retained timestamps in their original order, preserving future entries after a clock rollback.
     * With disabled limits, returns the supplied timestamps unchanged.
     *
     * @author Oleg Schildt
     */
    protected function pruneRecords(array $timestamps, float $now): array
    {
        if ($this->limits === []) {
            return $timestamps;
        }

        $longest_period = max(array_column($this->limits, 1));
        $active = [];

        foreach ($timestamps as $timestamp) {
            if (round($now - $timestamp, 6) < $longest_period) {
                $active[] = $timestamp;
            }
        }

        return $active;
    } // pruneRecords

    /**
     * Atomically reserves one request if every rolling-window limit permits it.
     *
     * @param string $key
     * A nonempty caller identity supplied by the request handler.
     *
     * @return int
     * Returns zero when admitted, or the longest required delay rounded up to whole seconds,
     * capped at PHP_INT_MAX. Rejected requests do not change stored history. Accepted requests
     * remove this caller's history older than the longest configured period. Other callers'
     * history and its background expiry remain the responsibility of the storage backend.
     * Future timestamps are retained if the system clock moves backwards.
     * Elapsed times use microsecond precision so fractional window boundaries expire exactly.
     *
     * @throws \InvalidArgumentException
     * If the caller key is empty.
     *
     * @throws \LogicException
     * If init() has not successfully initialized the manager.
     *
     * @throws \RuntimeException
     * If locking, the clock, reading, history validation or persistence fails.
     * Storage failures deny admission; the backend must release any acquired lock.
     *
     * @throws \Throwable
     * If a storage hook or overridden clock propagates another exception or error through atomic execution.
     *
     * @author Oleg Schildt
     */
    public function registerRequest(string $key): int
    {
        if ($key === "") {
            throw new \InvalidArgumentException("The throttling caller key must not be empty.");
        }

        if (!$this->initialized) {
            throw new \LogicException("The throttling manager must be initialized before use.");
        }

        if ($this->limits === []) {
            return 0;
        }

        $client = hash("sha256", $key);

        return $this->executeAtomically($client, function () use ($client): int {
            $now = round($this->getCurrentTime(), 6);

            if (!is_finite($now) || $now < 0) {
                throw new \RuntimeException("The throttling clock returned an invalid timestamp.");
            }

            $timestamps = $this->pruneRecords($this->loadRecords($client), $now);
            $total = count($timestamps);
            $retry_after = 0;

            foreach ($this->limits as [$maximum, $period]) {
                $count = 0;

                foreach ($timestamps as $timestamp) {
                    if (round($now - $timestamp, 6) < $period) {
                        $count++;
                    }
                }

                if ($count >= $maximum) {
                    // Enough of the oldest entries must expire to leave a slot in this window.
                    $delay = ceil($period - round($now - $timestamps[$total - $maximum], 6));
                    $delay = $delay >= PHP_INT_MAX ? PHP_INT_MAX : max(1, (int) $delay);
                    $retry_after = max($retry_after, $delay);
                }
            }

            if ($retry_after > 0) {
                return $retry_after;
            }

            $timestamps[] = $now;

            // A backwards clock adjustment must not break chronological expiry calculations.
            sort($timestamps, SORT_NUMERIC);

            $this->saveRecords($client, $timestamps);

            return 0;
        });
    } // registerRequest

    /**
     * Executes one admission operation while protecting the caller's history against concurrent updates.
     *
     * Subclasses must invoke the callback exactly once after acquiring a lock or equivalent
     * transaction for this key, commit successful updates and release resources in a finally block.
     * Protection must include the first admission for a key with no stored history yet.
     * Shared-file implementations may lock the entire file to preserve unrelated callers' records.
     * Locking only loadRecords() and saveRecords() independently is insufficient: another caller
     * could reserve the same remaining slot between those operations.
     *
     * @param string $key
     * The lowercase SHA-256 caller hash used by every storage hook in this operation.
     *
     * @param callable(): int $operation
     * The complete clock/read/check/write operation to execute inside the protected boundary.
     *
     * @return int
     * Returns the callback's admission result unchanged.
     *
     * @throws \RuntimeException
     * If acquiring, committing or releasing the storage protection fails.
     *
     * @throws \Throwable
     * If the admission callback fails; its failure must propagate after resources are released.
     *
     * @author Oleg Schildt
     */
    abstract protected function executeAtomically(string $key, callable $operation): int;

    /**
     * Reads and validates one caller's retained history inside its atomic execution boundary.
     *
     * @param string $key
     * The lowercase SHA-256 caller hash supplied to executeAtomically().
     *
     * @return list<float>
     * Returns only this caller's timestamps, ordered from oldest to newest.
     * Equal timestamps represent separate requests and must remain separate entries.
     * Timestamps are finite, nonnegative Unix seconds with microsecond precision. Missing history
     * returns an empty array; malformed or unavailable existing history must not be treated as empty.
     *
     * @throws \RuntimeException
     * If history cannot be read or does not satisfy the storage schema and timestamp ordering.
     *
     * @author Oleg Schildt
     */
    abstract protected function loadRecords(string $key): array;

    /**
     * Persists one caller's updated history inside its atomic execution boundary.
     *
     * @param string $key
     * The lowercase SHA-256 caller hash supplied to executeAtomically().
     *
     * @param list<float> $timestamps
     * This caller's ordered timestamps, with expired history already removed.
     * Repeated timestamp values represent separate requests and must not be deduplicated.
     *
     * @return void
     * Replaces this caller's history and makes the update visible before atomic execution finishes.
     * Other callers' active history must be preserved. Inactive history may be expired by the backend.
     *
     * @throws \RuntimeException
     * If persistence fails; incomplete writes must not replace the last valid history.
     *
     * @author Oleg Schildt
     */
    abstract protected function saveRecords(string $key, array $timestamps): void;
} // AbstractRequestThrottlingManager
