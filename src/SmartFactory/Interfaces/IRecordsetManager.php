<?php
/**
 * This file contains the declaration of the interface IRecordsetManager for working with record sets.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\Interfaces;

/**
 * Interface for working with record sets.
 *
 * @author Oleg Schildt
 */
interface IRecordsetManager
{
    /**
     * Sets the dbworker to be used for working with the database.
     *
     * @param \SmartFactory\DatabaseWorkers\DBWorker $dbworker
     * The dbworker to be used for working with the database.
     *
     * @return void
     *
     * @see IRecordsetManager::getDBWorker()
     *
     * @author Oleg Schildt
     */
    public function setDBWorker($dbworker);
    
    /**
     * Returns the dbworker to be used for working with the database.
     *
     * @return \SmartFactory\DatabaseWorkers\DBWorker
     * Returns the dbworker to be used for working with the database.
     *
     * @see IRecordsetManager::getDBWorker()
     *
     * @author Oleg Schildt
     */
    public function getDBWorker();
    
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
    public function describeTableFields($table, $fields, $key_fields);
    
    /**
     * Loads a record into an array in the form "field_name" => "value".
     *
     * @param array &$record
     * The target array where the data should be loaded.
     *
     * @param string|array $where_clause
     * The where clause that should restrict the result. If an array of keys is passed,
     * the where clause is build automatically based on it.
     *
     * @return boolean
     * Returns true if the record has been successfully loaded, otherwise false.
     *
     * @see IRecordsetManager::saveRecord()
     * @see IRecordsetManager::loadRecordSet()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function loadRecord(&$record, $where_clause);
    
    /**
     * Deletes records by a given where clause.
     *
     * @param string $where_clause
     * The where clause for the records to be deleted. If an array of keys is passed,
     * the where clause is build automatically based on it.
     *
     * @return boolean
     * Returns true if the records have been successfully deleted, otherwise false.
     *
     * @see IRecordsetManager::saveRecord()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function deleteRecords($where_clause);
    
    /**
     * Saves a record from an array in the form "field_name" => "value" into the table.
     *
     * @param array &$record
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
     * @see IRecordsetManager::loadRecord()
     * @see IRecordsetManager::saveRecordSet()
     * @see IRecordsetManager::deleteRecords()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function saveRecord(&$record, $identity_field = null);
    
    /**
     * Loads records into an array in the form $records["key_field1"]["key_field2"]["key_fieldN"]["field_name"] =
     * "value".
     *
     * @param array &$records
     * The target array where the data should be loaded.
     *
     * @param string|array $where_clause
     * The where clause that should restrict the result. If an array of keys is passed,
     * the where clause is build automatically based on it.
     *
     * @param string $order_clause
     * The order clause to sort the results.
     *
     * @return boolean
     * Returns true if the record has been successfully loaded, otherwise false.
     *
     * @see IRecordsetManager::loadRecord()
     * @see IRecordsetManager::saveRecordSet()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function loadRecordSet(&$records, $where_clause, $order_clause = "");
    
    /**
     * Saves records from an array in the form
     * $records["key_field1"]["key_field2"]["key_fieldN"]["field_name"] = "value" into the table.
     *
     * @param array &$records
     * The source array with the data to be saved.
     *
     * @param array $parent_values
     * If this recordset is a child subset of data to be saved, you can set the values of the foreign keys
     * in the form "field_name" => "value".
     *
     * @return boolean
     * Returns true if the records have been successfully saved, otherwise false.
     *
     * @see IRecordsetManager::loadRecordSet()
     * @see IRecordsetManager::saveRecord()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function saveRecordSet(&$records, $parent_values = []);
} // IRecordsetManager
