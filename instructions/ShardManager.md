# Shard selection and stateless client affinity

`ShardManager` caches one independent `DBWorker` per registered shard. Connections
are created lazily and remain local to the manager instance. Client affinity uses
rendezvous hashing and requires no state file, shared cache, lock or PHP session.

## Initialization in the object factory

Configure affinity and the optional identity callback in the application's binding:

```php
use SmartFactory\DatabaseWorkers\ShardManager;
use SmartFactory\Interfaces\IShardManager;
use SmartFactory\ObjectFactory;

ObjectFactory::bindFactory(IShardManager::class, static function (): ShardManager {
    $manager = new ShardManager();

    $manager->init([
        "pin_shard_to_user_session" => true,
        "user_identificator_getter" => static fn(): string|int|null => $_SESSION["user_id"] ?? null
    ]);

    return $manager;
});
```

The callback above reads the application's existing authentication session. Replace
its body with access to your current user ID or a stable authentication token as
appropriate. The manager itself does not start or write a PHP session. Authentication
must be available before the first affinity-enabled selection, rather than before
the object factory creates the manager. Avoid resolving authentication through the
same shard selection callback, which would recursively require its own identity.

`pin_shard_to_user_session` defaults to `false`. `user_identificator_getter` defaults
to `null`; omitting it uses the IP/User-Agent fingerprint. Both options reset to their
defaults if omitted during a subsequent `init()` call. Reinitialization preserves
registered shards and cached workers. The former `state_file` option is no longer used.

## Identity callback contract

- The getter may use any PHP callable representation and receives no arguments.
- Initialization validates and stores the callable without invoking it.
- It runs once per affinity-enabled `randomDBShard()` call. It is not called by
  `dbshard()` or when affinity is disabled.
- A nonempty string or any integer, including zero, supplies the complete identity
  instead of IP/User-Agent. Equivalent integers and strings, such as `42` and `"42"`,
  represent the same identity. Tokens are treated as opaque, case-sensitive strings.
- `null` and `""` fall back to IP/User-Agent, supporting anonymous requests.
- Other result types raise `UnexpectedValueException`. Callback exceptions and errors
  propagate unchanged; they do not trigger silent fallback.
- Identity is evaluated on every selection so authentication changes in the current
  request or in a long-running process are observed. Switching from an anonymous
  fingerprint to an authenticated ID can select another shard.

Without an identifier, `REMOTE_ADDR` and `HTTP_USER_AGENT` must be nonempty strings.
Forwarded headers are not interpreted; a trusted proxy setup must normalize the client
address before selection. Different clients can share a fingerprint, and changing an
attribute can change the selected shard. A supplied stable ID remains independent of
such changes. Changing a token can likewise change the selected shard.

## Selection behavior

`dbshard()` always selects the explicitly named shard and does not invoke the getter.
`randomDBShard()` selects a random group member when affinity is disabled. When enabled,
it computes a score for each member from the identity hash, group name and shard name,
and selects the member with the highest score. Fixed-length SHA-256 scores are compared
lexicographically; a deterministic shard-name tie-breaker resolves equal scores.
The short function `SmartFactory\randomDBShard()` uses the factory's shared manager.

Requests on different PHP processes or servers agree when they use the same client
identity, algorithm, group name and registered shard names. Registration order does
not matter. Shard names should remain stable when connection settings change.

Adding a shard only moves clients for which that new shard wins. With equally weighted
members, adding a fourth shard to three moves approximately a quarter of the clients.
Removing a shard only moves its own clients. Renaming a shard acts as removal and
addition. These topology changes can require data migration if shards contain distinct
data instead of interchangeable replicas. Existing file-based random assignments are
not preserved when switching to deterministic selection.

Each affinity-enabled selection computes one identity hash and one score per group
member, taking O(N) time and O(1) additional selection space. Selection errors propagate
without automatic failover. Null worker results are returned unchanged; retries with
unchanged identity and membership select the same shard. No assignment is stored.
