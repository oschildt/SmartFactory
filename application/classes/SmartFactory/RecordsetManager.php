<?php
/**
 * This file contains the implementation of the interface IRecordsetManager 
 * in the class RecordsetManager for working with record sets.
 *
 * @package Database
 *
 * @author Oleg Schildt 
 */
 
namespace SmartFactory;

use SmartFactory\Interfaces\IRecordsetManager;
use SmartFactory\DatabaseWorkers\DBWorker;

/**
 * Class for working with record sets.
 *
 * @uses DatabaseWorkers\DBWorker
 *
 * @author Oleg Schildt 
 */
class RecordsetManager implements IRecordsetManager
{
  /**
   * @var DatabaseWorkers\DBWorker
   * Internal variable for storing the dbworker.
   *
   * @author Oleg Schildt 
   */
  protected $dbworker = null;

  /**
   * @var string
   * Internal variable for storing the target table name.
   *
   * @author Oleg Schildt 
   */
  protected $table = null;

  /**
   * @var array
   * Internal array for storing the target fields.
   *
   * @author Oleg Schildt 
   */
  protected $fields = null;

  /**
   * @var array
   * Internal array for storing the key fields. These are the fields that are used 
   * to uniquely identify a record.
   *
   * @author Oleg Schildt 
   */
  protected $key_fields = null;

  /**
   * This is internal auxiliary function for checking that the recordset manager
   * is intialized correctly.
   *
   * @return boolean
   * It should return true if the recordset manager is intialized correctly, otherwise false.
   *
   * @author Oleg Schildt 
   */
  protected function basic_check()
  {
    if(empty($this->dbworker)) 
    {
      trigger_error("DBWorker is not defined!", E_USER_ERROR);
      return false;
    }
    
    if(empty($this->table)) 
    {
      trigger_error("Target table is not defined!", E_USER_ERROR);
      return false;
    }

    if(empty($this->fields)) 
    {
      trigger_error("Target fields are not defined!", E_USER_ERROR);
      return false;
    }
    
    if(!is_array($this->fields)) 
    {
      trigger_error("Field definition must be an array - field => type!", E_USER_ERROR);
      return false;
    }

    if(empty($this->key_fields)) 
    {
      trigger_error("Key fields are not defined!", E_USER_ERROR);
      return false;
    }
    
    if(!is_array($this->key_fields)) 
    {
      trigger_error("Key field definition must be an array!", E_USER_ERROR);
      return false;
    }

    return true;
  } // basic_check

  /**
   * This is internal auxiliary function for saving a record set from an array
   * with key field values as array dimensions.
   *
   * It expand this multidimensional
   * array into the set of flat records suitable for call {@see RecordsetManager::saveRecord()}.
   *
   * @param array $subarray
   * The current subarray ro be processed.
   *
   * @param array $key_fields
   * The array of the key fields. These are the fields that are used 
   * to uniquely identify a record.
   *
   * @param array $parent_values
   * The array of the values of the foreign keys
   * in the form "field_name" => "value".
   *
   * @param array $record
   * The array where the resulting flat record is built.
   *
   * @return boolean
   * Returns true if the subarray has been processed successfully, otherwise false.
   *
   * @author Oleg Schildt 
   */
  protected function process_subarray(&$subarray, $key_fields, &$parent_values, &$record)
  {
    $current_key = array_shift($key_fields);

    foreach($subarray as $key => $value)
    {
      if(!empty($current_key))
      {
        $record[$current_key] = $key;
        
        if(!empty($parent_values[$current_key])) $record[$current_key] = $parent_values[$current_key];
        
        if(!$this->process_subarray($value, $key_fields, $parent_values, $record)) return false;
      }
      else
      {
        $record[$key] = $value;
      }
    }
    
    if(empty($current_key))
    {
      return $this->saveRecord($record);
    }
    
    return true;
  } // process_subarray

  /**
   * Sets the dbworker to be used for working with the database.
   *
   * @param DatabaseWorkers\DBWorker $dbworker
   * The dbworker to be used for working with the database.
   *
   * @return void
   *
   * @see getDBWorker()
   *
   * @author Oleg Schildt 
   */
  public function setDBWorker($dbworker)
  {
    $this->dbworker = $dbworker;
  } // setDBWorker

  /**
   * Returns the dbworker to be used for working with the database.
   *
   * @return DatabaseWorkers\DBWorker
   * Returns the dbworker to be used for working with the database.
   *
   * @see getDBWorker()
   *
   * @author Oleg Schildt 
   */
  public function getDBWorker()
  {
    return $this->dbworker;
  } // getDBWorker

  /**
   * Defines the mappings for working with record sets.
   *
   * @param string $table
   * The name of the table.
   *
   * @param array $fields
   * The array of fields in the form "field name" => "field type".
   *
   * @param array $key_fields
   * The array of key fields. These are the fields that are used 
   * to uniquely identify a record.
   *
   * @return void
   *
   * @author Oleg Schildt 
   */
  public function defineTableMapping($table, $fields, $key_fields)
  {
    $this->table = $table;
    $this->fields = $fields;

    if(is_array($key_fields)) $this->key_fields = $key_fields;
    else                      $this->key_fields = null;
  } // defineTableMapping

  /**
   * Loads a record into an array in the form "field_name" => "value".
   *
   * @param array $record
   * The target array where the data should be loaded.
   *
   * @param string $where_clause
   * The where clause that should restrict the result to one record.
   *
   * @return boolean
   * Returns true if the record has been successfully loaded, otherwise false.
   *
   * @see saveRecord()
   * @see loadRecordSet()
   *
   * @uses \SmartFactory\DatabaseWorkers\DBWorker
   *
   * @author Oleg Schildt 
   */
  public function loadRecord(&$record, $where_clause)
  {
    if(!$this->basic_check()) return false;
    
    $query = "SELECT\n";
    
    $query .= implode(", ", array_keys($this->fields)) . "\n";
    
    $query .= "FROM " . $this->table . "\n";

    $query .= $where_clause;
    
    if(!$this->dbworker->execute_query($query))
    {
      return sql_error($this->dbworker);
    }

    if($this->dbworker->fetch_row())
    {
      foreach($this->fields as $field => $type)
      {
        $record[$field] = $this->dbworker->field_by_name($field);
        
        if($type == DBWorker::db_date || $type == DBWorker::db_datetime) $record[$field] = strtotime($record[$field]);
      }
    }

    $this->dbworker->free_result();
    
    return true;
  } // loadRecord

  /**
   * Loads records into an array in the form $records["key_field1"]["key_field2"]["key_fieldN"]["field_name"] = "value".
   *
   * @param array $records
   * The target array where the data should be loaded.
   *
   * @param string $where_clause
   * The where clause that should restrict the result.
   *
   * @return boolean
   * Returns true if the record has been successfully loaded, otherwise false.
   *
   * @see loadRecord()
   * @see saveRecordSet()
   *
   * @uses \SmartFactory\DatabaseWorkers\DBWorker
   *
   * @author Oleg Schildt 
   */
  public function loadRecordSet(&$records, $where_clause)
  {
    if(!$this->basic_check()) return false;
    
    $query = "SELECT\n";
    
    $query .= implode(", ", array_keys($this->fields)) . "\n";
    
    $query .= "FROM " . $this->table . "\n";

    if(!empty($where_clause)) $query .= $where_clause;
    
    if(!$this->dbworker->execute_query($query))
    {
      return sql_error($this->dbworker);
    }

    while($this->dbworker->fetch_row())
    {
      $dimensions = [];
      $row = [];
      
      foreach($this->fields as $field => $type)
      {
        $val = $this->dbworker->field_by_name($field);
        
        if($type == DBWorker::db_date || $type == DBWorker::db_datetime) $val = strtotime($val);
        
        if(in_array($field, $this->key_fields)) $dimensions[$field] = $val;
        else                                    $row[$field] = $val;
      }
      
      $reference = &$records;

      foreach($dimensions as &$dval) 
      {
        if(empty($reference[$dval])) $reference[$dval] = [];
        $reference = &$reference[$dval];
      } 

      $reference = $row;
      
      unset($reference);
    }
    
    $this->dbworker->free_result();

    return true;
  } // loadRecordSet

  /**
   * Saves a record from an array in the form "field_name" => "value" into the table.
   *
   * @param array $record
   * The source array with the data to be saved.
   *
   * @param string $identity_field
   * The name of the identity field if exists. If the identity field is specified
   * and the record does not exist yet in the table, the source array is extended
   * with a pair "identity field" => "identity value" issued by the database by this
   * insert operation.
   *
   * @return boolean
   * Returns true if the record has been successfully saved, otherwise false.
   *
   * @see loadRecord()
   * @see saveRecordSet()
   *
   * @uses DatabaseWorkers\DBWorker
   *
   * @author Oleg Schildt 
   */
  public function saveRecord(&$record, $identity_field = null)
  {
    if(!$this->basic_check()) return false;
    
    $where = "";      
    $must_insert = false;
    
    if(!empty($identity_field) && empty($record[$identity_field]))
    {
      $must_insert = true;
    }
    else
    {
      // check existence
      
      $query = "SELECT\n";
      
      $query .= "1\n";
      
      $query .= "FROM " . $this->table . "\n";

      $query .= "WHERE\n";
      
      foreach($this->key_fields as $key_field)
      {
        if(!empty($where)) $where .= " AND ";
        
        $value = $this->dbworker->escape(checkempty($record[$key_field]));
        
        if(empty($value) && (string)$value != "0")
        {
          $where .= $key_field . " IS NULL";
        }
        else switch(checkempty($this->fields[$key_field]))
        {
          case DBWorker::db_number:
          $where .= $key_field . " = " . $value;
          break;

          case DBWorker::db_datetime:
          $where .= $key_field . " = '" . $this->dbworker->format_datetime($value) . "'";
          break;

          case DBWorker::db_date:
          $where .= $key_field . " = '" . $this->dbworker->format_date($value) . "'";
          break;
          
          default:
          $where .= $key_field . " = '" . $value . "'";
        }
      }
      
      $query .= $where;
      
      if(!$this->dbworker->execute_query($query))
      {
        return sql_error($this->dbworker);
      }
      
      if(!$this->dbworker->fetch_row())
      {
        $must_insert = true;
      }

      $this->dbworker->free_result();
    }
    
    // saving
    
    $update_string = "";
    $insert_fields = "";
    $insert_values = "";
    
    foreach($this->fields as $field => $type)
    {
      if($must_insert && $field == $identity_field) continue;        
      
      if(!$must_insert && in_array($field, $this->key_fields)) continue;        
      
      $value = $this->dbworker->escape(checkempty($record[$field]));
      
      if(empty($value) && (string)$value != "0")
      {
        $update_string .= $field . " = NULL,\n";
        $insert_fields .= $field . ", ";
        $insert_values .= "NULL, ";
      }
      else switch($type)
      {
        case DBWorker::db_number:
        $update_string .= $field . " = " . $value . ",\n";
        $insert_fields .= $field . ", ";
        $insert_values .= $value . ", ";
        break;

        case DBWorker::db_datetime:
        $update_string .= $field . " = '" . $this->dbworker->format_datetime($value) . "',\n";
        $insert_fields .= $field . ", ";
        $insert_values .= "'" . $this->dbworker->format_datetime($value) . "', ";
        break;

        case DBWorker::db_date:
        $update_string .= $field . " = '" . $this->dbworker->format_date($value) . "',\n";
        $insert_fields .= $field . ", ";
        $insert_values .= "'" . $this->dbworker->format_date($value) . "', ";
        break;
        
        default:
        $update_string .= $field . " = '" . $value . "',\n";
        $insert_fields .= $field . ", ";
        $insert_values .= "'" . $value . "', ";
      }
    }
    
    if($must_insert)
    {
      $query  = "INSERT INTO " . $this->table . "(" . trim($insert_fields, ", ") . ")\n";
      $query .= "VALUES (" . trim($insert_values, ", ") . ")\n";
    }
    else
    {
      $query  = "UPDATE " . $this->table . " SET\n";
      $query .= trim($update_string, ",\n") . "\n";
      $query .= "WHERE\n";
      $query .= $where;
    }
    
    if(!$this->dbworker->execute_query($query))
    {
      return sql_error($this->dbworker);
    }
    
    if(!empty($identity_field) && $must_insert)
    {
      $record[$identity_field] = $this->dbworker->insert_id();
    }
    
    return true;
  } // saveRecord

  /**
   * Saves records from an array in the form 
   * $records["key_field1"]["key_field2"]["key_fieldN"]["field_name"] = "value" into the table.
   *
   * @param array $records
   * The source array with the data to be saved.
   *
   * @param array $parent_values
   * If this recordset is a child subset of data to be saved, you can set the values of the foreign keys
   * in the form "field_name" => "value".
   *
   * @return boolean
   * Returns true if the records have been successfully saved, otherwise false.
   *
   * @see loadRecordSet()
   * @see saveRecord()
   *
   * @uses DatabaseWorkers\DBWorker
   *
   * @author Oleg Schildt 
   */
  public function saveRecordSet(&$records, $parent_values = [])
  {
    if(!$this->basic_check()) return false;
    
    $key_fields = $this->key_fields;
    $current_key = array_shift($key_fields);
    
    foreach($records As $key => $value)
    {
      $record = [];
      $record[$current_key] = $key;
      
      if(!empty($parent_values[$current_key])) $record[$current_key] = $parent_values[$current_key];
      
      if(!$this->process_subarray($value, $key_fields, $parent_values, $record)) return false;
    }
    
    return true;
  } // saveRecordSet
} // RecordsetManager
