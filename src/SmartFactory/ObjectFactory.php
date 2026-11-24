<?php
/**
 * Provides context-specific class bindings and object creation.
 *
 * @package Factory
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

/**
 * Binds implementations to interfaces or parent classes and creates initialized objects.
 *
 * Bindings can also map a class to itself, allowing a derived implementation to be
 * substituted later without changing business logic. Singleton instances are shared
 * per context and requested contract, rather than per implementation class.
 * Contracts are specified by class/interface names using ::class. Creation functions
 * declare a compatible, non-nullable class or interface as their native return type.
 * Each binding requires a named function or closure that accepts no arguments and
 * constructs, initializes and returns an implementation. The factory never invokes
 * constructors directly and validates each result before returning or caching it.
 *
 * @author Oleg Schildt
 */
class ObjectFactory
{
    /**
     * Stores successfully initialized singleton instances by context and contract.
     *
     * @var array<string, array<class-string, object>>
     *
     * @author Oleg Schildt
     */
    protected static array $singletons = [];

    /**
     * Stores declared return types and required creation functions by context and contract.
     *
     * @var array<string, array<class-string, array{return_type: class-string, factory_function: \ReflectionFunction}>>
     *
     * @author Oleg Schildt
     */
    protected static array $itable = [];

    /**
     * Tracks contracts currently being constructed or initialized in each context.
     *
     * @var array<string, array<class-string, true>>
     *
     * @author Oleg Schildt
     */
    protected static array $resolving = [];

    /**
     * Stores the active dependency chain, including contexts, contracts and declared return types.
     *
     * @var list<string>
     *
     * @author Oleg Schildt
     */
    protected static array $resolution_path = [];

    /**
     * Binds a factory function to an interface or class in the specified context.
     *
     * Contexts have independent bindings and singleton instances, with no fallback to
     * the default context. Specify the requested contract using ::class. Omitting
     * the context registers the default binding. The creation function's declared
     * return type is checked against the contract without invoking the function.
     * Existing bindings can be replaced before a singleton is created. Reset that
     * singleton explicitly before replacing its creation function.
     *
     * Examples:
     *
     *     ObjectFactory::bindFactory(ILanguageManager::class, function (): LanguageManager {
     *         $instance = new LanguageManager();
     *
     *         $instance->detectLanguage();
     *
     *         return $instance;
     *     });
     *
     *     ObjectFactory::bindFactory(IRecordsetManager::class, function (): RecordsetManager {
     *         $instance = new RecordsetManager();
     *
     *         $instance->setDBWorker(singleton(\SmartFactory\DatabaseWorkers\MySQL_DBWorker::class));
     *
     *         return $instance;
     *     }, "console");
     *
     * @template T of object
     *
     * @param class-string<T> $interface_or_class
     * The interface or class name of the requested contract, supplied using ::class.
     *
     * @param callable(): T $factory_function
     * A named function or closure called without arguments when an instance is requested.
     * It must declare one non-nullable class or interface return type compatible with
     * the contract and return a fully initialized implementation on each call.
     * Missing, built-in, union and intersection return types are rejected.
     * Method callbacks and invokable objects are not supported.
     *
     * @param string $context
     * The binding context; defaults to "default". Any string, including an empty string, is accepted.
     *
     * @return void
     * Registers the binding without creating an instance.
     *
     * @throws ObjectFactoryException
     * If the contract or declared return type is unknown, the return declaration is
     * missing, unsupported, nullable or incompatible with the contract, the creation
     * function is unsupported or requires arguments, or the contract has a cached
     * singleton or is currently being resolved.
     *
     * @throws \Throwable
     * If autoloading the contract or declared return type throws an exception or error, it is propagated.
     *
     * @author Oleg Schildt
     */
    public static function bindFactory(string $interface_or_class, callable $factory_function, string $context = "default"): void
    {
        $contract = self::reflectClass($interface_or_class, $context);
        $contract_name = $contract->getName();
        $description = sprintf("context '%s', contract '%s'", $context, $contract_name);

        self::assertMutable($context, $contract_name);

        if (isset(self::$singletons[$context][$contract_name])) {
            throw new ObjectFactoryException(sprintf("Cannot replace binding (%s): reset the singleton first.", $description));
        }

        if (!$factory_function instanceof \Closure && (!is_string($factory_function) || !function_exists($factory_function))) {
            throw new ObjectFactoryException(sprintf("Cannot register binding (%s): the creation function must be a named function or closure.", $description));
        }

        $creator = new \ReflectionFunction($factory_function);

        if ($creator->getNumberOfRequiredParameters() > 0) {
            throw new ObjectFactoryException(sprintf("Cannot register binding (%s): the creation function must be callable without arguments.", $description));
        }

        $return_class = self::reflectReturnClass($creator, $context, $contract_name);
        $return_class_name = $return_class->getName();

        if (!is_a($return_class_name, $contract_name, true)) {
            throw new ObjectFactoryException(sprintf(
                "Cannot register binding (%s, declared return type '%s'): the return type must implement or extend the contract.",
                $description,
                $return_class_name
            ));
        }

        self::$itable[$context][$contract_name] = ["return_type" => $return_class_name, "factory_function" => $creator];
    } // bindFactory

    /**
     * Creates and initializes an implementation of the requested contract.
     *
     * Calls the registered creation function without arguments and verifies that its
     * result implements or extends the requested contract.
     * A singleton is cached only after successful creation and result validation.
     * Failed requests can be retried. Recursive requests for the same contract in
     * the same context are rejected throughout the creation function's execution.
     * Singleton identity is scoped to the context and contract; different contracts
     * bound to the same implementation can have different singleton instances.
     *
     * @template T of object
     *
     * @param class-string<T> $interface_or_class
     * The interface or class name of the requested contract, supplied using ::class.
     *
     * @param bool $singleton
     * Whether to reuse a cached instance for this context and contract. If false, calls the creation function every time.
     *
     * @param string $context
     * The exact binding context, without fallback to the default context.
     *
     * @return T
     * Returns the fully initialized implementation of the contract.
     *
     * @throws ObjectFactoryException
     * If the contract name is missing or unknown, no binding exists in the context,
     * a circular dependency is detected, or the creation function returns a value
     * that does not implement or extend the requested contract.
     *
     * @throws \Throwable
     * If autoloading or the creation function throws an exception or error, it is
     * propagated unchanged and the failed instance is not cached.
     *
     * @used_by \SmartFactory\instance()
     * @used_by \SmartFactory\singleton()
     *
     * @author Oleg Schildt
     */
    public static function getInstance(string $interface_or_class, bool $singleton, string $context = "default"): object
    {
        $contract = self::reflectClass($interface_or_class, $context);
        $contract_name = $contract->getName();

        if (!isset(self::$itable[$context][$contract_name])) {
            throw new ObjectFactoryException(sprintf("No binding exists for contract '%s' in context '%s'.", $contract_name, $context));
        }

        $binding = self::$itable[$context][$contract_name];
        $description = sprintf("[context '%s', contract '%s', declared return type '%s']", $context, $contract_name, $binding["return_type"]);

        if (isset(self::$resolving[$context][$contract_name])) {
            throw new ObjectFactoryException("Circular factory dependency: " . implode(" -> ", [...self::$resolution_path, $description]));
        }

        if ($singleton && isset(self::$singletons[$context][$contract_name])) {
            return self::$singletons[$context][$contract_name];
        }

        self::$resolving[$context][$contract_name] = true;
        self::$resolution_path[] = $description;

        try {
            $instance = $binding["factory_function"]->invoke();

            if (!is_object($instance) || !$contract->isInstance($instance)) {
                throw new ObjectFactoryException(sprintf(
                    "Invalid creation result (%s): expected an instance of '%s', got '%s'.",
                    $description,
                    $contract_name,
                    get_debug_type($instance)
                ));
            }

            // Publish only validated results; finally also cleans up failed requests.
            if ($singleton) {
                self::$singletons[$context][$contract_name] = $instance;
            }

            return $instance;
        } finally {
            unset(self::$resolving[$context][$contract_name]);

            if (self::$resolving[$context] === []) {
                unset(self::$resolving[$context]);
            }

            array_pop(self::$resolution_path);
        }
    } // getInstance

    /**
     * Forgets one cached singleton while preserving its binding.
     *
     * Previously returned objects remain valid and are not disconnected or disposed.
     * A later singleton request creates and initializes a new object.
     *
     * @param class-string $interface_or_class
     * The interface or class name of the contract to reset, supplied using ::class.
     *
     * @param string $context
     * The context whose cached instance should be forgotten.
     *
     * @return void
     * Removes the cached instance, or does nothing if none exists.
     *
     * @throws ObjectFactoryException
     * If the contract name is missing or unknown, or the contract is currently being resolved.
     *
     * @throws \Throwable
     * If autoloading the contract throws an exception or error, it is propagated.
     *
     * @author Oleg Schildt
     */
    public static function resetInstance(string $interface_or_class, string $context = "default"): void
    {
        $contract = self::reflectClass($interface_or_class, $context);
        $contract_name = $contract->getName();

        self::assertMutable($context, $contract_name);

        unset(self::$singletons[$context][$contract_name]);

        if (isset(self::$singletons[$context]) && self::$singletons[$context] === []) {
            unset(self::$singletons[$context]);
        }
    } // resetInstance

    /**
     * Forgets cached singletons and optionally bindings in one context.
     *
     * Previously returned objects remain valid and are not disconnected or disposed.
     *
     * @param string $context
     * The context to reset; an unknown context is ignored.
     *
     * @param bool $remove_bindings
     * Whether to remove the context's bindings as well as its cached instances.
     *
     * @return void
     * Clears the selected context while preserving other contexts.
     *
     * @throws ObjectFactoryException
     * If any contract in the context is currently being constructed or initialized.
     *
     * @author Oleg Schildt
     */
    public static function resetContext(string $context = "default", bool $remove_bindings = false): void
    {
        self::assertMutable($context);

        unset(self::$singletons[$context]);

        if ($remove_bindings) {
            unset(self::$itable[$context]);
        }
    } // resetContext

    /**
     * Forgets all cached singletons and optionally all bindings.
     *
     * Previously returned objects remain valid and are not disconnected or disposed.
     *
     * @param bool $remove_bindings
     * Whether to remove every binding as well as all cached instances.
     *
     * @return void
     * Clears factory state according to the requested scope.
     *
     * @throws ObjectFactoryException
     * If any contract is currently being constructed or initialized.
     *
     * @author Oleg Schildt
     */
    public static function resetAll(bool $remove_bindings = false): void
    {
        self::assertMutable();

        self::$singletons = [];

        if ($remove_bindings) {
            self::$itable = [];
        }
    } // resetAll

    /**
     * Resolves a class/interface name to its canonical reflection.
     *
     * @param class-string $class
     * An existing class/interface name, including aliases and alternate casing.
     *
     * @param string $context
     * The operation's context, included in validation errors.
     *
     * @return \ReflectionClass<object>
     * Returns reflection with the canonical class or interface name.
     *
     * @throws ObjectFactoryException
     * If the name is empty or does not identify an existing class or interface.
     *
     * @throws \Throwable
     * If autoloading the class or interface throws an exception or error, it is propagated.
     *
     * @author Oleg Schildt
     */
    protected static function reflectClass(string $class, string $context): \ReflectionClass
    {
        if ($class === "" || (!interface_exists($class) && !class_exists($class))) {
            throw new ObjectFactoryException(sprintf("The class or interface '%s' does not exist in context '%s'.", $class, $context));
        }

        return new \ReflectionClass($class);
    } // reflectClass

    /**
     * Resolves the creation function's native return declaration to a class or interface.
     *
     * Only one non-nullable named object type is accepted. Contextual names self,
     * parent and static are resolved using the closure's lexical or called class.
     * The creation function is not invoked.
     *
     * @param \ReflectionFunction $creator
     * The registered named function or closure to inspect.
     *
     * @param string $context
     * The binding context, included in validation errors.
     *
     * @param class-string $contract_name
     * The canonical requested contract name, included in validation errors.
     *
     * @return \ReflectionClass<object>
     * Returns reflection for the existing class or interface declared as the return type.
     *
     * @throws ObjectFactoryException
     * If the return declaration is missing, built-in, nullable, a union or intersection,
     * names an unknown type, or contains a contextual name without a matching class scope.
     *
     * @throws \Throwable
     * If autoloading the declared return type throws an exception or error, it is propagated.
     *
     * @author Oleg Schildt
     */
    protected static function reflectReturnClass(\ReflectionFunction $creator, string $context, string $contract_name): \ReflectionClass
    {
        $return_type = $creator->getReturnType();
        $description = sprintf("context '%s', contract '%s'", $context, $contract_name);

        if (!$return_type instanceof \ReflectionNamedType || $return_type->isBuiltin() || $return_type->allowsNull()) {
            throw new ObjectFactoryException(sprintf(
                "Cannot register binding (%s): the creation function must declare one non-nullable class or interface return type; got '%s'.",
                $description,
                $return_type === null ? "no return type" : (string) $return_type
            ));
        }

        $return_name = $return_type->getName();

        // Reflection preserves contextual names instead of expanding them to class names.
        if (in_array($return_name, ["self", "parent", "static"], true)) {
            $scope = $creator->getClosureScopeClass();
            $return_class = match ($return_name) {
                "self" => $scope,
                "parent" => $scope === null ? false : $scope->getParentClass(),
                "static" => $creator->getClosureCalledClass()
            };

            if (!$return_class instanceof \ReflectionClass) {
                throw new ObjectFactoryException(sprintf(
                    "Cannot register binding (%s): cannot resolve declared return type '%s' without a matching class scope.",
                    $description,
                    $return_name
                ));
            }

            return $return_class;
        }

        if (!interface_exists($return_name) && !class_exists($return_name)) {
            throw new ObjectFactoryException(sprintf(
                "Cannot register binding (%s): declared return type '%s' does not exist.",
                $description,
                $return_name
            ));
        }

        return new \ReflectionClass($return_name);
    } // reflectReturnClass

    /**
     * Prevents binding and cache mutations that affect an active resolution.
     *
     * @param ?string $context
     * The context to check, or null to check all contexts.
     *
     * @param ?string $contract_name
     * The canonical contract name, or null to check the entire selected context.
     *
     * @return void
     * Returns normally when no affected contract is being resolved.
     *
     * @throws ObjectFactoryException
     * If the requested scope includes a contract currently being constructed or initialized.
     *
     * @author Oleg Schildt
     */
    protected static function assertMutable(?string $context = null, ?string $contract_name = null): void
    {
        $active = $context === null ? self::$resolving !== [] : isset(self::$resolving[$context]);

        if ($context !== null && $contract_name !== null) {
            $active = isset(self::$resolving[$context][$contract_name]);
        }

        if ($active) {
            throw new ObjectFactoryException("Cannot change factory state during an affected resolution: " . implode(" -> ", self::$resolution_path));
        }
    } // assertMutable
} // ObjectFactory
