<?php
/**
 * Implements centralized database storage for application settings.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\DatabaseWorkers\ClobValue;
use SmartFactory\DatabaseWorkers\DBWorker;

/**
 * Stores application-wide settings centrally in a database as a JSON document.
 *
 * Central storage allows an application distributed across several servers to share
 * the same settings. A stable record key identifies the document. Saves lock that key,
 * read the latest document and merge only explicitly edited parameters in one transaction.
 * Concurrent edits to other parameters survive; the last save wins for the same parameter.
 * Values are cached in this object; call loadSettings() to refresh a long-lived instance.
 * Contextual validation supports separate forms, but drafts are not stored between requests.
 *
 * @see ConfigSettingsManager
 * @see UserSettingsManager
 *
 * @author Oleg Schildt
 */
class ApplicationSettingsManager extends AbstractDatabaseSettingsManager
{
    /**
     * The table containing application settings documents.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $settings_table = "";

    /**
     * The column containing the JSON document.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $settings_column = "";

    /**
     * The non-generated unique key column identifying a settings document.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $settings_id_column = "id";

    /**
     * The document key, allowing separate applications to share one settings table.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $settings_id = 1;

    /**
     * Configures database-backed settings without loading them immediately.
     *
     * @param array{dbworker: DBWorker, settings_table: string, settings_column: string, settings_id_column?: string, settings_id?: int, no_own_transaction?: bool} $parameters
     * The worker, table and JSON column. The unique, non-generated integer key defaults
     * to id = 1. A missing document is created on the first save. When no_own_transaction
     * is true the caller must already have started a READ COMMITTED transaction.
     * The settings table must provide the configured unique document key.
     *
     * @return void
     * Discards the previous snapshot and unsaved edits.
     *
     * @throws \InvalidArgumentException
     * If the worker, identifier names or document key have invalid types or values,
     * or the key column and JSON column have the same name.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void
    {
        $worker = $parameters['dbworker'] ?? null;
        $table = $parameters['settings_table'] ?? null;
        $column = $parameters['settings_column'] ?? null;
        $key = $parameters['settings_id_column'] ?? 'id';
        $id = $parameters['settings_id'] ?? 1;

        if (!$worker instanceof DBWorker || !is_string($table) || !is_string($column)
            || !is_string($key) || !is_int($id) || strcasecmp($column, $key) === 0) {
            throw new \InvalidArgumentException("Invalid application settings configuration.");
        }

        $this->validateIdentifier($table, true);

        $this->validateIdentifier($column);

        $this->validateIdentifier($key);

        $this->settings_table = $table;
        $this->settings_column = $column;
        $this->settings_id_column = $key;
        $this->settings_id = $id;

        $this->setDBWorker($worker, (bool) ($parameters['no_own_transaction'] ?? false));
    } // init

    /**
     * Verifies that the document location and worker have been configured.
     *
     * @return void
     * Does not open a connection or inspect table metadata.
     *
     * @throws \LogicException
     * If init() has not supplied a worker, table and JSON column.
     *
     * @author Oleg Schildt
     */
    protected function validateParameters(): void
    {
        if ($this->dbworker === null || $this->settings_table === "" || $this->settings_column === "") {
            throw new \LogicException("The application settings manager is not initialized.");
        }
    } // validateParameters

    /**
     * Reads one keyed JSON document through a bound query.
     *
     * @param DBWorker $worker
     * The connected worker, optionally holding the document's logical key lock.
     *
     * @return array<string, mixed>
     * Returns the decoded document, or an empty array for a missing row or NULL payload.
     *
     * @throws \UnexpectedValueException
     * If the JSON value is not an object or array, or duplicate document keys are found.
     *
     * @throws \Throwable
     * If preparing, executing, fetching or decoding the document fails.
     *
     * @author Oleg Schildt
     */
    protected function readJSON(DBWorker $worker): array
    {
        try {
            $worker->prepare_query('SELECT ' . $this->settings_column . ' FROM ' . $this->settings_table
                . ' WHERE ' . $this->settings_id_column . ' = ?');

            $worker->execute_prepared_query($this->settings_id);

            if (!$worker->fetch_row()) {
                return [];
            }

            $json = $worker->field_by_name($this->settings_column, DBWorker::DB_STRING);

            if ($worker->fetch_row()) {
                throw new \UnexpectedValueException("The application settings key is not unique.");
            }
        } finally {
            $worker->free_prepared_query();
        }

        if ($json === null || $json === '') {
            return [];
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \UnexpectedValueException("Application settings JSON must contain an object or array.");
        }

        return $data;
    } // readJSON

    /**
     * Loads the configured document using an isolated prepared statement.
     *
     * @param array<string, mixed> &$data
     * Receives the complete document after successful loading.
     *
     * @return void
     * Does not modify the currently cached settings snapshot.
     *
     * @throws \Throwable
     * If initialization, connection, reading or decoding fails.
     *
     * @see ApplicationSettingsManager::saveJSON()
     *
     * @author Oleg Schildt
     */
    protected function loadJSON(array &$data): void
    {
        $this->validateParameters();

        $data = $this->readJSON($this->databaseWorker());
    } // loadJSON

    /**
     * Locks the document key, merges local edits and saves through save_records().
     *
     * @param array<string, mixed> &$data
     * The local snapshot, replaced with the merged document only after success.
     *
     * @return void
     * Creates a missing keyed document and preserves unrelated concurrent edits.
     *
     * @throws \Throwable
     * If initialization, transaction setup, locking, reading, encoding or saving fails.
     * External transactions remain the caller's responsibility and must be rolled back on failure.
     *
     * @see ApplicationSettingsManager::loadJSON()
     *
     * @author Oleg Schildt
     */
    protected function saveJSON(array &$data): void
    {
        $this->validateParameters();

        $data = $this->withTransaction(function (DBWorker $worker) use ($data): array {
            // Lock before reading, including when the document has not been created yet.
            $worker->lock_record($this->settings_table, [$this->settings_id_column => $this->settings_id]);

            $merged = $this->readJSON($worker);

            foreach ($this->changed as $name => $_) {
                $merged[$name] = $data[$name];
            }

            $json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            $record = [$this->settings_id_column => $this->settings_id,
                $this->settings_column => new ClobValue($json)];

            $worker->save_records($record, [
                'table' => $this->settings_table,
                'fields' => [$this->settings_id_column => DBWorker::DB_NUMBER,
                    $this->settings_column => DBWorker::DB_AS_IS],
                'array_dimension_keys' => [],
                'record_key_fields' => [$this->settings_id_column]
            ]);

            return $merged;
        });
    } // saveJSON

    /**
     * Implements the shared loading contract using the JSON document reader.
     *
     * @param array<string, mixed> &$data
     * Receives the complete document.
     *
     * @return void
     * Leaves the active snapshot unchanged until loading succeeds.
     *
     * @throws \Throwable
     * If connecting, reading or decoding the configured document fails.
     *
     * @author Oleg Schildt
     */
    protected function loadSettingsData(array &$data): void
    {
        $this->loadJSON($data);
    } // loadSettingsData

    /**
     * Implements the shared saving contract using a transactional JSON merge.
     *
     * @param array<string, mixed> &$data
     * Receives the merged document after successful saving.
     *
     * @return void
     * Leaves change tracking to the public save method.
     *
     * @throws \Throwable
     * If transaction setup, locking, merging or saving fails.
     *
     * @author Oleg Schildt
     */
    protected function saveSettingsData(array &$data): void
    {
        $this->saveJSON($data);
    } // saveSettingsData
} // ApplicationSettingsManager
