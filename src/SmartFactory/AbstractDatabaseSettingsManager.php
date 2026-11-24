<?php

namespace SmartFactory;

use SmartFactory\DatabaseWorkers\DBWorker;

/**
 * Shares connection isolation and transaction ownership for database settings managers.
 *
 * @author Oleg Schildt
 */
abstract class AbstractDatabaseSettingsManager extends AbstractSettingsManager
{
    /**
     * The configured worker; statements are executed through connected clones.
     *
     * @var DBWorker|null
     *
     * @author Oleg Schildt
     */
    protected ?DBWorker $dbworker = null;

    /**
     * Whether saving requires an existing caller-managed transaction.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $no_own_transaction = false;

    /**
     * Selects the worker and transaction policy, discarding the previous snapshot.
     *
     * @param DBWorker $dbworker
     * The worker used to read and save settings.
     *
     * @param bool $no_own_transaction
     * True requires a caller-managed READ COMMITTED transaction. False starts one
     * when necessary and joins an existing READ COMMITTED transaction without committing it.
     *
     * @return void
     * Does not connect or load settings immediately.
     *
     * @author Oleg Schildt
     */
    public function setDBWorker(DBWorker $dbworker, bool $no_own_transaction = false): void
    {
        $this->dbworker = $dbworker;
        $this->no_own_transaction = $no_own_transaction;

        $this->resetSettings();
    } // setDBWorker

    /**
     * Validates an unquoted SQL table or column name before composing a query.
     *
     * @param string $name
     * The unquoted SQL identifier.
     *
     * @param bool $table
     * Whether up to three schema-qualified name components are allowed.
     *
     * @return void
     * Accepts the same identifier syntax as DBWorker::save_records().
     *
     * @throws \InvalidArgumentException
     * If the name is empty, quoted or contains unsupported SQL syntax.
     *
     * @author Oleg Schildt
     */
    protected function validateIdentifier(string $name, bool $table = false): void
    {
        $identifier = '[a-zA-Z_][a-zA-Z0-9_$#]*';
        $pattern = '/\A' . $identifier . ($table ? '(?:\.' . $identifier . '){0,2}' : '') . '\z/';

        if (!preg_match($pattern, $name)) {
            throw new \InvalidArgumentException("Invalid settings SQL identifier: " . $name);
        }
    } // validateIdentifier

    /**
     * Obtains a connected clone without replacing the caller's statements or results.
     *
     * @return DBWorker
     * Returns a worker sharing the connection and its transaction state.
     *
     * @throws \LogicException
     * If no worker has been configured.
     *
     * @throws \Throwable
     * If connecting to the configured database fails.
     *
     * @author Oleg Schildt
     */
    protected function databaseWorker(): DBWorker
    {
        if ($this->dbworker === null) {
            throw new \LogicException("The 'dbworker' is not specified.");
        }

        if (!$this->dbworker->is_connected()) {
            $this->dbworker->connect();
        }

        return $this->dbworker->create_clone();
    } // databaseWorker

    /**
     * Executes one aggregate save inside an owned or caller-managed transaction.
     *
     * @param callable(DBWorker): array<string, mixed> $action
     * The action which locks, merges and saves the complete settings aggregate.
     *
     * @return array<string, mixed>
     * Returns the saved snapshot after committing an owned transaction.
     *
     * @throws \LogicException
     * If a required external transaction is absent or an external transaction does
     * not use READ COMMITTED, which is required for fresh reads after acquiring a lock.
     *
     * @throws \Throwable
     * If connecting, saving or committing fails. Owned transactions are rolled back;
     * callers retain rollback responsibility for external transactions after any failure.
     *
     * @author Oleg Schildt
     */
    protected function withTransaction(callable $action): array
    {
        $worker = $this->databaseWorker();
        $level = $worker->get_transaction_isolation_level();
        $own_transaction = $level === null;

        if (
            ($this->no_own_transaction && $own_transaction)
            || ($level !== null && $level !== DBWorker::ISOLATION_READ_COMMITTED)
        ) {
            throw new \LogicException("Settings require a READ COMMITTED transaction; the configured caller transaction is absent or incompatible.");
        }

        if ($own_transaction) {
            $worker->start_transaction(DBWorker::ISOLATION_READ_COMMITTED);
        }

        try {
            $data = $action($worker);

            if ($own_transaction) {
                $worker->commit_transaction();
            }

            return $data;
        } catch (\Throwable $exception) {
            if ($own_transaction && $worker->get_transaction_isolation_level() !== null) {
                try {
                    $worker->rollback_transaction();
                } catch (\Throwable) {
                    // Retain the original failure if connection recovery is also unsuccessful.
                }
            }

            throw $exception;
        } finally {
            $worker->free_prepared_query();
        }
    } // withTransaction
} // AbstractDatabaseSettingsManager

