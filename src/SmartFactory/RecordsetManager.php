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

use \SmartFactory\Interfaces\IRecordsetManager;

use \SmartFactory\DatabaseWorkers\DBWorker;
use \SmartFactory\DatabaseWorkers\DBWorkerException;

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
     * @var ?DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    protected ?DatabaseWorkers\DBWorker $dbworker = null;

    /**
     * Internal variable for storing the target table name.
     *
     * @var ?string
     *
     * @author Oleg Schildt
     */
    protected ?string $table = null;

    /**
     * Internal array for storing the target fields.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected array $fields = [];

    /**
     * Internal array for storing the key fields. These are the fields that are used
     * to uniquely identify a record.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    protected array $key_fields = [];

    /**
     * This is internal auxiliary function for checking that the recordset manager
     * is initialized correctly.
     *
     * @param string $type
     * The type of validation - table or query.
     *
     * @return void
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
    protected function validateParameters(string $type): void
    {
        if (empty($this->dbworker)) {
            throw new \Exception("The 'dbworker' is not specified!");
        }

        if (!$this->dbworker instanceof DBWorker) {
            throw new \Exception(sprintf("The 'dbworker' does not extends the class '%s'!", DBWorker::class));
        }

        if ($type == "table" && empty($this->table)) {
            throw new \Exception("The target table is not specified!");
        }

        if (empty($this->fields)) {
            throw new \Exception("The target fields are not specified!");
        }

        if (!is_array($this->fields)) {
            throw new \Exception("Field definition must be an array - field => type!");
        }

        if (!empty($this->key_fields) && !is_array($this->key_fields)) {
            throw new \Exception("Key field definition must be an array!");
        }
    } // validateParameters

    /**
     * This is internal auxiliary function for converting an array to a where clause.
     *
     * @param array|string &$where_clause
     * The where clause that should be checked. If an array of keys is passed,
     * the where clause is build based on it.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case if a field for the clause wad not described
     * by the initialization.
     *
     * @author Oleg Schildt
     */
    protected function checkWhereClause(array|string &$where_clause): void
    {
        if (!is_array($where_clause)) {
            return;
        }

        $tmp = "";
        foreach ($where_clause as $key_field => $value) {
            if (empty($this->fields[$key_field])) {
                throw new \Exception(sprintf("The field '%s' is not described!", $key_field));
            }

            if (!empty($tmp)) {
                $tmp .= " and ";
            }

            $tmp .= $key_field . " = " . $this->dbworker->prepare_for_query($value, $this->fields[$key_field]);
        }

        $where_clause = "where " . $tmp;
    } // checkWhereClause

    /**
     * This is internal auxiliary function for saving a record set from an array
     * with key field values as array dimensions.
     *
     * It expands this multidimensional
     * array into the set of flat records suitable for call {@see RecordsetManager::saveRecord()}.
     *
     * @param array $subarray
     * The current subarray ro be processed.
     *
     * @param array $key_fields
     * The array of the key fields. These are the fields that are used
     * to uniquely identify a record.
     *
     * @param array &$parent_values
     * The array of the values of the foreign keys
     * in the form "field_name" => "value".
     *
     * @param array &$record
     * The array where the resulting flat record is built.
     *
     * @param string $identity_field
     * The name of the identity field if exists. If the identity field is specified
     * and the record does not exist yet in the table, the source array is extended
     * with a pair "identity field" => "identity value" issued by the database by this
     * insert operation.
     *
     * @return void
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
    protected function processSubarray(array $subarray, array $key_fields, array &$parent_values, array &$record, string $identity_field): void
    {
        $current_key = array_shift($key_fields);

        foreach ($subarray as $key => $value) {
            if (!empty($current_key)) {
                if (!empty($parent_values[$current_key])) {
                    $record[$current_key] = $parent_values[$current_key];
                } else {
                    $record[$current_key] = $key;
                }

                $this->processSubarray($value, $key_fields, $parent_values, $record, $identity_field);
            } else {
                $record[$key] = $value;
            }
        }

        if (empty($current_key)) {
            $where_clause = [];

            foreach ($this->key_fields as $field) {
                if (!empty($record[$field])) {
                    $where_clause[$field] = $record[$field];
                }
            }

            $this->saveRecord($record, $where_clause, $identity_field);
        }
    } // processSubarray

    /**
     * Sets the dbworker to be used for working with the database.
     *
     * @param DBWorker $dbworker
     * The dbworker to be used for working with the database.
     *
     * @return void
     *
     * @see RecordsetManager::getDBWorker()
     *
     * @author Oleg Schildt
     */
    public function setDBWorker(DBWorker $dbworker): void
    {
        $this->dbworker = $dbworker;
    } // setDBWorker

    /**
     * Returns the dbworker to be used for working with the database.
     *
     * @return ?\SmartFactory\DatabaseWorkers\DBWorker
     * Returns the dbworker to be used for working with the database.
     *
     * @throws DBWorkerException
     * It might throw exceptions in the case of any errors.
     *
     * @author Oleg Schildt
     * @see RecordsetManager::getDBWorker()
     *
     */
    public function getDBWorker(): ?\SmartFactory\DatabaseWorkers\DBWorker
    {
        $this->dbworker?->connect();

        return $this->dbworker;
    } // getDBWorker

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
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if the query fails or if some object names are invalid.
     *
     * @see IRecordsetManager::describeTableFieldsQuery()
     *
     * @author Oleg Schildt
     */
    public function describeTableFields(string $table, array $fields, array $key_fields): void
    {
        $this->table = $table;
        $this->fields = $fields;

        if (is_array($key_fields)) {
            $this->key_fields = $key_fields;
        } else {
            $this->key_fields = [];
        }

        $this->validateParameters("table");
    } // describeTableFields

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
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if the query fails or if some object names are invalid.
     *
     * @see IRecordsetManager::describeTableFields()
     *
     * @author Oleg Schildt
     */
    public function describeTableFieldsQuery(array $fields, array $key_fields): void
    {
        $this->fields = $fields;

        if (is_array($key_fields)) {
            $this->key_fields = $key_fields;
        } else {
            $this->key_fields = [];
        }

        $this->validateParameters("query");
    } // describeTableFieldsQuery

    /**
     * Deletes records by a given where clause.
     *
     * @param array|string $where_clause
     * The where clause that should restrict the result. If an array of keys is passed,
     * the where clause is build automatically based on it.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see  RecordsetManager::saveRecord()
     * @see  RecordsetManager::deleteRecordsQuery()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function deleteRecords(array|string $where_clause): void
    {
        $this->validateParameters("table");

        $this->checkWhereClause($where_clause);

        $query = "delete from " . $this->table . "\n";

        $query .= $where_clause;

        $this->deleteRecordsQuery($query);
    } // deleteRecords

    /**
     * Deletes records by a given query.
     *
     * @param string $query
     * The query to be used.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see  RecordsetManager::deleteRecords()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function deleteRecordsQuery(string $query): void
    {
        $this->validateParameters("query");

        $this->dbworker->connect();

        $this->dbworker->execute_query($query);
    } // deleteRecordsQuery

    /**
     * Loads a record into an array in the form "field_name" => "value" based on a table.
     *
     * @param array &$record
     * The target array where the data should be loaded.
     *
     * @param array|string $where_clause
     * The where clause that should restrict the result to one record. If an array of keys is passed,
     * the where clause is build automatically based on it.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see  RecordsetManager::saveRecord()
     * @see  RecordsetManager::loadRecordSet()
     * @see  RecordsetManager::loadRecordQuery()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function loadRecord(array &$record, array|string $where_clause): void
    {
        $this->validateParameters("table");

        $this->checkWhereClause($where_clause);

        $query = "select\n";

        $query .= implode(", ", array_keys($this->fields)) . "\n";

        $query .= "from " . $this->table . "\n";

        $query .= $where_clause;

        $this->loadRecordQuery($record, $query);
    } // loadRecord

    /**
     * Loads a record into an array in the form "field_name" => "value".
     *
     * @param array &$record
     * The target array where the data should be loaded.
     *
     * @param string $query
     * The query to be used.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see  RecordsetManager::loadRecord()
     * @see  RecordsetManager::loadRecordSetQuery()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function loadRecordQuery(array &$record, string $query): void
    {
        $this->validateParameters("query");

        $this->dbworker->connect();

        $this->dbworker->execute_query($query);

        if ($this->dbworker->fetch_row()) {
            foreach ($this->fields as $field => $type) {
                $record[$field] = $this->dbworker->field_by_name($field, $type);
            }
        }

        $this->dbworker->free_result();
    } // loadRecordQuery

    /**
     * Loads records into an array in the form
     *
     * $records["key_field1"]["key_field2"]["key_fieldN"]["field_name"] = "value".
     *
     * baed on a table.
     *
     * @param array &$records
     * The target array where the data should be loaded.
     *
     * @param array|string $where_clause
     * The where clause that should restrict the result. If an array of keys is passed,
     * the where clause is build automatically based on it.
     *
     * @param string $order_clause
     * The order clause to sort the results.
     *
     * @param int $limit
     * The limit how many records should be loaded. 0 for unlimited.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see  RecordsetManager::loadRecord()
     * @see  RecordsetManager::saveRecordSet()
     * @see  RecordsetManager::loadRecordSetQuery()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function loadRecordSet(array &$records, array|string $where_clause, string $order_clause = "", int $limit = 0): void
    {
        $this->validateParameters("table");

        $this->checkWhereClause($where_clause);

        $query = $this->dbworker->build_select_query($this->table, array_keys($this->fields), $where_clause, $order_clause, $limit);

        $this->loadRecordSetQuery($records, $query);
    } // loadRecordSet

    /**
     * Loads records into an array in the form
     *
     * $records["key_field1"]["key_field2"]["key_fieldN"]["field_name"] = "value".
     *
     * baed on a query.
     *
     * @param array &$records
     * The target array where the data should be loaded.
     *
     * @param string $query
     * The query to be used.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see  RecordsetManager::loadRecordSet()
     * @see  RecordsetManager::loadRecordQuery()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function loadRecordSetQuery(array &$records, string $query): void
    {
        $this->validateParameters("query");

        $this->dbworker->connect();

        $this->dbworker->execute_query($query);

        while ($this->dbworker->fetch_row()) {
            $dimensions = [];
            $row = [];

            foreach ($this->fields as $field => $type) {
                $val = $this->dbworker->field_by_name($field, $type);

                $row[$field] = $val;

                if (in_array($field, $this->key_fields)) {
                    $dimensions[$field] = $val;
                }
            }

            if (!empty($dimensions)) {
                $reference = &$records;

                foreach ($dimensions as $dval) {
                    if (empty($reference[$dval])) {
                        $reference[$dval] = [];
                    }
                    $reference = &$reference[$dval];
                }

                $reference = $row;

                unset($reference);
            } else {
                $records[] = $row;
            }
        }

        $this->dbworker->free_result();
    } // loadRecordSetQuery

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
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see  RecordsetManager::loadRecord()
     * @see  RecordsetManager::saveRecordSet()
     *
     * @uses DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function saveRecord(array &$record, array|string $where_clause, string $identity_field = ""): void
    {
        $this->validateParameters("table");

        $this->dbworker->connect();

        $must_insert = false;

        // key_fields not specified - always insert
        // identity firld exists but its value is not specified - always insert
        if (empty($this->key_fields) || empty($where_clause)) {
            $must_insert = true;
        } else {
            // check existence

            $query = "select\n";
            $query .= "1\n";
            $query .= "from " . $this->table . "\n";

            $this->checkWhereClause($where_clause);

            $query .= $where_clause;

            $this->dbworker->execute_query($query);

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
            // if autoincrement
            if ($must_insert && $field == $identity_field) {
                continue;
            }

            // The value for the field is not passed in the record,
            // skip it
            if (!array_key_exists($field, $record)) {
                continue;
            }

            $value = $this->dbworker->prepare_for_query($record[$field] ?? "", $this->fields[$field] ?? "");

            $update_string .= $field . " = " . $value . ",\n";
            $insert_fields .= $field . ", ";
            $insert_values .= $value . ", ";
        }

        if ($must_insert) {
            $query = "insert into " . $this->table . " (" . trim($insert_fields, ", ") . ")\n";
            $query .= "values (" . trim($insert_values, ", ") . ")\n";

            $this->dbworker->execute_query($query);
        } elseif (!empty($update_string)) {
            $query = "update " . $this->table . " set\n";
            $query .= trim($update_string, ",\n") . "\n";
            $query .= $where_clause;

            $this->dbworker->execute_query($query);
        }

        if ($must_insert && !empty($identity_field)) {
            $record[$identity_field] = $this->dbworker->insert_id();
        }
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
     * @param string $identity_field
     * The name of the identity field if exists. If the identity field is specified
     * and the record does not exist yet in the table, the source array is extended
     * with a pair "identity field" => "identity value" issued by the database by this
     * insert operation.
     *
     * @return void
     *
     * @throws \Exception
     * It might throw an exception in the case of any errors:
     *
     * - if some parameters are missing.
     * - if dbworker does not extend {@see \SmartFactory\DatabaseWorkers\DBWorker}.
     * - if some parameters are not of the proper type.
     * - if the query fails or if some object names are invalid.
     *
     * @see  RecordsetManager::loadRecordSet()
     * @see  RecordsetManager::saveRecord()
     *
     * @uses DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function saveRecordSet(array $records, array $parent_values = [], string $identity_field = ""): void
    {
        $this->validateParameters("table");

        $this->dbworker->connect();

        $key_fields = $this->key_fields;
        $current_key = array_shift($key_fields);

        foreach ($records as $key => $value) {
            $record = [];

            if (!empty($parent_values[$current_key])) {
                $record[$current_key] = $parent_values[$current_key];
            } else {
                $record[$current_key] = $key;
            }

            $this->processSubarray($value, $key_fields, $parent_values, $record, $identity_field);
        }
    } // saveRecordSet

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
     * @throws \Exception
     * It might throw exceptions in the case of any errors.
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     * @see  IRecordsetManager::countRecordsQuery()
     *
     */
    public function countRecords(array|string $where_clause): int
    {
        $this->validateParameters("table");

        $this->checkWhereClause($where_clause);

        $query = "select\n";

        $query .= "count(*)\n";

        $query .= "from " . $this->table . "\n";

        if (!empty($where_clause)) {
            $query .= $where_clause . "\n";
        }

        return $this->countRecordsQuery($query);
    } // countRecords

    /**
     * Counts records based on the query.
     *
     * @param string $query
     * The query to be used.
     *
     * @return int
     * Returns the number of records.
     *
     * @throws \Exception
     * It might throw exceptions in the case of any errors.
     *
     * @see  IRecordsetManager::countRecords()
     *
     * @uses \SmartFactory\DatabaseWorkers\DBWorker
     *
     * @author Oleg Schildt
     */
    public function countRecordsQuery(string $query): int
    {
        $this->validateParameters("query");

        $this->dbworker->connect();

        $cnt = 0;

        $this->dbworker->execute_query($query);

        if ($this->dbworker->fetch_row()) {
            $cnt = $this->dbworker->field_by_num(0);
        }

        $this->dbworker->free_result();

        return $cnt;
    } // countRecordsQuery

    /**
     * Starts the translation.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @see RecordsetManager::commit_transaction()
     * @see RecordsetManager::rollback_transaction()
     *
     * @author Oleg Schildt
     */
    public function start_transaction(): void
    {
        $this->dbworker->connect();

        $this->dbworker->start_transaction();
    }

    /**
     * Commits the translation.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @see RecordsetManager::start_transaction()
     * @see RecordsetManager::rollback_transaction()
     *
     * @author Oleg Schildt
     */
    public function commit_transaction(): void
    {
        $this->dbworker->connect();

        $this->dbworker->commit_transaction();
    }

    /**
     * Rolls back the translation.
     *
     * @return void
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @see RecordsetManager::start_transaction()
     * @see RecordsetManager::commit_transaction()
     *
     * @author Oleg Schildt
     */
    public function rollback_transaction(): void
    {
        $this->dbworker->connect();

        $this->dbworker->rollback_transaction();
    }

    /**
     * Escapes the string so that it can be used in the query without causing an error.
     *
     * @param string $str
     * The string to be escaped.
     *
     * @return string
     * Returns the escaped string.
     *
     * @see RecordsetManager::format_date()
     * @see RecordsetManager::format_datetime()
     * @see RecordsetManager::quotes_or_null()
     * @see RecordsetManager::number_or_null()
     *
     * @author Oleg Schildt
     */
    public function escape(string $str): string
    {
        return $this->dbworker->escape($str);
    }

    /**
     * Escapes the string so that it can be used in the query without causing an error or returns the sstring NULL if the string is empty.
     *
     * @param string $str
     * The string to be escaped.
     *
     * @return string
     * Returns the escaped string.
     *
     * @see RecordsetManager::escape()
     * @see RecordsetManager::format_date()
     * @see RecordsetManager::format_datetime()
     * @see RecordsetManager::number_or_null()
     *
     * @author Oleg Schildt
     */
    function quotes_or_null(string $str): string
    {
        return $this->dbworker->quotes_or_null($str);
    }

    /**
     * Checks that the value is a number and returns it, or returns the string NULL if the value is empty.
     *
     * @param string $str
     * The string to be escaped.
     *
     * @return string
     * Returns the escaped string.
     *
     * @throws DBWorkerException
     * It might throw an exception in the case of any errors.
     *
     * @see RecordsetManager::escape()
     * @see RecordsetManager::format_date()
     * @see RecordsetManager::format_datetime()
     * @see RecordsetManager::quotes_or_null()
     *
     * @author Oleg Schildt
     */
    function number_or_null(string $str): string
    {
        return $this->dbworker->number_or_null($str);
    }

    /**
     * Formats the date to a string compatible for the corresponding database.
     *
     * @param int $date
     * The date value as timestamp.
     *
     * @return string
     * Returns the string representation of the date compatible for the corresponding database.
     *
     * @see RecordsetManager::escape()
     * @see RecordsetManager::format_datetime()
     * @see RecordsetManager::quotes_or_null()
     * @see RecordsetManager::number_or_null()
     *
     * @author Oleg Schildt
     */
    public function format_date(int $date): string
    {
        return $this->dbworker->format_date($date);
    }

    /**
     * Formats the date/time to a string compatible for the corresponding database.
     *
     * @param int $datetime
     * The date/time value as timestamp.
     *
     * @return string
     * Returns the string representation of the date/time compatible for the corresponding database.
     *
     * @see RecordsetManager::escape()
     * @see RecordsetManager::format_date()
     * @see RecordsetManager::quotes_or_null()
     * @see RecordsetManager::number_or_null()
     *
     * @author Oleg Schildt
     */
    public function format_datetime(int $datetime): string
    {
        return $this->dbworker->format_datetime($datetime);
    }
} // RecordsetManager
