<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge;

use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgeUnavailableException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\SecretKeyNotDeclaredException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\SecretsNotEnabledException;

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
     */
    public function keys(): array;

    /**
     * @throws BridgeUnavailableException
     * @throws SecretsNotEnabledException
     */
    public function has(string $key): bool;

    /**
     * Null for a key that is declared but never set — not an error.
     *
     * @throws BridgeUnavailableException
     * @throws SecretsNotEnabledException
     * @throws SecretKeyNotDeclaredException
     */
    public function get(string $key): ?string;

    /**
     * @throws BridgeUnavailableException
     * @throws SecretsNotEnabledException
     * @throws SecretKeyNotDeclaredException
     */
    public function set(string $key, string $value): void;

    /**
     * False when no value existed to delete — not an error.
     *
     * @throws BridgeUnavailableException
     * @throws SecretsNotEnabledException
     * @throws SecretKeyNotDeclaredException
     */
    public function delete(string $key): bool;
}
