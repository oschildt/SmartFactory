<?php
/**
 * Created by PhpStorm.
 * User: oschildt
 * Date: 23.11.2018
 * Time: 12:52
 */

namespace SmartFactory\DatabaseWorkers;

use SmartFactory\Interfaces\IShardManager;

use function SmartFactory\dbworker;

/**
 * Class for managing connections to the DB shards.
 *
 * This shard manager allows registering multiple shards and request connections to them.
 * It ensures that only one connection to a shard is used within a request.
 *
 * @see DBWorker
 *
 * @author Oleg Schildt
 */
class ShardManager implements IShardManager
{
    /**
     * Internal array for storing the load balancing groups and its shards.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $load_balancing_groups = [];
    
    /**
     * Internal array for storing the mapping betwen shard names and connection parameters.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $shard_table = [];
    
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
     * - $parameters["db_server"] - server address
     * - $parameters["db_name"] - database name
     * - $parameters["db_user"] - user name
     * - $parameters["db_password"] - user password
     * - $parameters["autoconnect"] - should true if the connection should be established automatically upon creation.
     * - $parameters["read_only"] - this paramter sets the connection to the read only mode.
     *
     * @param string $load_balancing_group
     * The name of the load balancing group, if the shard should be part of it, {@see ShardManager::randomDBShard()}.
     *
     * @return boolean
     * It should return true if the registering was successful, otherwise false.
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if the shard name is not specified.
     * - if the shard name has been already registered.
     *
     * @author Oleg Schildt
     */
    public function registerShard($shard_name, $parameters, $load_balancing_group = "")
    {
        if (empty($shard_name)) {
            throw new \Exception("The shard name is not specified!");
        }
        
        if (!empty($this->shard_table[$shard_name])) {
            throw new \Exception("The shard '$shard_name' has been already registered!");
        }
        
        $this->shard_table[$shard_name]["parameters"] = $parameters;
        
        if (!empty($load_balancing_group)) {
            $this->load_balancing_groups[$load_balancing_group][] = $shard_name;
        }
        
        return true;
    } // registerShard
    
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
     * @return \SmartFactory\DatabaseWorkers\DBWorker|null
     * returns DBWorker object or null if the object could not be created.
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if the interface or class does not exist.
     * - if the shard was not found.
     * - if the check of the classes and interfaces fails.
     * - if the php extension is not installed.
     * - db_missing_type_error - if the database type is not specified.
     * - db_conn_data_error - if the connection parameters are incomplete.
     * - db_server_conn_error - if the database server cannot be connected.
     * - db_not_exists_error - if database does not exists od inaccesible to the user.
     *
     * @author Oleg Schildt
     */
    public function dbshard($shard_name)
    {
        if (empty($shard_name) || empty($this->shard_table[$shard_name])) {
            throw new \Exception("The shard '$shard_name' was not found!");
            return null;
        }
        
        // Important!
        //
        // Many different databases may be connected within one request,
        // therefore, we request the dbworker as not singleton. Otherwise,
        // only one dbworker  connected to one database would be returned.
        //
        // But, as soon as, we got a shard connected to a required database,
        // we keep it as singleton within one request, to avoid the unnecessary
        // DB connections.
        //
        // In other words, many databases can be accessed from one request,
        // but there is only one connection to each database.
        
        if (empty($this->shard_table[$shard_name]["dbworker"])) {
            $this->shard_table[$shard_name]["dbworker"] = dbworker($this->shard_table[$shard_name]["parameters"], false /* not singleton */);
        }
        
        return $this->shard_table[$shard_name]["dbworker"];
    } // dbshard
    
    /**
     * The method provides the DBWorker object for working with the shard, that is chosen randomly
     * for load balancing reason.
     *
     * @param string $load_balancing_group
     * The name of the load balancing group, from which the shard should be randomly picked.
     *
     * @return \SmartFactory\DatabaseWorkers\DBWorker|null
     * returns DBWorker object or null if the object could not be created.
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if the load balancing group was not found.
     * - db_server_conn_error - if the database server cannot be connected.
     * - db_not_exists_error - if database does not exists od inaccesible to the user.
     *
     * @author Oleg Schildt
     */
    public function randomDBShard($load_balancing_group)
    {
        if (empty($this->load_balancing_groups[$load_balancing_group])) {
            throw new \Exception("The group '$load_balancing_group' was not found!");
        }
        
        $pos = rand(0, count($this->load_balancing_groups[$load_balancing_group]) - 1);
        
        return $this->dbshard($this->load_balancing_groups[$load_balancing_group][$pos]);
    } // randomDBShard
} // ShardManager