<?php
/**
 * This file contains the implementation of the class ObjectFactory
 * for creation of the objects.
 *
 * @package Factory
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

/**
 * Class for creation of the objects.
 *
 * The class ObjectFactory is an auxiliary class that empowers the factory methods. It provides the methods
 * for binding of class implementations to the interfaces and for creation of objects for the requested interface.
 *
 * @author Oleg Schildt
 */
class ObjectFactory
{
    /**
     * Internal array for storing the singleton instances for re-using.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    static protected $singletons = [];
    
    /**
     * Internal array for storing the mapping betwen interfaces/classes and the bound classes.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    static protected $itable = [];
    
    /**
     * Binds a class to an interface or a parent class.
     *
     * The key point of this approach is the definition common interfaces
     * and implementation of them in classes. When an object that supoorts an
     * interface is requested, an instance of the corresponding bound class
     * is created. When you want to change the class, you need just bind
     * the new class to the interface.
     *
     * You can also bind a class to itself and reqest it by own name in factory
     * method. And later, you can implement a derived class and change the binding
     * without affecting the code in the business logic.
     *
     * @param string|object $interface_or_class
     * Name of the class/interface as string or the class/interface.
     *
     * @param string|object $class
     * The class which instance should be created if the object is requested.
     *
     * @param callable $init_function
     * The optional initialization function. You can provide it to do some
     * custom intialization. The signature of
     * this function is:
     *
     * ```php
     * function (object $instance) : void;
     * ```
     *
     * - $instance - the created instance.
     *
     * Example:
     *
     * ```php
     * ObjectFactory::bindClass(ILanguageManager::class, LanguageManager::class, function($instance) {
     *   $instance->detectLanguage();
     * });
     *
     * ObjectFactory::bindClass(IRecordsetManager::class, RecordsetManager::class, function($instance) {
     *   $instance->setDBWorker(dbworker());
     * });
     * ```
     *
     * @return void
     *
     * @throws \Exception
     * It might throw the following exceptions in the case of any errors:
     *
     * - if the interface or bound class is not specified.
     * - if the interface or class does not exist.
     * - if the bound class is empty.
     * - if the bound class does not implement the corresponding interface.
     * - if the bound class is not instantiable.
     * - if the check of the classes and interfaces fails.
     *
     * @author Oleg Schildt
     */
    static public function bindClass($interface_or_class, $class, $init_function = null)
    {
        if (empty($class)) {
            throw new \Exception("Bound class is empty!");
        }
        
        if (empty($interface_or_class)) {
            throw new \Exception("Bound interface or class is empty!");
        }
        
        if (!interface_exists($interface_or_class) && !class_exists($interface_or_class)) {
            throw new \Exception(sprintf("The interface or class '%s' does not exist!", $interface_or_class));
        }
        
        if (!class_exists($class)) {
            throw new \Exception(sprintf("The class '%s' does not exist!", $class));
        }
        
        $ic = new \ReflectionClass($interface_or_class);
        $c = new \ReflectionClass($class);
        
        if (!$c->isInstantiable()) {
            throw new \Exception(sprintf("The class '%s' is not instantiable!", $c->getName()));
        }
        
        if ($c != $ic) {
            if (!$c->isSubclassOf($ic)) {
                throw new \Exception(sprintf("The class '%s' does not implement the interface '%s'!", $c->getName(), $ic->getName()));
            }
        }
        
        $f = null;
        if ($init_function !== null) {
            if (!is_callable($init_function)) {
                throw new \Exception(sprintf("'%s' is not a function!", $init_function));
            }
            
            $f = new \ReflectionFunction($init_function);
        }
        
        self::$itable[$ic->getName()] = ["class" => $c, "init_function" => $f];
    } // bindClass
    
    /**
     * Сreates an object that support the interface $interface_or_class.
     *
     * @param string|object $interface_or_class
     * Name of the class/interface which instance should be created.
     *
     * @param boolean $singleton
     * If the parameter is true, it ensures that only one instance of this object exists.
     * The singleton is a usual patter for the action objects like SessionManager, EventManager,
     * DBWorker etc. It makes no sense to produce many instances of such classes,
     * it wastes the computer resources and might cause errors.
     *
     * If the parameter is false, then, by each request, a new object is created. If you request
     * data objects like User, a separate instance must be created for each item.
     *
     * @return object
     * Returns object of the class bound to the interface.
     *
     * @throws \Exception
     * It might throw the following exceptions in the case of any errors:
     *
     * - if the interface or class is not specified.
     * - if the interface or class does not exist.
     * - if the check of the classes and interfaces fails.
     *
     * @used_by \SmartFactory\instance()
     * @used_by \SmartFactory\singleton()
     *
     * @author Oleg Schildt
     */
    static public function getInstance($interface_or_class, $singleton)
    {
        if (empty($interface_or_class)) {
            throw new \Exception("Class or interface is not specified!");
        }
        
        if (!interface_exists($interface_or_class) && !class_exists($interface_or_class)) {
            throw new \Exception(sprintf("The interface or class '%s' does not exist!", $interface_or_class));
        }
        
        $class = new \ReflectionClass($interface_or_class);
        
        $class_name = $class->getName();
        
        if (empty(self::$itable[$class_name])) {
            throw new \Exception(sprintf("The interface or class '%s' has no bound class!", $class_name));
        }
        
        // if not singleton, we create a new instance every time it is requested
        if (!$singleton) {
            $instance = self::$itable[$class_name]["class"]->newInstance();
            
            if (!empty(self::$itable[$class_name]["init_function"])) {
                self::$itable[$class_name]["init_function"]->invoke($instance);
            }
            
            return $instance;
        }
        
        // if singleton, we create an instance only if it does not exist yet
        if (empty(self::$singletons[$class_name])) {
            self::$singletons[$class_name] = self::$itable[$class_name]["class"]->newInstance();
            
            if (!empty(self::$itable[$class_name]["init_function"])) {
                self::$itable[$class_name]["init_function"]->invoke(self::$singletons[$class_name]);
            }
        }
        
        return self::$singletons[$class_name];
    } // getInstance
} // ObjectFactory
