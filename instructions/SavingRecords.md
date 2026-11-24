# Concurrent record saving

`DBWorker::save_records()` serializes saves of the same configured record key.
Different keys use different service rows. Only this method participates in the
protocol: `execute_query()`, prepared queries, procedures and external applications
do not acquire these service locks automatically.

## Service table

Install `dbworker_locks` before calling `save_records()`. Its columns are:

* `resource_key`: a 64-character SHA-256 hexadecimal digest and primary key.
* `resource_name`: nullable text (`TEXT`, `NVARCHAR(MAX)` or `CLOB`).

MySQL requires InnoDB. The default locations are the target database in MySQL,
`dbo.dbworker_locks` in the target SQL Server database, `public.dbworker_locks` in
PostgreSQL, and `DBWORKER_LOCKS` in the worker's current Oracle schema.

Service rows are created on demand. The drivers handle an expected duplicate
while creating a row, then retain its exclusive lock until transaction completion.
There is no application-maintained `locked` flag. Rows remain after unlock.

For an ordinary record, the digest includes the canonical physical table name,
sorted key column names and native fingerprints of the converted key values.
Schema-qualified and unqualified names for the same table resolve to the same
resource. Key names may be supplied in a different order. Key values are not
written to `resource_name`; its record description contains table and field names.

All callers saving the same logical record must configure the same set of
`record_key_fields`. Using different candidate keys for the same row does not
automatically coordinate those two resources. Keep suitable primary/unique
constraints as the database's final integrity protection.

## Value comparison

The key is derived after `record_bind_value()` conversion. Empty strings become
NULL, numeric values are interpreted using column types, and NULL is compared
with `IS NULL`. Scalar key types must support the driver's normalization SQL;
unsupported types raise `DBWorkerException` before a record is written.

* MySQL uses typed expressions and native collation sort weights, including the
  collation's trailing-space rules. ENUM, SET, YEAR and spatial keys are not supported.
* SQL Server uses typed conversions and collation-aware CHECKSUM for types whose
  equality cannot safely be reproduced in PHP. Integer/decimal and binary-collated
  string keys retain their full native representations. CHECKSUM collisions can
  make unrelated keys wait for one another; the real UPDATE predicate still uses
  the original typed values, so a collision cannot select the wrong record.
* PostgreSQL uses its type-aware extended record hash, including collation-aware
  hashing for text. A type must provide a compatible native hash implementation.
* Oracle uses typed numeric/date representations and linguistic sort keys.
  The metadata query requires Oracle 12.2 or later.

Other native locks, unique-index checks, foreign keys and database deadlocks can
still cause waiting. A service-row protocol does not eliminate database locking.

## Transactions and errors

With no active managed transaction, the method begins and completes one
transaction for the whole call. A failed call rolls back its earlier writes.
The input array is updated only after the complete call succeeds.

Inside a transaction opened with `start_transaction()`, the method neither commits
nor rolls back the caller's transaction. All service locks remain until the caller
commits or rolls back. After a failed save, subsequent saves and commit are rejected
until rollback; clones share this state. Rolling back the caller transaction does
not undo IDs already returned to a PHP array by an earlier successful call.

Manage transactions only through DBWorker's transaction methods. Transaction SQL,
DDL that commits implicitly and direct native connection use are outside this
managed-state contract. `start_transaction(?string $isolation_level = null)` uses
the worker's `default_isolation_level` property when the argument is omitted or
NULL. The property initially contains READ COMMITTED and can be configured through
`init()` or the `dbworker()` factory:

```php
$dbw = dbworker([
    'db_type' => 'MySQL',
    // Other connection settings are supplied here.
    'default_isolation_level' => DBWorker::ISOLATION_SERIALIZABLE
]);
```

An explicit argument overrides the property for one transaction without changing
the configured default. The legacy `isolation_level` option affects the native
session default, independently of this worker property. Pass one of these constants
to choose a level explicitly:

```php
$dbw->start_transaction(DBWorker::ISOLATION_READ_COMMITTED);
$dbw->start_transaction(DBWorker::ISOLATION_REPEATABLE_READ);
$dbw->start_transaction(DBWorker::ISOLATION_SERIALIZABLE);
```

Only these three levels are accepted. Oracle rejects REPEATABLE READ before
starting a transaction. The choice applies to one managed transaction; SQL Server
restores its previous session setting after commit or rollback, including
completion through a clone. MySQL and PostgreSQL use transaction-scoped settings.
Oracle applies SET TRANSACTION before any transaction reads or writes; its
read-only mode retains Oracle's stronger snapshot-read semantics.

Changing `default_isolation_level` with `init()` affects subsequent parameterless
starts, not the active transaction. Invalid configured values raise DBWorkerException
and do not replace the previous property value. A clone copies the configured
default while continuing to share active transaction state with its connection.
Unsupported native levels, such as Oracle's REPEATABLE READ, are rejected when
the selected transaction is started; an explicit supported argument can still
override an unsupported configured default.

The internally owned `save_records()` transaction and the short identity-allocation
transaction explicitly request READ COMMITTED because the locking protocol requires
fresh snapshots. The application's configured default does not change those
internal transactions. A caller-owned transaction still retains its chosen level
and must satisfy the save operation's isolation requirements.

PostgreSQL and Oracle saves require READ COMMITTED so a
waiting operation sees the preceding writer's committed row. Oracle checks the
active transaction's chosen level rather than its connection default. MySQL uses a current locking read, including
when the caller has already established a REPEATABLE READ snapshot; InnoDB's
native gap locks at that isolation level can additionally block nearby keys.
SQL Server SNAPSHOT isolation is rejected before saving; READ COMMITTED SNAPSHOT
at the database level remains compatible with statement-level READ COMMITTED.

Database errors unrelated to the selected key, lock timeouts and deadlocks remain
exceptions. A deadlocked or otherwise aborted caller transaction must be rolled
back and the complete application operation retried; the library does not replay
arbitrary caller work.

## Identity contract

* An absent, NULL or empty-string identity requests a generated ID.
* A supplied integer identity, including zero and negative values, is preserved.
* A known record key is checked only after its service lock is acquired: an
  existing row is updated, otherwise a row is inserted using the supplied ID.
* With no `record_key_fields`, each call inserts. Clear a previously returned ID
  before another generated INSERT; otherwise that supplied ID is preserved.

The identity must fit a PHP integer and the native column type. An identity field
must identify an actual generated column. Missing non-identity fields retain
defaults on INSERT and current values on UPDATE. At least one configured
non-identity field must be supplied. Identity columns are excluded from UPDATE.

MySQL explicitly preserves zero using a temporary NO_AUTO_VALUE_ON_ZERO setting
and restores SQL mode afterward. SQL Server performs explicit-ID insertion and
IDENTITY_INSERT cleanup in the same prepared TRY/CATCH batch. SQL Server requires
table ownership or ALTER permission for that operation.

PostgreSQL supports GENERATED ALWAYS through OVERRIDING SYSTEM VALUE. Oracle
explicit-ID insertion requires GENERATED BY DEFAULT; GENERATED ALWAYS remains
supported for ordinary native generated INSERTs, but cannot accept a supplied ID.
The method never changes Oracle table definitions while saving a record.

### Sequence-backed allocation in PostgreSQL and Oracle

To support arbitrary explicit IDs without transactional DDL, these drivers keep
an additional identity-allocation resource in the same service table. Its
`resource_name` contains a JSON high-water mark. A short independent connection
transaction locks this resource, obtains a native sequence value when needed,
checks the committed maximum ID, reserves the next sufficiently large ID and
commits the watermark before the record is written. It is released immediately,
rather than remaining locked for the caller's potentially long transaction.

This requires permission to open another connection with the configured demo or
application credentials. Allocation may leave gaps after failed or rolled-back
record saves. A per-ID lock and a fresh existence check handle an explicit-ID
writer taking a reserved candidate before the generated INSERT acquires its lock.

The watermark coordinates `save_records()` ID allocation. It does not rewrite
the Oracle/PostgreSQL native sequence counter to every explicit high ID. Direct
SQL using sequence defaults does not participate and must not be mixed with this
allocation protocol without separate coordination. Never edit or delete active
service resources manually.

## Form migration

Negative IDs are no longer implicit temporary identifiers. Translate a form's
temporary marker to a missing/NULL identity before calling `save_records()`.
An empty identity array dimension can represent a new record; after INSERT it is
replaced with the actual ID. The page-recordset example translates its `-1` marker
before saving, while keeping translation keys linked to the generated page ID.

## Verification

From `application`:

```text
php -d xdebug.mode=off tests/dbworkers/parameters.php
php -d xdebug.mode=off tests/dbworkers/integration.php
php -d xdebug.mode=off tests/dbworkers/save_records_concurrency.php
php -d xdebug.mode=off tests/dbworkers/recordset_examples.php
```

The concurrency runner starts independent PHP processes with separate connections.
It checks concurrent service-row creation, INSERT followed by UPDATE for the same
key, different keys progressing while another transaction is open, equivalent
key representations, preserved explicit IDs, generated IDs above an explicit
high value, commit/rollback ownership, failed batches and recovery. Test tables
and service resources use unique names and are removed afterward.

The integration runner also loads `transaction_cases.php`. It tests native level
selection, fresh and repeatable snapshots, blocking reads, invalid/unsupported
level rejection, the unconditional READ COMMITTED default, session restoration
and transaction completion through a clone.
