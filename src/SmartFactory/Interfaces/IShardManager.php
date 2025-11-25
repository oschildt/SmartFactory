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
 * Interface for managing connections to the DB shards.
 *
 * @author Oleg Schildt
 */
interface IShardManager
{
    /**
     * Registers a new shard.
     *
     * @param string $shard_name
     * Unique shard name.
     *
     * @param array $parameters
     * The connection parameters to the shard as an associative array in the form key => value:
     *
     * - $parameters["db_type"] - type of the database (MySQL or MSSQL)
     *
     * - $parameters["db_server"] - server address
     *
     * - $parameters["db_name"] - database name
     *
     * - $parameters["db_user"] - user name
     *
     * - $parameters["db_password"] - user password
     *
     * - $parameters["autoconnect"] - should true if the connection should be established automatically
     *                 upon creation.
     *
     * - $parameters["read_only"] - this parameter sets the connection to the read only mode.
     *
     * @param string $load_balancing_group
     * The name of the load balancing group, if the shard should be part of it.
     *
     * @return bool
     * It should return true if the registering was successful, otherwise false.
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function registerShard(string $shard_name, array $parameters, string $load_balancing_group = ""): bool;
    
    /**
     * The method provides the DBWorker object for working with the shard.
     *
     * If the parameters are omitted, the system takes the parameters from the configuration
     * settings and reuses the single instance of the DBWorker for all requests.
     * If the user passes the parameters explicitly, a new instance of the DBWorker is created upon each new request.
     *
     * Currently supported: MySQL und MS SQL.
     *
     * @param string $shard_name
     * The name of the shard.
     *
     * @return ?\SmartFactory\DatabaseWorkers\DBWorker
     * returns DBWorker object or null if the object could not be created.
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function dbshard(string $shard_name): ?\SmartFactory\DatabaseWorkers\DBWorker;
    
    /**
     * The method provides the DBWorker object for working with the shard, that is chosen randomly
     * for load balancing reason.
     *
     * @param string $load_balancing_group
     * The name of the load balancing group, from which the shard should be randomly picked.
     *
     * @return ?\SmartFactory\DatabaseWorkers\DBWorker
     * returns DBWorker object or null if the object could not be created.
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors.
     *
     * @author Oleg Schildt
     */
    public function randomDBShard(string $load_balancing_group): ?\SmartFactory\DatabaseWorkers\DBWorker;
} // IShardManager