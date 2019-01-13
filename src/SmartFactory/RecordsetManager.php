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
     * Internal variable for storing the dbworker.
     *
     * @var DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    protected $dbworker = null;
    
    /**
     * Internal variable for storing the target table name.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected $table = null;
    
    /**
     * Internal array for storing the target fields.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected $fields = null;
    
    /**
     * Internal array for storing the key fields. These are the fields that are used
     * to uniquely identify a record.
     *
     * @var array
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
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     *
     * @author Oleg Schildt
     */
    protected function validateParameters()
    {
        if (empty($this->dbworker)) {
            throw new \Exception("The 'dbworker' is not specified!");
        }
    
        if (!$this->dbworker instanceof DBWorker) {
            throw new \Exception(sprintf("The 'dbworker' does not extends the class '%s'!", DBWorker::class));
        }
    
        if (empty($this->table)) {
            throw new \Exception("The target table is not specified!");
        }
        
        if (empty($this->fields)) {
            throw new \Exception("The target fields are not specified!");
        }
    
        if (empty($this->key_fields)) {
            throw new \Exception("Key fields are not defined!");
        }
    
        if (!is_array($this->fields)) {
            throw new \Exception("Field definition must be an array - field => type!");
        }
        
        if (!is_array($this->key_fields)) {
            throw new \Exception("Key field definition must be an array!");
        }
        
        return true;
    } // validateParameters
    
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
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @author Oleg Schildt
     */
    protected function process_subarray(&$subarray, $key_fields, &$parent_values, &$record)
    {
        $current_key = array_shift($key_fields);
        
        foreach ($subarray as $key => $value) {
            if (!empty($current_key)) {
                $record[$current_key] = $key;
                
                if (!empty($parent_values[$current_key])) {
                    $record[$current_key] = $parent_values[$current_key];
                }
                
                if (!$this->process_subarray($value, $key_fields, $parent_values, $record)) {
                    return false;
                }
            } else {
                $record[$key] = $value;
            }
        }
        
        if (empty($current_key)) {
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
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if the query fails or if some object names are invalid.
     *
     * @author Oleg Schildt
     */
    public function defineTableMapping($table, $fields, $key_fields)
    {
        $this->table = $table;
        $this->fields = $fields;
        
        if (is_array($key_fields)) {
            $this->key_fields = $key_fields;
        } else {
            $this->key_fields = null;
        }
        
        $this->validateParameters();
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
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see  saveRecord()
     * @see  loadRecordSet()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function loadRecord(&$record, $where_clause)
    {
        $this->validateParameters();
        
        $query = "SELECT\n";
        
        $query .= implode(", ", array_keys($this->fields)) . "\n";
        
        $query .= "FROM " . $this->table . "\n";
        
        $query .= $where_clause;
        
        if (!$this->dbworker->execute_query($query)) {
            throw new \Exception($this->dbworker->get_last_error() . "\n\n" . $this->dbworker->get_last_query(), "system_error");
        }
        
        if ($this->dbworker->fetch_row()) {
            foreach ($this->fields as $field => $type) {
                $record[$field] = $this->dbworker->field_by_name($field);
                
                if ($type == DBWorker::DB_DATE || $type == DBWorker::DB_DATETIME) {
                    $record[$field] = strtotime($record[$field]);
                }
            }
        }
        
        $this->dbworker->free_result();
        
        return true;
    } // loadRecord
    
    /**
     * Loads records into an array in the form $records["key_field1"]["key_field2"]["key_fieldN"]["field_name"] =
     * "value".
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
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see  loadRecord()
     * @see  saveRecordSet()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function loadRecordSet(&$records, $where_clause)
    {
        $this->validateParameters();
        
        $query = "SELECT\n";
        
        $query .= implode(", ", array_keys($this->fields)) . "\n";
        
        $query .= "FROM " . $this->table . "\n";
        
        if (!empty($where_clause)) {
            $query .= $where_clause;
        }
        
        if (!$this->dbworker->execute_query($query)) {
            throw new \Exception($this->dbworker->get_last_error() . "\n\n" . $this->dbworker->get_last_query(), "system_error");
        }
        
        while ($this->dbworker->fetch_row()) {
            $dimensions = [];
            $row = [];
            
            foreach ($this->fields as $field => $type) {
                $val = $this->dbworker->field_by_name($field);
                
                if ($type == DBWorker::DB_DATE || $type == DBWorker::DB_DATETIME) {
                    $val = strtotime($val);
                }
                
                if (in_array($field, $this->key_fields)) {
                    $dimensions[$field] = $val;
                } else {
                    $row[$field] = $val;
                }
            }
            
            $reference = &$records;
            
            foreach ($dimensions as &$dval) {
                if (empty($reference[$dval])) {
                    $reference[$dval] = [];
                }
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
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see  loadRecord()
     * @see  saveRecordSet()
     *
     * @uses DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function saveRecord(&$record, $identity_field = null)
    {
        $this->validateParameters();
        
        $where = "";
        $must_insert = false;
        
        if (!empty($identity_field) && empty($record[$identity_field])) {
            $must_insert = true;
        } else {
            // check existence
            
            $query = "SELECT\n";
            
            $query .= "1\n";
            
            $query .= "FROM " . $this->table . "\n";
            
            $query .= "WHERE\n";
            
            foreach ($this->key_fields as $key_field) {
                if (!empty($where)) {
                    $where .= " AND ";
                }
                
                $value = $this->dbworker->escape(checkempty($record[$key_field]));
                
                if (empty($value) && (string)$value != "0") {
                    $where .= $key_field . " IS NULL";
                } else switch (checkempty($this->fields[$key_field])) {
                    case DBWorker::DB_NUMBER:
                        $where .= $key_field . " = " . $value;
                        break;
                    
                    case DBWorker::DB_DATETIME:
                        $where .= $key_field . " = '" . $this->dbworker->format_datetime($value) . "'";
                        break;
                    
                    case DBWorker::DB_DATE:
                        $where .= $key_field . " = '" . $this->dbworker->format_date($value) . "'";
                        break;
                    
                    default:
                        $where .= $key_field . " = '" . $value . "'";
                }
            }
            
            $query .= $where;
            
            if (!$this->dbworker->execute_query($query)) {
                throw new \Exception($this->dbworker->get_last_error() . "\n\n" . $this->dbworker->get_last_query(), "system_error");
            }
            
            if (!$this->dbworker->fetch_row()) {
                $must_insert = true;
            }
            
            $this->dbworker->free_result();
        }
        
        // saving
        
        $update_string = "";
        $insert_fields = "";
        $insert_values = "";
        
        foreach ($this->fields as $field => $type) {
            if ($must_insert && $field == $identity_field) {
                continue;
            }
            
            if (!$must_insert && in_array($field, $this->key_fields)) {
                continue;
            }
            
            $value = $this->dbworker->escape(checkempty($record[$field]));
            
            if (empty($value) && (string)$value != "0") {
                $update_string .= $field . " = NULL,\n";
                $insert_fields .= $field . ", ";
                $insert_values .= "NULL, ";
            } else switch ($type) {
                case DBWorker::DB_NUMBER:
                    $update_string .= $field . " = " . $value . ",\n";
                    $insert_fields .= $field . ", ";
                    $insert_values .= $value . ", ";
                    break;
                
                case DBWorker::DB_DATETIME:
                    $update_string .= $field . " = '" . $this->dbworker->format_datetime($value) . "',\n";
                    $insert_fields .= $field . ", ";
                    $insert_values .= "'" . $this->dbworker->format_datetime($value) . "', ";
                    break;
                
                case DBWorker::DB_DATE:
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
        
        if ($must_insert) {
            $query = "INSERT INTO " . $this->table . "(" . trim($insert_fields, ", ") . ")\n";
            $query .= "VALUES (" . trim($insert_values, ", ") . ")\n";
        } else {
            $query = "UPDATE " . $this->table . " SET\n";
            $query .= trim($update_string, ",\n") . "\n";
            $query .= "WHERE\n";
            $query .= $where;
        }
        
        if (!$this->dbworker->execute_query($query)) {
            throw new \Exception($this->dbworker->get_last_error() . "\n\n" . $this->dbworker->get_last_query(), "system_error");
        }
        
        if (!empty($identity_field) && $must_insert) {
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
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see  loadRecordSet()
     * @see  saveRecord()
     *
     * @uses DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function saveRecordSet(&$records, $parent_values = [])
    {
        $this->validateParameters();
        
        $key_fields = $this->key_fields;
        $current_key = array_shift($key_fields);
        
        foreach ($records As $key => $value) {
            $record = [];
            $record[$current_key] = $key;
            
            if (!empty($parent_values[$current_key])) {
                $record[$current_key] = $parent_values[$current_key];
            }
            
            if (!$this->process_subarray($value, $key_fields, $parent_values, $record)) {
                return false;
            }
        }
        
        return true;
    } // saveRecordSet
} // RecordsetManager
