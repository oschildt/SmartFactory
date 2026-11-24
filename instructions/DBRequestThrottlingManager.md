# DBRequestThrottlingManager

`SmartFactory\DBRequestThrottlingManager` extends `AbstractRequestThrottlingManager`
and implements the existing `IRequestThrottlingManager` contract through that base.
It stores one caller's timestamp list in one database row. MySQL, PostgreSQL, SQL
Server and Oracle use the same adapter through their DBWorker implementations.

## Initialization

Supply a configured `DBWorker` and an unquoted table name. Connecting is lazy;
initialization does not create tables or clear stored history.

```php
$manager = new \SmartFactory\DBRequestThrottlingManager();

$manager->init([
    "dbworker" => $dbworker,
    "table" => "api_request_throttling",
    "limits" => [[3, 1], [15, 10], [1550, 60], [10000, 500]],
]);

$handler->init([
    "throttling_manager" => $manager,
    "user_identificator_getter" => static fn(): string|int|null => $_SESSION["user_id"] ?? null,
]);
```

`limits` is optional and defaults to `IRequestThrottlingManager::DEFAULT_LIMITS`.
An empty list disables limits without database access. Identity resolution stays
in RequestHandler, including IP fallback. The table stores SHA-256 caller keys,
not raw user IDs, API credentials or IP addresses.

Factory bindings can continue using `IRequestThrottlingManager::class` with a
creation function returning an initialized `DBRequestThrottlingManager`.

## Storage table

Create the table before use. Its name is configurable; column names are fixed:

| Column | Contract |
| --- | --- |
| `client_key` | Primary key: the 64-character lowercase SHA-256 caller hash. |
| `request_times` | Non-null text/CLOB containing a JSON array of ordered Unix timestamps with subsecond precision. Equal timestamps are separate requests and are retained. |
| `expires_at` | Non-null numeric Unix timestamp: newest retained request plus the longest configured period. Indexed for cleanup. |

A caller owns one row. Text storage provides consistent binding and validation
across all four databases. Null, empty, malformed, unordered, negative or nonnumeric
history is rejected instead of resetting a budget.

Package DDL:

- [MySQL](../../sql/request_throttling_mysql.sql)
- [PostgreSQL](../../sql/request_throttling_postgresql.sql)
- [SQL Server](../../sql/request_throttling_mssql.sql)
- [Oracle](../../sql/request_throttling_oracle.sql)

For MySQL:

```sql
CREATE TABLE api_request_throttling (
    client_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_times LONGTEXT NOT NULL,
    expires_at DECIMAL(30, 6) NOT NULL,
    PRIMARY KEY (client_key),
    INDEX api_request_throttling_expiry (expires_at)
) ENGINE=InnoDB;
```

PostgreSQL uses TEXT/NUMERIC, SQL Server NVARCHAR(MAX)/DECIMAL, and Oracle CLOB/NUMBER.
All processes sharing a caller key must use the same limits. Increasing retention
cannot restore timestamps already pruned under an earlier policy.

## Logical-key lock service

`DBWorker::lock_record()` and `save_records()` require `dbworker_locks` in the
driver's standard location. The demo database creation scripts already include it.
The target-table DDL above does not recreate this shared service table.

If missing, run only the statement for the database being configured:

```sql
-- MySQL: current database.
CREATE TABLE dbworker_locks (
    resource_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    resource_name LONGTEXT NULL
) ENGINE=InnoDB;

-- PostgreSQL.
CREATE TABLE public.dbworker_locks (
    resource_key VARCHAR(64) NOT NULL PRIMARY KEY,
    resource_name TEXT NULL
);

-- SQL Server.
CREATE TABLE dbo.dbworker_locks (
    resource_key CHAR(64) COLLATE Latin1_General_100_BIN2 NOT NULL PRIMARY KEY,
    resource_name NVARCHAR(MAX) NULL
);

-- Oracle: current application schema.
CREATE TABLE dbworker_locks (
    resource_key CHAR(64 BYTE) NOT NULL PRIMARY KEY,
    resource_name CLOB NULL
);
```

See [DBWorker's save/lock protocol](DatabaseWorkers/SAVE_RECORDS.md).
Do not delete service-lock rows while cooperating writers are active.

## Transactions

Each admission obtains a connected clone, starts its own explicit READ COMMITTED
transaction, locks `["client_key" => $key]` through `lock_record()`, runs the inherited
clock/read/check/write operation, and commits before returning its result.

Logical-key locking covers simultaneous first requests before a caller row exists.
Different caller keys use different logical locks. Rejections do not update the
caller's history or extend its expiry.

An existing managed transaction on the supplied connection is rejected without
ending it. Joining application transactions would let a later rollback erase an
already admitted request. Use a separate DBWorker connection if throttling runs
while application work is transactional; a clone shares the native connection.

Clones preserve prepared statement definitions but share native connection
constraints. Finish unread results before admission when the driver cannot run
another command on the same connection.

Failed owned transactions are rolled back when still active. A rollback or cleanup
failure does not replace the primary exception. A failed rollback requires caller
connection recovery. DBWorkerException failures are wrapped in RuntimeException
with the original cause, so RequestHandler logs a neutral HTTP 500 system error.

## Expiry maintenance

The common algorithm prunes the current caller's timestamps during admitted updates.
The DB adapter does not scan other callers. Schedule separate cleanup of inactive
rows through indexed `expires_at` and a bound cutoff from the same time source:

```php
$cleanup = $dbworker->create_clone();

try {
    $cleanup->prepare_query("DELETE FROM api_request_throttling WHERE expires_at < ?");

    $cleanup->execute_prepared_query(sprintf("%.6F", microtime(true)));
} finally {
    $cleanup->free_prepared_query();
}
```

Use a trusted table name and a clock consistent with admission workers. Expiry uses
the newest retained timestamp, conservatively retaining future entries after a
clock rollback. Both the target and service tables require transactional storage.

## Verification

From the development project root:

```text
php -d xdebug.mode=off tools/phpunit.phar --filter DBRequestThrottlingManagerTest
php -d xdebug.mode=off application/tests/dbworkers/throttling.php
```

Unit tests use mock workers and deterministic time without database access. The
integration runner uses all four demo connections and package DDL. It checks isolated
caller histories, retry delays, statement preservation, transaction ownership,
error recovery, large CLOB payloads, corruption and simultaneous admissions through
independent PHP processes. Unique fixture tables and only their own service-lock
rows are removed afterward; demo data is not modified.
