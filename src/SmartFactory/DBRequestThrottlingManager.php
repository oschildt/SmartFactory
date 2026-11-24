<?php
/**
 * Provides database-backed storage for rolling-window API throttling.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

use SmartFactory\DatabaseWorkers\ClobValue;
use SmartFactory\DatabaseWorkers\DBWorker;
use SmartFactory\DatabaseWorkers\DBWorkerException;

/**
 * Persists one caller's request history in one database row under a transactional logical-key lock.
 *
 * Uses DBWorker::lock_record(), including keys without a stored row, and save_records() for bound
 * upserts. The configured database needs the target table and DBWorker's dbworker_locks service table.
 * Each admission owns a READ COMMITTED transaction so application rollbacks cannot refund requests.
 * Connected clones preserve the caller's prepared statement while sharing its native connection.
 *
 * @link markdown:DBRequestThrottlingManager.md Important notes
 *
 * @author Oleg Schildt
 */
class DBRequestThrottlingManager extends AbstractRequestThrottlingManager
{
    /**
     * Stores the configured database worker, connected lazily when admission is requested.
     *
     * @var DBWorker|null
     *
     * @author Oleg Schildt
     */
    protected ?DBWorker $dbworker = null;

    /**
     * Stores the validated, optionally schema-qualified target table name.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $table = "";

    /**
     * Stores the isolated statement worker during an owned admission transaction.
     *
     * @var DBWorker|null
     *
     * @author Oleg Schildt
     */
    private ?DBWorker $active_worker = null;

    /**
     * Stores the hashed caller key owning the current transaction.
     *
     * @var string|null
     *
     * @author Oleg Schildt
     */
    private ?string $active_key = null;

    /**
     * Stores the validated, schema-qualified table used by the active transaction.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    private string $active_table = "";

    /**
     * Configures the database worker, storage table and inherited rolling-window limits.
     *
     * @param array{dbworker: DBWorker, table: string, limits?: list<array{0: positive-int, 1: positive-int}>} $parameters
     * The worker and an unquoted table name, optionally qualified by schema or database.
     * The table must contain client_key, request_times and expires_at columns as documented
     * in DBRequestThrottlingManager.md. Omitted limits use DEFAULT_LIMITS; an empty list disables limits.
     *
     * @return void
     * Applies validated configuration without connecting, creating tables or clearing stored history.
     * Invalid initialization preserves the previous configuration.
     *
     * @throws \InvalidArgumentException
     * If dbworker is not a DBWorker, the table is missing or is not a supported SQL identifier,
     * or the limit policy is invalid.
     *
     * @throws \LogicException
     * If initialization is attempted during an active admission operation.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void
    {
        if ($this->active_worker !== null) {
            throw new \LogicException("The throttling manager cannot be reinitialized during admission.");
        }

        $worker = $parameters["dbworker"] ?? null;
        $table = $parameters["table"] ?? null;

        if (!$worker instanceof DBWorker) {
            throw new \InvalidArgumentException("The dbworker must be a DBWorker instance.");
        }

        if (!is_string($table)) {
            throw new \InvalidArgumentException("The throttling table must be an SQL identifier.");
        }

        $this->validateTable($table);

        parent::init($parameters);

        $this->dbworker = $worker;
        $this->table = $table;
    } // init

    /**
     * Validates an unquoted table identifier before composing SQL.
     *
     * @param string $table
     * A table name with at most three identifier components separated by dots.
     *
     * @return void
     * Accepts the identifier syntax used by DBWorker::lock_record() and save_records().
     *
     * @throws \InvalidArgumentException
     * If the table name is empty, quoted or contains unsupported SQL syntax.
     *
     * @author Oleg Schildt
     */
    private function validateTable(string $table): void
    {
        if (!preg_match('/\A[a-zA-Z_][a-zA-Z0-9_$#]*(?:\.[a-zA-Z_][a-zA-Z0-9_$#]*){0,2}\z/', $table)) {
            throw new \InvalidArgumentException("The throttling table must be an unquoted SQL identifier.");
        }
    } // validateTable

    /**
     * Serializes one caller's complete admission operation inside an owned READ COMMITTED transaction.
     *
     * @param string $key
     * The lowercase SHA-256 caller hash supplied by the inherited admission algorithm.
     *
     * @param callable(): int $operation
     * The complete clock/read/check/write operation to run after acquiring the caller's logical-key lock.
     *
     * @return int
     * Returns the admission result only after committing and releasing the logical-key lock.
     *
     * @throws \LogicException
     * If no worker is configured, admission is nested, or the configured connection already has
     * a managed transaction. Use a separate connection when application work is already transactional.
     *
     * @throws \InvalidArgumentException
     * If the worker's schema produces an unsupported qualified table name.
     *
     * @throws \Throwable
     * If connecting, cloning, starting, locking, the callback, committing or statement cleanup fails.
     * DBWorkerException failures are wrapped in RuntimeException with the original cause so handlers report 500.
     * Owned transactions are rolled back after failures when still active. A rollback or cleanup failure
     * does not replace the original failure; a connection whose rollback failed needs caller recovery.
     *
     * @author Oleg Schildt
     */
    protected function executeAtomically(string $key, callable $operation): int
    {
        try {
            return $this->withTransaction($key, $operation);
        } catch (DBWorkerException $exception) {
            // Storage failures are system errors rather than client-facing DBWorker validation messages.
            throw new \RuntimeException("The database throttling storage operation failed.", 0, $exception);
        }
    } // executeAtomically

    /**
     * Owns a READ COMMITTED transaction and its selected caller's logical-key lock.
     *
     * @param string $key
     * The caller hash identifying the admission operation.
     *
     * @param callable(): int $operation
     * The complete admission callback, invoked only after locking this key.
     *
     * @return int
     * Returns the result after committing the owned transaction.
     *
     * @throws \LogicException
     * If configuration is missing, admission is nested or an application transaction is already active.
     *
     * @throws \Throwable
     * If connection setup, qualification, transaction operations, locking, the callback or cleanup fails.
     * A failed owned transaction is rolled back when still active without ending an application transaction.
     *
     * @author Oleg Schildt
     */
    private function withTransaction(string $key, callable $operation): int
    {
        if ($this->dbworker === null || $this->table === "") {
            throw new \LogicException("The database throttling manager is not initialized.");
        }

        if ($this->active_worker !== null) {
            throw new \LogicException("Nested throttling admission operations are not supported.");
        }

        if (!$this->dbworker->is_connected()) {
            $this->dbworker->connect();
        }

        $worker = $this->dbworker->create_clone();

        if ($worker->get_transaction_isolation_level() !== null) {
            throw new \LogicException("Database throttling requires a connection without an active managed transaction.");
        }

        $table = $worker->qualify_name_with_schema($this->table);

        $this->validateTable($table);

        $worker->start_transaction(DBWorker::ISOLATION_READ_COMMITTED);

        $this->active_worker = $worker;
        $this->active_key = $key;
        $this->active_table = $table;
        $failure = null;

        try {
            // The logical lock also protects simultaneous first admissions before the target row exists.
            $worker->lock_record($table, ["client_key" => $key]);

            $result = $operation();

            $worker->commit_transaction();

            return $result;
        } catch (\Throwable $exception) {
            $failure = $exception;

            if ($worker->get_transaction_isolation_level() !== null) {
                try {
                    $worker->rollback_transaction();
                } catch (\Throwable) {
                    // Preserve the admission failure; the caller must recover an unusable connection.
                }
            }

            throw $exception;
        } finally {
            $this->active_worker = null;
            $this->active_key = null;
            $this->active_table = "";

            try {
                $worker->free_prepared_query();
            } catch (\Throwable $cleanup_exception) {
                if ($failure === null) {
                    throw $cleanup_exception;
                }
            }
        }
    } // withTransaction

    /**
     * Retrieves the statement worker belonging to the current caller's owned transaction.
     *
     * @param string $key
     * The caller hash requested by the storage hook.
     *
     * @return DBWorker
     * Returns the active clone only for the caller protected by the current logical-key lock.
     *
     * @throws \LogicException
     * If no admission operation is active or the supplied key belongs to another caller.
     *
     * @author Oleg Schildt
     */
    private function activeWorker(string $key): DBWorker
    {
        if ($this->active_worker === null || $this->active_key !== $key) {
            throw new \LogicException("Throttling storage access requires the active caller's transaction.");
        }

        return $this->active_worker;
    } // activeWorker

    /**
     * Loads and validates the JSON timestamp list stored for one caller.
     *
     * @param string $key
     * The lowercase SHA-256 caller hash supplied to executeAtomically().
     *
     * @return list<float>
     * Returns ordered finite, nonnegative timestamps, preserving duplicates, or an empty list
     * when the caller has no row. Existing null, empty, malformed or non-array payloads fail closed.
     *
     * @throws \LogicException
     * If the key does not belong to the active admission transaction.
     *
     * @throws \RuntimeException
     * If the caller key is not unique or stored history is invalid.
     *
     * @throws \Throwable
     * If preparing, binding, reading, converting a CLOB or releasing the statement fails.
     *
     * @author Oleg Schildt
     */
    protected function loadRecords(string $key): array
    {
        $worker = $this->activeWorker($key);
        $failure = null;

        try {
            $worker->prepare_query("SELECT request_times FROM " . $this->active_table . " WHERE client_key = ?");

            $worker->execute_prepared_query($key);

            if (!$worker->fetch_row()) {
                return [];
            }

            $json = $worker->field_by_name("request_times", DBWorker::DB_STRING);

            if ($worker->fetch_row()) {
                throw new \RuntimeException("The throttling caller key is not unique.");
            }
        } catch (\Throwable $exception) {
            $failure = $exception;

            throw $exception;
        } finally {
            try {
                $worker->free_prepared_query();
            } catch (\Throwable $cleanup_exception) {
                if ($failure === null) {
                    throw $cleanup_exception;
                }
            }
        }

        if (!is_string($json) || $json === "") {
            throw new \RuntimeException("The stored throttling history is empty or is not text.");
        }

        try {
            $values = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException("The stored throttling history is invalid JSON.", 0, $exception);
        }

        if (!is_array($values)) {
            throw new \RuntimeException("The stored throttling history must be a JSON array.");
        }

        $timestamps = [];
        $previous = -1.0;

        foreach ($values as $value) {
            if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value < 0 || $value < $previous) {
                throw new \RuntimeException("The stored throttling timestamps are invalid or out of order.");
            }

            $timestamp = (float) $value;
            $timestamps[] = $timestamp;
            $previous = $timestamp;
        }

        return $timestamps;
    } // loadRecords

    /**
     * Upserts only the selected caller's history and its indexed expiry timestamp.
     *
     * @param string $key
     * The lowercase SHA-256 caller hash supplied to executeAtomically().
     *
     * @param list<float> $timestamps
     * This caller's nonempty ordered timestamp list prepared by the common admission algorithm.
     * Equal timestamps represent separate requests and are preserved.
     *
     * @return void
     * Saves the caller's JSON/CLOB payload and expiry, preserving every other caller.
     * Expiry is the newest retained timestamp plus the longest configured window.
     *
     * @throws \LogicException
     * If the key does not belong to the active admission transaction or the list is unexpectedly empty.
     *
     * @throws \Throwable
     * If JSON encoding, CLOB creation or the bound transactional upsert fails.
     *
     * @author Oleg Schildt
     */
    protected function saveRecords(string $key, array $timestamps): void
    {
        $worker = $this->activeWorker($key);

        if ($timestamps === []) {
            throw new \LogicException("An admitted throttling request must contain at least one timestamp.");
        }

        $json = json_encode($timestamps, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $expires_at = max($timestamps) + max(array_column($this->limits, 1));
        $record = [
            "client_key" => $key,
            "request_times" => new ClobValue($json),
            "expires_at" => sprintf("%.6F", $expires_at),
        ];

        $worker->save_records($record, [
            "table" => $this->active_table,
            "fields" => [
                "client_key" => DBWorker::DB_STRING,
                "request_times" => DBWorker::DB_AS_IS,
                "expires_at" => DBWorker::DB_NUMBER,
            ],
            "array_dimension_keys" => [],
            "record_key_fields" => ["client_key"],
        ]);
    } // saveRecords
} // DBRequestThrottlingManager
