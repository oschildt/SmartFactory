<?php
/**
 * Provides Redis storage for the common rolling-window throttling algorithm.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

/**
 * Stores each caller's JSON history behind an expiring, owner-checked Redis lock.
 *
 * Requires the optional phpredis extension and a configured Redis connection. Lua guards
 * reads, writes and lock release so an expired owner cannot overwrite another admission.
 * Uses the Redis server clock and expires inactive history after its longest retained window.
 * The client remains owned by the application and is never reconfigured or disconnected.
 *
 * @link markdown:RedisRequestThrottlingManager.md Important notes
 *
 * @author Oleg Schildt
 */
class RedisRequestThrottlingManager extends AbstractRequestThrottlingManager
{
    /**
     * Stores the application-owned phpredis client.
     *
     * @var \Redis|null
     *
     * @author Oleg Schildt
     */
    protected ?\Redis $redis = null;

    /**
     * Separates the application and policy from other Redis data.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $key_prefix = "smartfactory:throttling:";

    /**
     * Bounds how long an abandoned caller lock survives, in milliseconds.
     *
     * @var positive-int
     *
     * @author Oleg Schildt
     */
    protected int $lock_ttl_ms = 10000;

    /**
     * Bounds contention retries, in milliseconds, excluding individual network calls.
     *
     * @var non-negative-int
     *
     * @author Oleg Schildt
     */
    protected int $lock_timeout_ms = 3000;

    /**
     * Sets the interval between contended lock attempts, in milliseconds.
     *
     * @var positive-int
     *
     * @author Oleg Schildt
     */
    protected int $lock_retry_interval_ms = 10;

    /**
     * Identifies the caller currently protected by this instance.
     *
     * @var string|null
     *
     * @author Oleg Schildt
     */
    private ?string $active_key = null;

    /**
     * Identifies the unique owner of the current caller lock.
     *
     * @var string|null
     *
     * @author Oleg Schildt
     */
    private ?string $active_token = null;

    /**
     * Configures the client, namespace, contention settings and inherited rolling-window policy.
     *
     * @param array{redis: \Redis, key_prefix?: non-empty-string, limits?: list<array{0: positive-int, 1: positive-int}>, lock_ttl_ms?: positive-int, lock_timeout_ms?: non-negative-int, lock_retry_interval_ms?: positive-int} $parameters
     * A phpredis client configured by the application. The default prefix is smartfactory:throttling:.
     * Lock defaults are a 10000 ms lease, 3000 ms contention timeout and 10 ms retry interval.
     * Each lock setting is an integer no greater than 2147483647. A zero timeout permits one attempt.
     * Omitted limits use DEFAULT_LIMITS; an empty list disables all storage access.
     *
     * @return void
     * Applies validated settings without connecting or changing existing history or client options.
     * Invalid initialization preserves the previous configuration. See RedisRequestThrottlingManager.md.
     *
     * @throws \InvalidArgumentException
     * If redis is not a phpredis client, the prefix is empty, a lock setting is outside its range,
     * or the inherited limit policy is invalid.
     *
     * @throws \LogicException
     * If initialization is attempted during an admission operation.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void
    {
        if ($this->active_key !== null) {
            throw new \LogicException("The throttling manager cannot be reinitialized during admission.");
        }

        $redis = $parameters["redis"] ?? null;
        $prefix = array_key_exists("key_prefix", $parameters) ? $parameters["key_prefix"] : "smartfactory:throttling:";
        $settings = ["lock_ttl_ms" => 10000, "lock_timeout_ms" => 3000, "lock_retry_interval_ms" => 10];

        if (!$redis instanceof \Redis) {
            throw new \InvalidArgumentException("The redis parameter must be a phpredis Redis instance.");
        }

        if (!is_string($prefix) || $prefix === "") {
            throw new \InvalidArgumentException("The Redis throttling key prefix must be a nonempty string.");
        }

        foreach ($settings as $name => $default) {
            $value = array_key_exists($name, $parameters) ? $parameters[$name] : $default;
            $minimum = $name === "lock_timeout_ms" ? 0 : 1;

            if (!is_int($value) || $value < $minimum || $value > 2147483647) {
                throw new \InvalidArgumentException("The " . $name . " parameter is outside its supported integer range.");
            }

            $settings[$name] = $value;
        }

        parent::init($parameters);

        $this->redis = $redis;
        $this->key_prefix = $prefix;
        $this->lock_ttl_ms = $settings["lock_ttl_ms"];
        $this->lock_timeout_ms = $settings["lock_timeout_ms"];
        $this->lock_retry_interval_ms = $settings["lock_retry_interval_ms"];
    } // init

    /**
     * Verifies that the application client can execute immediate Lua commands safely.
     *
     * @return \Redis
     * Returns the connected client in ATOMIC mode. Client prefixes are applied by phpredis to Lua KEYS.
     * Lua arguments and stored JSON are independent of the client's serializer and compression options.
     *
     * @throws \LogicException
     * If the client is missing, disconnected, or inside MULTI or a pipeline.
     *
     * @author Oleg Schildt
     */
    private function client(): \Redis
    {
        if ($this->redis === null || !$this->redis->isConnected()) {
            throw new \LogicException("Redis throttling requires a connected phpredis client.");
        }

        if ($this->redis->getMode() !== \Redis::ATOMIC) {
            throw new \LogicException("Redis throttling cannot run inside MULTI or a pipeline.");
        }

        return $this->redis;
    } // client

    /**
     * Builds the caller's history and lock keys in a common Redis hash tag.
     *
     * @param string $key
     * The lowercase SHA-256 caller hash supplied by the base algorithm.
     *
     * @return array{0: string, 1: string}
     * Returns the lock key followed by the history key, before any phpredis client prefix.
     *
     * @author Oleg Schildt
     */
    private function keys(string $key): array
    {
        $base = $this->key_prefix . "{" . $key . "}";

        return [$base . ":lock", $base . ":history"];
    } // keys

    /**
     * Serializes a caller's admission and releases only the lock owned by this operation.
     *
     * @param string $key
     * The lowercase SHA-256 caller hash supplied by the common algorithm.
     *
     * @param callable(): int $operation
     * The clock/read/check/write operation invoked exactly once after lock acquisition.
     *
     * @return int
     * Returns admission or retry only after verifying ownership and releasing the lock.
     *
     * @throws \LogicException
     * If the client is unavailable, in queued mode, or admission is nested.
     *
     * @throws \RuntimeException
     * If Redis commands fail, lock contention exceeds the timeout, or the lease expires.
     * An expired owner cannot write history or release a replacement owner's lock.
     *
     * @throws \Throwable
     * If token generation or the callback fails. The original failure survives cleanup failures.
     * A write whose response is lost may consume a slot without granting admission.
     *
     * @author Oleg Schildt
     */
    protected function executeAtomically(string $key, callable $operation): int
    {
        if ($this->active_key !== null) {
            throw new \LogicException("Nested throttling admission operations are not supported.");
        }

        $redis = $this->client();
        [$lock] = $this->keys($key);
        $token = bin2hex(random_bytes(24));
        $started = hrtime(true);
        $failure = null;
        $acquired = false;
        $acquire_script = 'return redis.call("SET", KEYS[1], ARGV[1], "NX", "PX", ARGV[2]) and 1 or 0';
        $release_script = 'if redis.call("GET", KEYS[1]) == ARGV[1] then return redis.call("DEL", KEYS[1]) end return 0';

        try {
            do {
                $result = $redis->eval($acquire_script, [$lock, $token, $this->lock_ttl_ms], 1);

                if ($result === 1) {
                    $acquired = true;

                    break;
                }

                if ($result !== 0) {
                    throw new \RuntimeException("The Redis throttling lock could not be acquired.");
                }

                $remaining_ms = $this->lock_timeout_ms - (hrtime(true) - $started) / 1000000;

                if ($remaining_ms <= 0) {
                    throw new \RuntimeException("The Redis throttling lock wait timed out.");
                }

                usleep((int) min($remaining_ms * 1000, $this->lock_retry_interval_ms * 1000));
            } while (true);

            $this->active_key = $key;
            $this->active_token = $token;

            return $operation();
        } catch (\RedisException $exception) {
            $failure = new \RuntimeException("The Redis throttling storage operation failed.", 0, $exception);

            throw $failure;
        } catch (\Throwable $exception) {
            $failure = $exception;

            throw $exception;
        } finally {
            $this->active_key = null;
            $this->active_token = null;

            try {
                // Also attempt owner-checked release after an ambiguous acquisition response.
                $released = $redis->eval($release_script, [$lock, $token], 1);

                if ($acquired && $released !== 1 && $failure === null) {
                    throw new \RuntimeException("The Redis throttling lock expired before admission completed.");
                }
            } catch (\Throwable $cleanup_exception) {
                if ($failure === null) {
                    throw new \RuntimeException("The Redis throttling lock could not be released.", 0, $cleanup_exception);
                }
            }
        }
    } // executeAtomically

    /**
     * Requires storage hooks to belong to the current locked caller.
     *
     * @param string $key
     * The hashed caller selected by the storage hook.
     *
     * @return string
     * Returns this operation's unique owner token.
     *
     * @throws \LogicException
     * If no operation is active or the hook requests another caller's history.
     *
     * @author Oleg Schildt
     */
    private function token(string $key): string
    {
        if ($this->active_key !== $key || $this->active_token === null) {
            throw new \LogicException("Redis storage access requires the active caller's operation.");
        }

        return $this->active_token;
    } // token

    /**
     * Reads the Redis server's clock for consistent windows across application servers.
     *
     * @return float
     * Returns Unix seconds with microsecond precision.
     *
     * @throws \LogicException
     * If the client cannot execute an immediate command.
     *
     * @throws \RuntimeException
     * If Redis returns an invalid TIME reply.
     *
     * @throws \RedisException
     * If Redis cannot execute TIME; the atomic boundary wraps the storage failure.
     *
     * @author Oleg Schildt
     */
    protected function getCurrentTime(): float
    {
        $time = $this->client()->time();

        if (!is_array($time) || count($time) !== 2 || !is_numeric($time[0]) || !is_numeric($time[1])) {
            throw new \RuntimeException("The Redis throttling clock returned an invalid reply.");
        }

        return (float) $time[0] + (float) $time[1] / 1000000;
    } // getCurrentTime

    /**
     * Loads only the selected caller's ordered JSON history while checking lease ownership.
     *
     * @param string $key
     * The lowercase SHA-256 caller hash supplied to executeAtomically().
     *
     * @return list<float>
     * Returns finite, nonnegative, ascending timestamps, preserving duplicates. A missing key
     * returns an empty list. Existing empty, malformed, non-array or unordered history fails closed.
     *
     * @throws \LogicException
     * If access does not belong to the active caller or the client cannot execute commands.
     *
     * @throws \RuntimeException
     * If the lease was lost, a command reply is invalid, or the stored JSON history is invalid.
     *
     * @throws \RedisException
     * If Redis cannot read the key; the atomic boundary wraps the storage failure.
     *
     * @author Oleg Schildt
     */
    protected function loadRecords(string $key): array
    {
        $token = $this->token($key);
        $script = 'if redis.call("GET", KEYS[1]) ~= ARGV[1] then return {0} end return {1, redis.call("GET", KEYS[2])}';
        $result = $this->client()->eval($script, [...$this->keys($key), $token], 2);

        if (!is_array($result) || count($result) !== 2 || $result[0] !== 1) {
            throw new \RuntimeException("The Redis throttling history could not be read under its owner lock.");
        }

        if ($result[1] === false || $result[1] === null) {
            return [];
        }

        if (!is_string($result[1]) || $result[1] === "") {
            throw new \RuntimeException("The stored Redis throttling history is empty or is not text.");
        }

        try {
            $values = json_decode($result[1], false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException("The stored Redis throttling history is invalid JSON.", 0, $exception);
        }

        if (!is_array($values)) {
            throw new \RuntimeException("The stored Redis throttling history must be a JSON array.");
        }

        $timestamps = [];
        $previous = -1.0;

        foreach ($values as $value) {
            if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value < 0 || $value < $previous) {
                throw new \RuntimeException("The stored Redis throttling timestamps are invalid or out of order.");
            }

            $timestamp = (float) $value;
            $timestamps[] = $timestamp;
            $previous = $timestamp;
        }

        return $timestamps;
    } // loadRecords

    /**
     * Replaces only the active caller's history if the lease is still owned, with atomic expiry.
     *
     * @param string $key
     * The lowercase SHA-256 caller hash supplied to executeAtomically().
     *
     * @param list<float> $timestamps
     * This caller's nonempty, ordered history supplied by the common algorithm.
     *
     * @return void
     * Stores JSON and a millisecond TTL covering the newest timestamp plus the longest window.
     * Extremely large retention beyond Redis/Lua's safe millisecond range is stored without expiry
     * to avoid discarding active history prematurely. Rejections never call this method or renew TTL.
     *
     * @throws \LogicException
     * If access is outside the active caller's operation or the timestamp list is empty.
     *
     * @throws \RuntimeException
     * If the lease was lost or Redis did not confirm persistence.
     *
     * @throws \JsonException
     * If the supplied history cannot be encoded as JSON.
     *
     * @throws \RedisException
     * If Redis cannot persist history; the atomic boundary wraps the storage failure.
     *
     * @author Oleg Schildt
     */
    protected function saveRecords(string $key, array $timestamps): void
    {
        $token = $this->token($key);

        if ($timestamps === []) {
            throw new \LogicException("An admitted throttling request must contain at least one timestamp.");
        }

        $json = json_encode($timestamps, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $expires = max($timestamps) + max(array_column($this->limits, 1));
        $script = <<<'LUA'
if redis.call("GET", KEYS[1]) ~= ARGV[1] then return 0 end
local now = redis.call("TIME")
local ttl = math.max(1, math.ceil((tonumber(ARGV[3]) - tonumber(now[1])) * 1000 - tonumber(now[2]) / 1000) + 1)
if ttl < 9007199254740000 then
    redis.call("SET", KEYS[2], ARGV[2], "PX", string.format("%.0f", ttl))
else
    redis.call("SET", KEYS[2], ARGV[2])
end
return 1
LUA;
        $result = $this->client()->eval($script, [...$this->keys($key), $token, $json, sprintf("%.6F", $expires)], 2);

        if ($result !== 1) {
            throw new \RuntimeException("The Redis throttling history could not be saved under its owner lock.");
        }
    } // saveRecords
} // RedisRequestThrottlingManager
