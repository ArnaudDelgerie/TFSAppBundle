<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge\Exception;

final class SecretStorageFailedException extends BridgeException
{
    public static function create(): self
    {
        return new self('The hub could not reach its secret storage (500 storage_failed): the operation did not take effect.');
    }
}
