<?php
/**
 * This file contains the implementation of the class DBConnectionState.
 *
 * @package Database
 *
 * @author Oleg Schildt
 */
namespace SmartFactory\DatabaseWorkers;

/**
 * Stores native connection ownership and transaction state shared by cloned workers.
 *
 * The native connection is closed when the last reference to this object is released.
 * Statements and result sets remain owned by their individual workers.
 *
 * @author Oleg Schildt
 */
final class DBConnectionState
{
    /**
     * Indicates whether workers sharing the connection have an active managed transaction.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    public bool $transaction = false;

    /**
     * Stores connection-wide schema settings, native mode flags and identity/deallocation caches.
     * Active transaction isolation and any session isolation to restore are shared by all clones.
     *
     * @var array
     *
     * @author Oleg Schildt
     */
    public array $attributes = [];

    /**
     * Creates the shared owner of a native database connection.
     *
     * @param mixed $handle
     * The open native connection object or resource.
     *
     * @param \Closure $closer
     * The driver callback that receives and closes the native handle when its last owner is released.
     *
     * @author Oleg Schildt
     */
    public function __construct(
        /**
         * Stores the native database connection handle until its last owner releases it.
         *
         * @var mixed
         *
         * @author Oleg Schildt
         */
        public mixed $handle,

        /**
         * Stores the driver callback that closes the native connection handle.
         *
         * @var \Closure
         *
         * @author Oleg Schildt
         */
        private readonly \Closure $closer
    ) {
        // Promoted properties retain the native handle and close callback; no further initialization is needed.
    } // __construct

    /**
     * Closes the native connection when its last shared owner is released.
     *
     * @return void
     *
     * @author Oleg Schildt
     */
    public function __destruct()
    {
        // The final shared owner has been released; close the native handle once.
        if ($this->handle !== null) {
            ($this->closer)($this->handle);
            $this->handle = null;
        }
    } // __destruct
} // class DBConnectionState

