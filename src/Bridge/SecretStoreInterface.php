<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge;

use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgeUnavailableException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\SecretKeyNotDeclaredException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\SecretsNotEnabledException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\SecretStorageFailedException;

interface SecretStoreInterface
{
    /**
     * Never throws: false covers "no bridge" and "secrets group not
     * enabled" alike, plus any transport failure while probing.
     */
    public function isAvailable(): bool;

    /**
     * @return SecretKey[]
     *
     * @throws BridgeUnavailableException
     * @throws SecretsNotEnabledException
     * @throws SecretStorageFailedException the hub's storage failed (500 storage_failed); nothing was read or written
     */
    public function keys(): array;

    /**
     * @throws BridgeUnavailableException
     * @throws SecretsNotEnabledException
     * @throws SecretStorageFailedException the hub's storage failed (500 storage_failed); nothing was read or written
     */
    public function has(string $key): bool;

    /**
     * Null for a key that is declared but never set — not an error.
     *
     * @throws BridgeUnavailableException
     * @throws SecretsNotEnabledException
     * @throws SecretStorageFailedException the hub's storage failed (500 storage_failed); nothing was read or written
     * @throws SecretKeyNotDeclaredException
     */
    public function get(string $key): ?string;

    /**
     * @throws BridgeUnavailableException
     * @throws SecretsNotEnabledException
     * @throws SecretStorageFailedException the hub's storage failed (500 storage_failed); nothing was read or written
     * @throws SecretKeyNotDeclaredException
     */
    public function set(string $key, string $value): void;

    /**
     * False when no value existed to delete — not an error.
     *
     * @throws BridgeUnavailableException
     * @throws SecretsNotEnabledException
     * @throws SecretStorageFailedException the hub's storage failed (500 storage_failed); nothing was read or written
     * @throws SecretKeyNotDeclaredException
     */
    public function delete(string $key): bool;
}
