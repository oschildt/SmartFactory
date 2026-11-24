<?php
/**
 * This file contains the declaration of the interface IShardManager for managing connections to the DB shards.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\Interfaces;

/**
 * Registers database shards and selects their workers directly or through named groups.
 *
 * Implementations may use deterministic client affinity for random selections within each group.
 *
 * @author Oleg Schildt
 */
interface IShardManager extends IInitable
{
    /**
     * Initializes the manager's load-balancing and client-affinity settings.
     *
     * @param array<string, mixed> $parameters
     * Implementation-specific settings. The standard manager accepts the boolean
     * pin_shard_to_user_session (default false) and an optional zero-argument callable
     * user_identificator_getter returning a string, int or null. A nonempty string or
     * integer supplies the identity; null or an empty string uses the IP/User-Agent fallback.
     *
     * @return void
     * Applies routing settings while preserving registered shards and cached workers.
     *
     * @throws \Exception
     * If a supplied routing setting is invalid for the implementation.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void;

    /**
     * Registers a uniquely named shard and optionally adds it to a load-balancing group.
     *
     * @param string $shard_name
     * The nonempty unique shard name, including the valid name "0".
     *
     * @param array<string, mixed> $parameters
     * The connection parameters to the shard as an associative array in the form key => value:
     *
     * - $parameters["db_type"] - database driver (MySQL, MSSQL, PostgreSQL or Oracle)
     *
     * - $parameters["db_server"] - server address
     *
     * - $parameters["db_name"] - database name
     *
     * - $parameters["db_user"] - user name
     *
     * - $parameters["db_password"] - user password
     *
     * - $parameters["autoconnect"] - true if the connection should be established automatically
     *                 upon creation.
     *
     * - $parameters["read_only"] - this parameter sets the connection to the read only mode.
     *
     * @param string $load_balancing_group
     * The optional group name used by randomDBShard().
     *
     * @return bool
     * Returns true on successful registration. Invalid registrations cause an exception.
     *
     * @throws \Exception
     * If the shard name is empty or already registered.
     *
     * @author Oleg Schildt
     */
    public function registerShard(string $shard_name, array $parameters, string $load_balancing_group = ""): bool;

    /**
     * Returns the independently cached worker for a registered shard.
     *
     * The first access creates a worker using the shard's registered connection settings.
     * Later accesses through the same manager reuse it, independently of other shards' workers.
     *
     * @param string $shard_name
     * The name of the shard.
     *
     * @return ?\SmartFactory\DatabaseWorkers\DBWorker
     * Returns the cached or newly created worker, or null if the worker factory returns null.
     *
     * @throws \Exception
     * If the shard is unknown, the driver cannot be created, or connection initialization fails.
     *
     * @author Oleg Schildt
     */
    public function dbshard(string $shard_name): ?\SmartFactory\DatabaseWorkers\DBWorker;

    /**
     * Returns the worker of a randomly selected group member, honoring client affinity if enabled.
     *
     * @param string $load_balancing_group
     * The name of a nonempty registered load-balancing group.
     *
     * @return ?\SmartFactory\DatabaseWorkers\DBWorker
     * Returns the selected shard's worker, or null if the worker factory returns null.
     *
     * @throws \Exception
     * If the group is unknown, the selected shard cannot be initialized, or required
     * client identity is unavailable or invalid.
     *
     * @throws \Throwable
     * If the configured identifier getter throws an exception or error, it is propagated unchanged.
     *
     * @author Oleg Schildt
     */
    public function randomDBShard(string $load_balancing_group): ?\SmartFactory\DatabaseWorkers\DBWorker;
} // IShardManager
