<?php
/**
 * This file contains the implementation of the class ShardManager.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\DatabaseWorkers;

use SmartFactory\Interfaces\IShardManager;

use function SmartFactory\dbworker;

/**
 * Class for registering database shards and managing their connections.
 *
 * Keeps a separate cached DBWorker per registered shard within this instance.
 * Named groups support random selection. Optional affinity uses rendezvous hashing
 * to select the same shard for a stable client identity without persistent storage.
 *
 * @author Oleg Schildt
 */
class ShardManager implements IShardManager
{
    /**
     * Stores the shard names belonging to each load-balancing group.
     *
     * @var array<string|int, list<string>>
     *
     * @author Oleg Schildt
     */
    protected array $load_balancing_groups = [];

    /**
     * Maps shard names to their connection parameters and optional cached DBWorker instances.
     *
     * @var array<string|int, array{parameters: array<string, mixed>, dbworker?: DBWorker|null}>
     *
     * @author Oleg Schildt
     */
    protected array $shard_table = [];

    /**
     * Controls whether load-balanced selections use deterministic client affinity.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $pin_shard_to_user_session = false;

    /**
     * Supplies the current user ID or stable authentication token when configured.
     *
     * @var (\Closure(): (string|int|null))|null
     *
     * @author Oleg Schildt
     */
    protected ?\Closure $user_identificator_getter = null;

    /**
     * Initializes deterministic client affinity and the optional user-identifier getter.
     *
     * Registered shards and cached workers are preserved when initialization is repeated.
     * The getter is called without arguments only when affinity-enabled randomDBShard()
     * is invoked, so authentication may be resolved after object creation. Its nonempty
     * string or integer result replaces the IP/User-Agent identity. Null or an empty string
     * falls back to that fingerprint. No file, PHP session or shared cache is required.
     *
     * @param array{pin_shard_to_user_session?: bool, user_identificator_getter?: (callable(): (string|int|null))|null} $parameters
     * Optional settings. pin_shard_to_user_session defaults to false and the getter defaults
     * to null on every initialization. Any callable representation is accepted for the getter.
     *
     * @return void
     * Updates affinity settings without invoking the getter or opening database connections.
     *
     * @throws \InvalidArgumentException
     * If user_identificator_getter is supplied and is neither callable nor null.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void
    {
        $getter = $parameters["user_identificator_getter"] ?? null;

        if ($getter !== null && !is_callable($getter)) {
            throw new \InvalidArgumentException("The user identifier getter must be callable or null.");
        }

        $this->user_identificator_getter = $getter === null ? null : \Closure::fromCallable($getter);
        $this->pin_shard_to_user_session = !empty($parameters["pin_shard_to_user_session"]);
    } // init

    /**
     * Registers a shard and optionally adds it to a load-balancing group.
     *
     * @param string $shard_name
     * The nonempty unique shard name.
     *
     * @param array<string, mixed> $parameters
     * The connection settings accepted by dbworker(), including db_type, db_server,
     * db_name, db_user, db_password and optional driver settings. Supported driver names
     * are MySQL, MSSQL, PostgreSQL and Oracle.
     *
     * @param string $load_balancing_group
     * The optional group name used by randomDBShard().
     *
     * @return bool
     * Returns true when the shard is registered. Invalid registrations cause an exception.
     *
     * @throws \Exception
     * If the name is empty or already registered.
     *
     * @link markdown:ShardManager.md Shard selection and stateless client affinity
     *
     * @see ShardManager::dbshard()
     * @see ShardManager::randomDBShard()
     *
     * @author Oleg Schildt
     */
    public function registerShard(string $shard_name, array $parameters, string $load_balancing_group = ""): bool
    {
        if ($shard_name === "") {
            throw new \Exception("The shard name is not specified!");
        }

        if (isset($this->shard_table[$shard_name])) {
            throw new \Exception("The shard '$shard_name' has been already registered!");
        }

        $this->shard_table[$shard_name]["parameters"] = $parameters;

        if ($load_balancing_group !== "") {
            $this->load_balancing_groups[$load_balancing_group][] = $shard_name;
        }

        return true;
    } // registerShard

    /**
     * Returns the worker cached for a registered shard.
     *
     * A separate non-singleton factory request is used for each shard, then cached within
     * this manager. This avoids reusing another shard's connection merely because its driver class matches.
     *
     * @param string $shard_name
     * The registered shard name.
     *
     * @return \SmartFactory\DatabaseWorkers\DBWorker|null
     * Returns the cached worker, creating it from the registered connection settings on first access,
     * or null if the worker factory returns null.
     *
     * @throws \Exception
     * If the shard is unknown, the driver cannot be created, or connection initialization fails.
     *
     * @author Oleg Schildt
     */
    public function dbshard(string $shard_name): ?\SmartFactory\DatabaseWorkers\DBWorker
    {
        if (!isset($this->shard_table[$shard_name])) {
            throw new \Exception("The shard '$shard_name' was not found!");
        }

        // Create a separate factory instance for each shard, even when several
        // shards use the same driver class. Cache it in this manager so later
        // lookups reuse the connection to that shard.
        if (empty($this->shard_table[$shard_name]["dbworker"])) {
            $this->shard_table[$shard_name]["dbworker"] = dbworker($this->shard_table[$shard_name]["parameters"], false /* not singleton */);
        }

        return $this->shard_table[$shard_name]["dbworker"];
    } // dbshard

    /**
     * Returns a random group member, or a deterministic member when client affinity is enabled.
     *
     * Affinity uses rendezvous hashing of the client identity, group name and shard name.
     * It requires no stored assignments and is independent of registration order. Adding
     * a shard only moves clients for which the new shard wins; removing one only moves
     * its clients. Failed worker initialization propagates without automatic failover.
     *
     * @param string $load_balancing_group
     * The name of a nonempty registered group.
     *
     * @return \SmartFactory\DatabaseWorkers\DBWorker|null
     * Returns the selected shard's cached or newly initialized worker, or null if the factory returns null.
     *
     * @throws \Exception
     * If the group is unknown or the selected shard cannot be initialized.
     *
     * @throws \RuntimeException
     * If affinity needs the fallback fingerprint but the client IP or User-Agent is missing or empty.
     *
     * @throws \UnexpectedValueException
     * If the configured identifier getter returns a value other than string, int or null.
     *
     * @throws \Throwable
     * If the identifier getter throws an exception or error, it is propagated unchanged.
     *
     * @see ShardManager::registerShard()
     * @see ShardManager::dbshard()
     *
     * @author Oleg Schildt
     */
    public function randomDBShard(string $load_balancing_group): ?\SmartFactory\DatabaseWorkers\DBWorker
    {
        if (empty($this->load_balancing_groups[$load_balancing_group])) {
            throw new \Exception("The group '$load_balancing_group' was not found!");
        }

        $shards = $this->load_balancing_groups[$load_balancing_group];

        if (!$this->pin_shard_to_user_session) {
            return $this->dbshard($shards[rand(0, count($shards) - 1)]);
        }

        $user_hash = $this->userSessionHash();
        $highest_score = "";
        $selected_shard = null;

        foreach ($shards as $shard_name) {
            $score = hash("sha256", serialize([$load_balancing_group, $user_hash, $shard_name]));

            // Compare fixed-length hexadecimal scores as strings to avoid numeric conversion.
            // A stable name-based tie-breaker also makes hash collisions independent of order.
            if (
                $selected_shard === null
                || strcmp($score, $highest_score) > 0
                || ($score === $highest_score && strcmp($shard_name, $selected_shard) < 0)
            ) {
                $highest_score = $score;
                $selected_shard = $shard_name;
            }
        }

        return $this->dbshard($selected_shard);
    } // randomDBShard

    /**
     * Computes the current client's identity hash for deterministic shard selection.
     *
     * The optional zero-argument getter is evaluated on every affinity-enabled selection.
     * A nonempty string or any integer, including zero, replaces the IP/User-Agent identity.
     * Integer and equivalent string identifiers are normalized to the same identity.
     * A null or empty-string result falls back to REMOTE_ADDR and HTTP_USER_AGENT, as does
     * an omitted getter. Identity sources are separated so an identifier cannot collide
     * with the raw fingerprint merely because its contents match client attributes.
     * Forwarded headers are not interpreted; trusted proxies must normalize REMOTE_ADDR.
     *
     * @return string
     * Returns the SHA-256 hash of the normalized identifier or the fallback fingerprint.
     *
     * @throws \RuntimeException
     * If fallback identity is needed but the client IP or User-Agent is missing or empty.
     *
     * @throws \UnexpectedValueException
     * If the getter returns a value other than string, int or null.
     *
     * @throws \Throwable
     * If the getter throws an exception or error, it is propagated unchanged.
     *
     * @author Oleg Schildt
     */
    protected function userSessionHash(): string
    {
        if ($this->user_identificator_getter !== null) {
            $identifier = ($this->user_identificator_getter)();

            if ($identifier !== null && !is_string($identifier) && !is_int($identifier)) {
                throw new \UnexpectedValueException("The user identifier getter must return a string, integer or null.");
            }

            if ($identifier !== null && $identifier !== "") {
                return hash("sha256", serialize(["identifier", (string) $identifier]));
            }
        }

        $ip = $_SERVER["REMOTE_ADDR"] ?? null;
        $user_agent = $_SERVER["HTTP_USER_AGENT"] ?? null;

        if (!is_string($ip) || $ip === "" || !is_string($user_agent) || $user_agent === "") {
            throw new \RuntimeException("Shard affinity requires a nonempty client IP address and User-Agent.");
        }

        return hash("sha256", serialize(["fingerprint", $ip, $user_agent]));
    } // userSessionHash
} // ShardManager
