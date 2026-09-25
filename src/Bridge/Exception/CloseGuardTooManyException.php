<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge\Exception;

final class CloseGuardTooManyException extends BridgeException
{
    public static function create(): self
    {
        return new self('The hub\'s backend guard capacity (16 per app instance) is exhausted: registration was refused, no guard was installed.');
    }
}
