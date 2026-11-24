<?php
/**
 * This file contains the implementation of the class BlobValue.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */
namespace SmartFactory\DatabaseWorkers;

/**
 * Explicit binary bind value for a BLOB or bytea parameter.
 *
 * Accepts a byte string or an open caller-owned stream. The wrapper keeps the original
 * value and does not read, close or take ownership of the stream.
 *
 * @author Oleg Schildt
 */
final class BlobValue
{
    /**
     * Creates an explicit binary bind value.
     *
     * @param string|resource $value
     * The binary string or open readable stream to bind.
     * The caller retains ownership of any stream.
     *
     * @throws DBWorkerException
     * It throws an exception if the value is neither a string nor an open stream.
     *
     * @author Oleg Schildt
     */
    public function __construct(
        /**
         * Stores the original caller-supplied LOB value without taking ownership of its stream.
         *
         * @var string|resource
         *
         * @author Oleg Schildt
         */
        public readonly mixed $value
    )
    {
        // Keep the binary source untouched; validation must not consume or close a caller-owned stream.
        if (!is_string($value) && (!is_resource($value) || get_resource_type($value) !== 'stream')) {
            throw new DBWorkerException('A BLOB value must be a string or an open stream.', DBWorker::ERR_WRONG_DATA_FORMAT);
        }
    } // __construct
} // class BlobValue
