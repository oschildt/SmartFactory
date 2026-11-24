<?php
/**
 * Implements JSON file storage for application configuration.
 *
 * @package System
 *
 * @author Oleg Schildt
 */

namespace SmartFactory;

/**
 * Manages configuration in a local JSON file, optionally encrypted and cached in APCu.
 *
 * Parameter names require no preliminary definitions. Saves merge explicitly assigned
 * parameters into the latest contents under an exclusive lock, preserving concurrent
 * edits to other parameters. Assignments to the same parameter use the last save.
 * Contextual validation supports separate forms; drafts require application-managed storage.
 *
 * @see ApplicationSettingsManager
 * @see UserSettingsManager
 *
 * @author Oleg Schildt
 */
class ConfigSettingsManager extends AbstractSettingsManager
{
    /**
     * The canonical absolute configuration file path.
     *
     * @var string|null
     *
     * @author Oleg Schildt
     */
    protected ?string $save_path = null;

    /**
     * Whether the JSON payload is encrypted before writing.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $save_encrypted = false;

    /**
     * The password key passed to the existing AES encryption helpers.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $salt_key = "default";

    /**
     * Whether a missing configuration file is an error when reading.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $config_file_must_exist = false;

    /**
     * Whether decoded snapshots are cached and verified against file contents.
     *
     * @var bool
     *
     * @author Oleg Schildt
     */
    protected bool $use_apcu = false;

    /**
     * Configures the JSON file, optional encryption and decoded-value caching.
     *
     * @param array{save_path: string, save_encrypted?: bool, salt_key?: string, config_file_must_exist?: bool, use_apcu?: bool} $parameters
     * The local file path and storage options. The parent directory must exist.
     * Requested APCu caching must be enabled for the current PHP execution mode.
     * Reinitializing discards the previous snapshot and unsaved edits.
     *
     * @return void
     * Validates storage options without loading the file.
     *
     * @throws \InvalidArgumentException
     * If the path or parent directory is invalid, or requested APCu caching is unavailable.
     *
     * @author Oleg Schildt
     */
    public function init(array $parameters): void
    {
        $path = $parameters["save_path"] ?? null;

        if (!is_string($path) || $path === "" || !is_dir(dirname($path))) {
            throw new \InvalidArgumentException("A local 'save_path' with an existing parent directory is required.");
        }

        $use_apcu = (bool) ($parameters["use_apcu"] ?? false);

        if ($use_apcu && (!function_exists('apcu_enabled') || !apcu_enabled())) {
            throw new \InvalidArgumentException("APCu is not enabled for this PHP execution mode.");
        }

        $this->save_path = realpath($path) ?: realpath(dirname($path)) . DIRECTORY_SEPARATOR . basename($path);
        $this->save_encrypted = (bool) ($parameters["save_encrypted"] ?? false);
        $this->salt_key = (string) ($parameters["salt_key"] ?? "default");
        $this->config_file_must_exist = (bool) ($parameters["config_file_must_exist"] ?? false);
        $this->use_apcu = $use_apcu;

        $this->resetSettings();
    } // init

    /**
     * Checks that a configuration file has been selected.
     *
     * @return void
     * Leaves storage and cached values unchanged.
     *
     * @throws \LogicException
     * If init() has not supplied a configuration file path.
     *
     * @author Oleg Schildt
     */
    protected function validateParameters(): void
    {
        if ($this->save_path === null) {
            throw new \LogicException("The 'save_path' is not specified.");
        }
    } // validateParameters

    /**
     * Acquires the sidecar lock shared by configuration readers and writers.
     *
     * @param int $operation
     * LOCK_SH for reading or LOCK_EX for merging and replacing the file.
     *
     * @return resource
     * Returns an open locked stream which the caller must close.
     *
     * @throws \RuntimeException
     * If the sidecar file cannot be opened or the requested lock cannot be acquired.
     *
     * @throws \LogicException
     * If the manager has not been initialized.
     *
     * @author Oleg Schildt
     */
    protected function openLock(int $operation)
    {
        $this->validateParameters();

        $lock = @fopen($this->save_path . ".lock", "c");

        if ($lock === false) {
            throw new \RuntimeException("Cannot open the configuration lock file: " . $this->save_path);
        }

        if (!flock($lock, $operation)) {
            fclose($lock);

            throw new \RuntimeException("Cannot lock the configuration file: " . $this->save_path);
        }

        return $lock;
    } // openLock

    /**
     * Derives a cache key specific to the canonical path and decoding options.
     *
     * @return string
     * Returns a key containing neither the path nor the password in plain text.
     *
     * @author Oleg Schildt
     */
    protected function cacheKey(): string
    {
        return "config_settings:" . hash('sha256', $this->save_path . "\0"
            . (int) $this->save_encrypted . "\0" . $this->salt_key);
    } // cacheKey

    /**
     * Reads and decodes the file while the caller holds the sidecar lock.
     *
     * @param bool $use_cache
     * Whether a decoded snapshot may be reused after matching the exact payload digest.
     * Saves bypass the cache to merge against the persisted source.
     *
     * @return array<string, mixed>
     * Returns decoded values, or an empty array for an optional missing file.
     *
     * @throws \RuntimeException
     * If a required file is missing or the payload cannot be read.
     *
     * @throws \UnexpectedValueException
     * If the decoded JSON is not an object or array.
     *
     * @throws \Throwable
     * If decryption fails or the payload is malformed JSON.
     *
     * @author Oleg Schildt
     */
    protected function readFile(bool $use_cache): array
    {
        clearstatcache(true, $this->save_path);

        if (!file_exists($this->save_path) && !$this->config_file_must_exist) {
            return [];
        }

        $stream = @fopen($this->save_path, "rb");

        if ($stream === false) {
            throw new \RuntimeException("Cannot read the configuration file: " . $this->save_path);
        }

        try {
            $payload = stream_get_contents($stream);
        } finally {
            fclose($stream);
        }

        if ($payload === false) {
            throw new \RuntimeException("Reading the configuration file failed: " . $this->save_path);
        }

        $digest = hash('sha256', $payload);

        if ($use_cache && $this->use_apcu) {
            $cached = apcu_fetch($this->cacheKey(), $success);

            if ($success && is_array($cached) && ($cached['digest'] ?? null) === $digest) {
                return $cached['settings'];
            }
        }

        $json = $this->save_encrypted ? aes_256_decrypt($payload, $this->salt_key) : $payload;
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \UnexpectedValueException("Configuration JSON must contain an object or array.");
        }

        if ($use_cache && $this->use_apcu) {
            apcu_store($this->cacheKey(), ['digest' => $digest, 'settings' => $data]);
        }

        return $data;
    } // readFile

    /**
     * Loads JSON under a shared lock without mutating the current settings snapshot.
     *
     * @param array<string, mixed> &$data
     * Receives the complete decoded snapshot after successful reading.
     *
     * @return void
     * Releases the lock even when reading, decrypting or decoding fails.
     *
     * @throws \Throwable
     * If initialization, locking, reading, decryption or JSON decoding fails.
     *
     * @see ConfigSettingsManager::saveJSON()
     *
     * @author Oleg Schildt
     */
    protected function loadJSON(array &$data): void
    {
        $lock = $this->openLock(LOCK_SH);

        try {
            $data = $this->readFile(true);
        } finally {
            fclose($lock);
        }
    } // loadJSON

    /**
     * Merges edited parameters under an exclusive lock and atomically replaces the file.
     *
     * @param array<string, mixed> &$data
     * The local snapshot, replaced with the merged snapshot after a successful write.
     *
     * @return void
     * Preserves concurrent edits to other parameters and invalidates the decoded cache.
     *
     * @throws \RuntimeException
     * If the file or directory is not writable, creating, writing or flushing the temporary
     * file fails, permissions cannot be preserved, or replacing the target fails.
     *
     * @throws \Throwable
     * If initialization, locking, reading, JSON encoding or encryption fails.
     *
     * @see ConfigSettingsManager::loadJSON()
     *
     * @author Oleg Schildt
     */
    protected function saveJSON(array &$data): void
    {
        $lock = $this->openLock(LOCK_EX);
        $temporary = null;
        $stream = null;

        try {
            $merged = $this->readFile(false);

            foreach ($this->changed as $name => $_) {
                $merged[$name] = $data[$name];
            }

            $json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            $payload = $this->save_encrypted ? aes_256_encrypt($json, $this->salt_key) : $json;

            if (!is_writable(dirname($this->save_path))
                || (file_exists($this->save_path) && !is_writable($this->save_path))) {
                throw new \RuntimeException("The configuration file or its directory is not writable: " . $this->save_path);
            }

            $temporary = @tempnam(dirname($this->save_path), ".settings-");

            if ($temporary === false) {
                throw new \RuntimeException("Cannot create a temporary configuration file.");
            }

            $stream = @fopen($temporary, "wb");

            if ($stream === false) {
                throw new \RuntimeException("Cannot open the temporary configuration file.");
            }

            $length = strlen($payload);
            $offset = 0;

            while ($offset < $length) {
                $written = fwrite($stream, substr($payload, $offset));

                if ($written === false || $written === 0) {
                    throw new \RuntimeException("Writing the temporary configuration file failed.");
                }

                $offset += $written;
            }

            if (!fflush($stream)) {
                throw new \RuntimeException("Flushing the temporary configuration file failed.");
            }

            fclose($stream);

            $stream = null;

            if (file_exists($this->save_path) && !chmod($temporary, fileperms($this->save_path) & 0777)) {
                throw new \RuntimeException("Cannot preserve configuration file permissions.");
            }

            if (!@rename($temporary, $this->save_path)) {
                throw new \RuntimeException("Cannot replace the configuration file: " . $this->save_path);
            }

            $temporary = null;
            $data = $merged;

            if ($this->use_apcu) {
                apcu_delete($this->cacheKey());
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (is_string($temporary) && file_exists($temporary)) {
                unlink($temporary);
            }

            fclose($lock);
        }
    } // saveJSON

    /**
     * Implements the common loading contract using the JSON reader.
     *
     * @param array<string, mixed> &$data
     * Receives the complete settings snapshot.
     *
     * @return void
     * Replaces the target only after successful loading.
     *
     * @throws \Throwable
     * If configuration cannot be locked, read, decrypted or decoded.
     *
     * @author Oleg Schildt
     */
    protected function loadSettingsData(array &$data): void
    {
        $this->loadJSON($data);
    } // loadSettingsData

    /**
     * Implements the common saving contract using a locked JSON merge.
     *
     * @param array<string, mixed> &$data
     * Receives the merged snapshot after saving succeeds.
     *
     * @return void
     * Leaves change tracking to the public save method.
     *
     * @throws \Throwable
     * If reading, encoding, encryption or replacing the file fails.
     *
     * @author Oleg Schildt
     */
    protected function saveSettingsData(array &$data): void
    {
        $this->saveJSON($data);
    } // saveSettingsData
} // ConfigSettingsManager
