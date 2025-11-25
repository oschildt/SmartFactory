<?php
/**
 * This file contains the declaration of the interface IRecordsetManager for working with record sets.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */

namespace SmartFactory\Interfaces;

use \SmartFactory\DatabaseWorkers\DBWorkerException;

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
    public function setDBWorker(\SmartFactory\DatabaseWorkers\DBWorker $dbworker): void;
    
    /**
     * Returns the dbworker to be used for working with the database.
     *
     * @return ?\SmartFactory\DatabaseWorkers\DBWorker
     * Returns the dbworker to be used for working with the database.
     *
     * @see IRecordsetManager::getDBWorker()
     *
     * @author Oleg Schildt
     */
    public function getDBWorker(): ?\SmartFactory\DatabaseWorkers\DBWorker;
    
    /**
     * Defines the field mappings for working with record sets based on a table.
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
     * @see IRecordsetManager::describeTableFieldsQuery()
     *
     * @author Oleg Schildt
     */
    public function describeTableFields(string $table, array $fields, array $key_fields): void;

    /**
     * Defines the field mappings for working with record sets based on a query.
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
     * @see IRecordsetManager::describeTableFields()
     *
     * @author Oleg Schildt
     */
    public function describeTableFieldsQuery(array $fields, array $key_fields): void;

    /**
     * Loads a record into an array in the form "field_name" => "value" based on a table.
     *
     * @param array &$record
     * The target array where the data should be loaded.
     *
     * @param array|string $where_clause
     * The where clause that should restrict the result. If an array of keys is passed,
     * the where clause is build automatically based on it.
     *
     * @return void
     *
     * @see IRecordsetManager::saveRecord()
     * @see IRecordsetManager::loadRecordSet()
     * @see IRecordsetManager::loadRecordQuery()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function loadRecord(array &$record, array|string $where_clause): void;

    /**
     * Loads a record into an array in the form "field_name" => "value" based on a query.
     *
     * @param array &$record
     * The target array where the data should be loaded.
     *
     * @param string $query
     * The query to be used.
     *
     * @return void
     *
     * @see IRecordsetManager::loadRecord()
     * @see IRecordsetManager::loadRecordSetQuery()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function loadRecordQuery(array &$record, string $query): void;

    /**
     * Deletes records by a given where clause.
     *
     * @param array|string $where_clause
     * The where clause for the records to be deleted. If an array of keys is passed,
     * the where clause is build automatically based on it.
     *
     * @return void
     *
     * @see IRecordsetManager::saveRecord()
     * @see IRecordsetManager::deleteRecordsQuery()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function deleteRecords(array|string $where_clause): void;

    /**
     * Deletes records by a given query.
     *
     * @param string $query
     * The query to be used.
     *
     * @return void
     *
     * @see IRecordsetManager::deleteRecords()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function deleteRecordsQuery(string $query): void;

    /**
     * Saves a record from an array in the form "field_name" => "value" into the table.
     *
     * @param array &$record
     * The source array with the data to be saved.
     *
     * @param array|string $where_clause
     * The where clause that should be used to define whether a record should be inserted or updated. If an array of keys is passed,
     * the where clause is build automatically based on it.
     *
     * @param string $identity_field
     * The name of the identity field if exists. If the identity field is specified
     * and the record does not exist yet in the table, the source array is extended
     * with a pair "identity field" => "identity value" issued by the database by this
     * insert operation.
     *
     * @return void
     *
     * @see IRecordsetManager::loadRecord()
     * @see IRecordsetManager::saveRecordSet()
     * @see IRecordsetManager::deleteRecords()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function saveRecord(array &$record, array|string $where_clause, string $identity_field = ""): void;

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
     * @param string $identity_field
     * The name of the identity field if exists. If the identity field is specified
     * and the record does not exist yet in the table, the source array is extended
     * with a pair "identity field" => "identity value" issued by the database by this
     * insert operation.
     *
     * @return void
     *
     * @see IRecordsetManager::loadRecordSet()
     * @see IRecordsetManager::saveRecord()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function saveRecordSet(array $records, array $parent_values = [], string $identity_field = ""): void;

    /**
     * Counts records based on the where clause.
     *
     * @param array|string $where_clause
     * The where clause that should restrict the result. If an array of keys is passed,
     * the where clause is build automatically based on it.
     *
     * @return int
     * Returns the number of records.
     *
     * @see IRecordsetManager::countRecordsQuery()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function countRecords(array|string $where_clause): int;

    /**
     * Counts records based on the query.
     *
     * @param string $query
     * The query to be used.
     *
     * @return int
     * Returns the number of records.
     *
     * @see IRecordsetManager::countRecords()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function countRecordsQuery(string $query): int;

    /**
     * Loads records into an array in the form
     *
     * $records["key_field1"]["key_field2"]["key_fieldN"]["field_name"] = "value"
     *
     * based on a table.
     *
     * @param array &$records
     * The target array where the data should be loaded.
     *
     * @param array|string $where_clause
     * The where clause that should restrict the result. If an array of keys is passed,
     * the where clause is build automatically based on it.
     *
     * @param int $limit
     * The limit how many records should be loaded. 0 for unlimited.
     *
     * @param string $order_clause
     * The order clause to sort the results.
     *
     * @return void
     *
     * @see IRecordsetManager::loadRecord()
     * @see IRecordsetManager::saveRecordSet()
     * @see IRecordsetManager::loadRecordSetQuery()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function loadRecordSet(array &$records, array|string $where_clause, string $order_clause = "", int $limit = 0): void;

    /**
     * Loads records into an array in the form
     *
     * $records["key_field1"]["key_field2"]["key_fieldN"]["field_name"] = "value"
     *
     * based on a query.
     *
     * @param array &$records
     * The target array where the data should be loaded.
     *
     * @param string $query
     * The query to be used.
     *
     * @return void
     *
     * @see IRecordsetManager::loadRecordSet()
     * @see IRecordsetManager::loadRecordQuery()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function loadRecordSetQuery(array &$records, string $query): void;

    /**
     * Starts the translation.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @see IRecordsetManager::commit_transaction()
     * @see IRecordsetManager::rollback_transaction()
     *
     * @author Oleg Schildt
     */
    public function start_transaction(): void;

    /**
     * Commits the translation.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @see IRecordsetManager::start_transaction()
     * @see IRecordsetManager::rollback_transaction()
     *
     * @author Oleg Schildt
     */
    public function commit_transaction(): void;

    /**
     * Rolls back the translation.
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @return void
     *
     * @see IRecordsetManager::start_transaction()
     * @see IRecordsetManager::commit_transaction()
     *
     * @author Oleg Schildt
     */
    public function rollback_transaction(): void;

    /**
     * Escapes the string so that it can be used in the query without causing an error.
     *
     * @param string $str
     * The string to be escaped.
     *
     * @return string
     * Returns the escaped string.
     *
     * @see IRecordsetManager::format_date()
     * @see IRecordsetManager::format_datetime()
     *
     * @author Oleg Schildt
     */
    public function escape(string $str): string;

    /**
     * Formats the date to a string compatible for the corresponding database.
     *
     * @param int $date
     * The date value as timestamp.
     *
     * @return string
     * Returns the string representation of the date compatible for the corresponding database.
     *
     * @see IRecordsetManager::escape()
     * @see IRecordsetManager::format_datetime()
     *
     * @author Oleg Schildt
     */
    public function format_date(int $date): string;

    /**
     * Formats the date/time to a string compatible for the corresponding database.
     *
     * @param int $datetime
     * The date/time value as timestamp.
     *
     * @return string
     * Returns the string representation of the date/time compatible for the corresponding database.
     *
     * @see IRecordsetManager::escape()
     * @see IRecordsetManager::format_date()
     *
     * @author Oleg Schildt
     */
    public function format_datetime(int $datetime): string;
} // IRecordsetManager
