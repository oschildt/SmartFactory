<?php
/**
 * This file contains the definition of the factory functions.
 *
 * @package Factory
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

/**
 * Returns a shared, fully initialized implementation of the requested contract.
 *
 * Useful for services such as SessionManager, EventManager and DBWorker. Instances
 * are shared per context and contract until explicitly reset through ObjectFactory.
 * Different contracts bound to the same implementation can have different instances.
 *
 * @template T of object
 *
 * @param class-string<T> $interface_or_class
 * The interface or class name of the requested contract, supplied using ::class.
 *
 * @param string $context
 * The exact binding context, without fallback to the default context.
 *
 * @return T
 * Returns the cached instance or a newly created and initialized implementation.
 *
 * @throws ObjectFactoryException
 * If the contract name is missing or unknown, no binding exists in the context,
 * a circular dependency is detected, or the creation function returns a value
 * that does not implement or extend the requested contract.
 *
 * @throws \Throwable
 * If autoloading or the creation function throws an exception or error,
 * it is propagated unchanged and the failed instance is not cached.
 *
 * @see \SmartFactory\instance()
 *
 * @author Oleg Schildt
 */
function singleton(string $interface_or_class, string $context = "default"): object
{
    return ObjectFactory::getInstance($interface_or_class, true, $context);
} // singleton

/**
 * Creates a new, fully initialized implementation of the requested contract.
 *
 * Every request creates a separate object, even if a singleton already exists for
 * the same contract. This is suitable for independent data objects such as users.
 *
 * @template T of object
 *
 * @param class-string<T> $interface_or_class
 * The interface or class name of the requested contract, supplied using ::class.
 *
 * @param string $context
 * The exact binding context, without fallback to the default context.
 *
 * @return T
 * Returns a new, fully initialized implementation of the contract.
 *
 * @throws ObjectFactoryException
 * If the contract name is missing or unknown, no binding exists in the context,
 * a circular dependency is detected, or the creation function returns a value
 * that does not implement or extend the requested contract.
 *
 * @throws \Throwable
 * If autoloading or the creation function throws an exception or error,
 * it is propagated unchanged.
 *
 * @see \SmartFactory\singleton()
 *
 * @author Oleg Schildt
 */
function instance(string $interface_or_class, string $context = "default"): object
{
    return ObjectFactory::getInstance($interface_or_class, false, $context);
} // instance

/**
 * Creates or retrieves a DBWorker object for working with the database.
 *
 * Supported databases: MySQL, MSSQL, PostgreSQL and Oracle.
 * An already connected singleton is returned without reinitializing its settings.
 * Singletons are shared per driver class in the default factory context, not per
 * connection configuration. Use singleton=false or shards for independent connections.
 * A worker that fails extension validation, initialization or connection is evicted
 * from the singleton cache so a later request can create a clean worker.
 *
 * @param array{
 *     db_type: non-empty-string,
 *     db_server?: scalar|\Stringable|null,
 *     db_port?: scalar|\Stringable|null,
 *     connection_string?: scalar|\Stringable|null,
 *     db_name?: scalar|\Stringable|null,
 *     db_user?: scalar|\Stringable|null,
 *     db_password?: scalar|\Stringable|null,
 *     db_schema?: scalar|\Stringable|null,
 *     default_isolation_level?: string,
 *     isolation_level?: scalar|\Stringable|null,
 *     autoconnect?: mixed,
 *     read_only?: mixed,
 *     connect_timeout?: scalar|null,
 *     read_timeout?: scalar|null,
 *     query_timeout?: scalar|null,
 *     encrypt?: mixed,
 *     trust_server_certificate?: mixed,
 *     save_records_explicit_identity?: mixed,
 *     auto_returning?: mixed,
 *     query_logger?: (callable(array{sql: string, duration_ms: int|float, parameter_types: array<array-key, string>, failed: bool}): mixed)|null,
 *     ...<string, mixed>
 * } $parameters
 * Connection settings and driver options. Only db_type is always required; the
 * remaining required values depend on the driver and whether a connection is opened.
 * String-valued settings accept scalars, null and Stringable objects because the
 * drivers cast them to strings. Boolean options accept mixed values using PHP
 * truthiness; actual booleans are recommended. auto_returning is disabled only by false.
 * Additional string keys are passed through to the driver for custom options.
 *
 * - $parameters["db_type"] - nonempty driver name (MySQL, MSSQL, PostgreSQL or Oracle).
 *   A custom name must resolve to a registered DatabaseWorkers\\<name>_DBWorker subclass.
 *
 * - $parameters["db_server"] - server address
 *
 * - $parameters["db_port"] - optional server port, usually an integer or digit string
 *   between 1 and 65535; an empty string or null clears an explicitly configured port.
 *
 * - $parameters["connection_string"] - optional complete Oracle Easy Connect/TNS descriptor
 *
 * - $parameters["db_name"] - database name
 *
 * - $parameters["db_user"] - user name
 *
 * - $parameters["db_password"] - user password
 *
 * - $parameters["default_isolation_level"] - isolation used by start_transaction() when its argument
 *   is omitted or NULL; defaults to DBWorker::ISOLATION_READ_COMMITTED.
 *
 * - $parameters["autoconnect"] - whether to establish the connection automatically upon creation.
 *
 * - $parameters["read_only"] - this parameter sets the connection to the read only mode.
 *
 * - $parameters["db_schema"] - optional schema name, interpreted by the selected driver.
 *
 * - $parameters["isolation_level"] - optional connection-level isolation, with supported
 *   values depending on the driver; distinct from default_isolation_level for transactions.
 *
 * - $parameters["connect_timeout"], $parameters["read_timeout"], $parameters["query_timeout"] -
 *   timeouts in seconds, where supported by the driver. Scalar values and null are
 *   cast to integers; integer values or digit strings are recommended.
 *
 * - $parameters["encrypt"], $parameters["trust_server_certificate"] - MSSQL TLS options.
 *
 * - $parameters["save_records_explicit_identity"] - enables explicit identity handling
 *   in MySQL and MSSQL save_records() operations.
 *
 * - $parameters["auto_returning"] - enables Oracle automatic identity retrieval; defaults to true.
 *
 * - $parameters["query_logger"] - optional callback receiving the SQL template,
 *   duration in milliseconds, parameter type names and failure status. Its return value is ignored.
 *
 * Example:
 * ```php
 * use SmartFactory\DatabaseWorkers\DBWorker;
 *
 * $dbw = dbworker(["db_type" => "MySQL",
 *                  "db_server" => "localhost",
 *                  "db_name" => "framework_demo",
 *                  "db_user" => "root",
 *                  "db_password" => "root",
 *                  "default_isolation_level" => DBWorker::ISOLATION_READ_COMMITTED,
 *                  "autoconnect" => true
 *                 ]);
 * ```
 *
 * @param bool $singleton
 * If the parameter is true, it ensures that only one instance of this object exists.
 *
 * @return DatabaseWorkers\DBWorker
 * Returns the initialized database worker.
 *
 * @throws \InvalidArgumentException
 * If the database type is absent, empty or not a string.
 *
 * @throws ObjectFactoryException
 * If the driver class is unknown, its factory binding is missing, or resolving it fails validation.
 *
 * @throws \UnexpectedValueException
 * If the resolved driver does not extend DatabaseWorkers\DBWorker.
 *
 * @throws \Exception
 * If the native database extension is unavailable.
 *
 * @throws DatabaseWorkers\DBWorkerException
 * If connection settings or the default isolation level are invalid, or connecting to the database fails.
 *
 * @throws \Throwable
 * If autoloading, a custom creation function, or a custom worker method throws an exception or error.
 *
 * @author Oleg Schildt
 */
function dbworker(array $parameters = [], bool $singleton = true): DatabaseWorkers\DBWorker
{
    if (!isset($parameters["db_type"]) || !is_string($parameters["db_type"]) || $parameters["db_type"] === "") {
        throw new \InvalidArgumentException("Database type must be a nonempty string.");
    }

    $class_name = __NAMESPACE__ . "\\DatabaseWorkers\\" . $parameters["db_type"] . "_DBWorker";
    $dbworker = ObjectFactory::getInstance($class_name, $singleton);

    try {
        if (!$dbworker instanceof DatabaseWorkers\DBWorker) {
            throw new \UnexpectedValueException("The database driver must extend " . DatabaseWorkers\DBWorker::class . ".");
        }

        if (!$dbworker->is_extension_installed()) {
            throw new \Exception(sprintf("PHP extension '%s' is not installed. Work with the database '%s' is not possible!", $dbworker->get_extension_name(), $dbworker->get_rdbms_name()));
        }

        // Preserve the settings and session state of an already connected singleton.
        if ($dbworker->is_connected()) {
            return $dbworker;
        }

        $dbworker->init($parameters);

        if (!empty($parameters["autoconnect"])) {
            $dbworker->connect();
        }
    } catch (\Throwable $exception) {
        // Factory creation has finished, so its public reset API can discard partial state.
        if ($singleton) {
            ObjectFactory::resetInstance($class_name);
        }

        throw $exception;
    }

    return $dbworker;
} // dbworker

