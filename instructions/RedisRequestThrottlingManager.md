# RedisRequestThrottlingManager

`SmartFactory\RedisRequestThrottlingManager` extends `AbstractRequestThrottlingManager`
and preserves the `IRequestThrottlingManager` factory contract. It reuses the common
rolling-window algorithm and provides caller-scoped Redis storage and locking.
The optional phpredis extension is required only when using this adapter. No PHP
client package is required through Composer. Redis-compatible Memurai is supported.

## Initialization

Create, connect, authenticate and select the database on the client in application
initialization. Pass the configured `\Redis` object to the manager:

```php
$redis = new \Redis();

$redis->connect("127.0.0.1", 6379, 3.0);

$redis->setOption(\Redis::OPT_READ_TIMEOUT, 3.0);

$manager = new \SmartFactory\RedisRequestThrottlingManager();

$manager->init([
    "redis" => $redis,
    "key_prefix" => "myapplication:api:throttling:",
    "limits" => [[3, 1], [15, 10], [1550, 60], [10000, 500]],
    "lock_ttl_ms" => 10000,
    "lock_timeout_ms" => 3000,
    "lock_retry_interval_ms" => 10,
]);

$handler->init([
    "throttling_manager" => $manager,
    "user_identificator_getter" => static fn(): string|int|null => $_SESSION["user_id"] ?? null,
]);
```

Only `redis` is required. The smallest valid initialization is:

```php
$manager->init(["redis" => $redis]);
```

| Parameter | Accepted value | Default | Meaning |
| --- | --- | --- | --- |
| `redis` | `\Redis` | Required | Application-owned phpredis client; connected when admission is requested. |
| `key_prefix` | Nonempty string | `smartfactory:throttling:` | Namespace shared by processes enforcing the same policy. Use different prefixes for independent applications or budgets. |
| `limits` | List of `[positive integer count, positive integer seconds]` | `[[3, 1], [15, 10], [1550, 60], [10000, 500]]` | Rolling-window limits enforced simultaneously. An empty list disables all Redis access. |
| `lock_ttl_ms` | Integer from 1 to 2147483647 | `10000` | Lease lifetime and maximum lifetime of an abandoned caller lock. |
| `lock_timeout_ms` | Integer from 0 to 2147483647 | `3000` | How long to retry a contended lock. Zero allows one attempt. Individual network calls are bounded by client timeouts, separately. |
| `lock_retry_interval_ms` | Integer from 1 to 2147483647 | `10` | Delay between contended acquisition attempts, capped by the remaining wait time. |

`init()` validates settings without connecting, issuing commands, creating keys or
clearing history. Invalid initialization leaves the previous configuration intact.
Omitted parameters reset to the defaults above on each successful initialization.

The manager does not change client options, authenticate, select a database or
close the connection. Host, port, TLS, credentials and database index belong to
the client configuration. The adapter accepts `\Redis`; `\RedisCluster` and Predis
objects are not supported. It rejects MULTI and pipeline mode without modifying
the application's queue. Do not concurrently reconfigure a client or change its
selected database while admission is running.

Existing phpredis `OPT_PREFIX` is honored in addition to `key_prefix`. Serializer
and compression settings are preserved: Lua reads and writes raw JSON independent
of those settings. Inspect such values with an unconfigured client or `redis-cli`,
because normal GET on a serializing client may attempt to decode the JSON itself.

## Factory binding

The existing interface remains the binding key. For example, with an application
function returning a connected client:

```php
ObjectFactory::bindFactory(IRequestThrottlingManager::class, function (): RedisRequestThrottlingManager {
    $instance = new RedisRequestThrottlingManager();

    $instance->init([
        "redis" => getApplicationRedis(),
        "key_prefix" => "myapplication:api:throttling:",
    ]);

    return $instance;
});
```

Caller identification remains in RequestHandler, including its optional
`user_identificator_getter` and IP fallback. The manager receives the resulting
opaque identity and stores only its SHA-256 hash.

## Storage and atomic admission

For each hashed caller, the manager uses two keys:

```text
<client prefix><key_prefix>{<SHA-256 caller hash>}:history
<client prefix><key_prefix>{<SHA-256 caller hash>}:lock
```

History is one JSON array of ordered Unix timestamps with microsecond precision.
Equal timestamps represent distinct requests and are preserved. Other callers'
history is never read or rewritten. Redis TIME supplies the common admission clock.
Server clock rollbacks retain future records conservatively, as in the base class.

The manager acquires an expiring per-caller lock using a random owner token and
SET NX PX. Lua checks the token when reading history, writing history with its TTL,
and releasing the lock. An operation that outlives its lease cannot overwrite newer
history or delete a replacement owner's lock. Even a rejected request must confirm
ownership before returning its retry delay. Contention or lost ownership raises a
storage exception rather than returning admission or HTTP 429.

The callback executes exactly once after acquisition. There are no automatic
replays of the admission callback following an ambiguous network response. A write
may have reached Redis even if its response was lost; that attempt conservatively
consumes a slot without granting admission. Acquired locks are released on success
and failure, with TTL as crash recovery. A cleanup failure never replaces an earlier
failure. Increasing the lock TTL can accommodate larger histories or slower Redis
connections; it also prolongs recovery from an abandoned lock.

Inactive history expires automatically after the newest retained timestamp plus
the longest configured period. TTL is calculated against Redis TIME and rounded
up with a one-millisecond safety margin. Rejections do not rewrite history or
extend its TTL. Extremely large retention beyond Redis/Lua's safe millisecond
range is stored without expiry rather than expiring active history prematurely.
No database table, schema migration or scheduled cleanup is required for normal limits.

Missing history starts an empty budget. Existing malformed JSON, non-array data,
invalid or unordered timestamps and Redis values of another type fail closed and
are not overwritten. Redis command failures are reported as neutral RuntimeException
instances; RedisException is retained as the cause when supplied by phpredis.
Request handlers report these failures as HTTP 500 without executing the action.

All workers sharing a prefix and Redis database must use the same policy. Increasing
a period cannot recover timestamps already expired under an earlier policy. Budget
history needs reliable storage: deleting or evicting a history key, restarting without
persisted history, or losing writes during asynchronous failover resets that budget.
Use a suitable persistence policy and a non-evicting Redis deployment for strict limits.
This adapter coordinates on one writable Redis server; it is not a multi-master lock.

## Verification

Unit tests require phpredis but no Redis server:

```powershell
php -d xdebug.mode=off tools/phpunit.phar --filter RedisRequestThrottlingManagerTest
```

The standalone integration runner defaults to `127.0.0.1:6379`, database zero:

```powershell
php -d xdebug.mode=off tests/Integration/redis_throttling.php
php -d xdebug.mode=off tests/Integration/redis_throttling.php 127.0.0.1 6379
```

It verifies shared policies, exact window boundaries, duplicate timestamps, automatic
expiry, corrupt and wrong-type history, contention, abandoned-lock recovery, stale
writers, owner-safe release, client queues, client prefix/serialization settings,
large histories and eight synchronized processes for shared and independent callers.
Only a random `__sf_redis_test:<random>:` namespace is created. The runner removes
its keys in a finally block and checks that none remain; it never flushes the database
or changes Redis server settings. An interrupted process can leave test history until
TTL; deliberately tested enormous retention or replacement-lock fixtures may require
cleanup of that exact invocation's namespace after a hard interruption.

The server-side operations follow the official Redis
[single-instance owner-token lock pattern](https://redis.io/docs/latest/develop/clients/patterns/distributed-locks/)
and phpredis [Lua scripting behavior](https://github.com/phpredis/phpredis#scripting).
