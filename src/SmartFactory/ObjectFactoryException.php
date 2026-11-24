<?php
/**
 * Defines the exception used for factory configuration and resolution failures.
 *
 * @package Factory
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

/**
 * Reports invalid bindings, creation results, missing registrations, circular dependencies and unsafe resets.
 *
 * Exceptions and errors raised by creation functions or autoloaders
 * are propagated unchanged instead of being converted to this exception type.
 *
 * @author Oleg Schildt
 */
class ObjectFactoryException extends \RuntimeException
{
} // ObjectFactoryException
