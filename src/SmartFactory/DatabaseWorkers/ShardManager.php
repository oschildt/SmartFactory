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
   * - $parameters["read_only"] - this paramter sets the connection to the read only mode.
   *
   * @return boolean
   * It should return true if the registering was successful, otherwise false.
   *
   * @author Oleg Schildt
   */
  public function registerShard($shard_name, $parameters)
  {
    if(empty($shard_name))
    {
      trigger_error("The shard name is undefined (empty)!", E_USER_ERROR);
      return false;
    }
  
    if(!empty($this->shard_table[$shard_name]))
    {
      trigger_error("The shard '$shard_name' has been already registered!", E_USER_ERROR);
      return false;
    }
  
    $this->shard_table[$shard_name]["parameters"] = $parameters;
  
    return true;
  } // registerShard
  
  /**
   * The method dbshard provides the DBWorker object for working with the shard.
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
   * @author Oleg Schildt
   */
  public function dbshard($shard_name)
  {
    if(empty($shard_name) || empty($this->shard_table[$shard_name])) return null;
    
    if(empty($this->shard_table[$shard_name]["dbworker"]))
    {
      $this->shard_table[$shard_name]["dbworker"] = dbworker($this->shard_table[$shard_name]["parameters"]);
    }
    
    return $this->shard_table[$shard_name]["dbworker"];
  } // dbshard
} // ShardManager