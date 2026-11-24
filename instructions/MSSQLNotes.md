# SQL Server results without MARS

`MSSQL_DBWorker` explicitly connects with `MultipleActiveResultSets => false`.
Clones continue to share one native connection, session and transaction. Creating
a clone does not open a second connection.

## Forward-only results

All statements use `SQLSRV_CURSOR_FORWARD`. An unread result keeps the shared
connection busy; the former `scrollable` option no longer selects static cursors.
Before another worker issues a request or starts a transaction, either read the
result until `fetch_row()` returns false or call `free_result()` to cancel it.
Otherwise the native driver reports a pending-results error as DBWorkerException.
The failed competing request does not cancel the original worker's result.

A prepared
statement remains reusable after EOF or `free_result()`. Explicit cancellation
discards pending results; reading to EOF completes trailing batch commands and
reports deferred SQL errors.

Stored procedure results and explicit OUTPUT clauses also use forward-only
results. The wrapper exposes the first public row result and skips preceding row-count results.
It does not provide a public interface for multiple row result sets; later row
sets are discarded when the batch is completed.

## Internal and discarded results

Result-free batches and internal SCOPE_IDENTITY results are consumed completely
before returning. This releases the connection and prevents unread protocol
results from blocking a clone's metadata queries or transaction commands.

`execute_procedure()` intentionally exposes no result rows or output parameters.
SQL Server consumes those results before freeing the procedure statement, so
commands following an ignored SELECT execute and deferred errors are reported.
To read a procedure result, use an ordinary or prepared EXEC query and fetch its
first row result.

On execution or fetch failure, the driver cancels its own pending results. It
keeps other workers' results intact. Explicit-ID batches still restore
IDENTITY_INSERT and stream parameters retain their existing ownership rules.

## Verification

The integration runner includes `mssql_mars_cases.php` for the MSSQL driver.
It checks that a pending forward-only result really blocks a clone, which also
detects accidentally enabling MARS or replacing streaming with silent buffering.
The normal integration checks additionally cover transactions, shared clones,
bound parameters, large binary/Unicode fields and concurrent record saving.
