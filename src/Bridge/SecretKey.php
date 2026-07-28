<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge;

final class SecretKey
{
    public function __construct(
        private readonly string $key,
        private readonly bool $isSet,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function isSet(): bool
    {
        return $this->isSet;
    }
}
