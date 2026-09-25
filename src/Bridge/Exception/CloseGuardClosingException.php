<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge\Exception;

final class CloseGuardClosingException extends BridgeException
{
    public static function create(): self
    {
        return new self('The hub has committed to shutting down: the backend guard namespace is closed for the rest of this process\'s life.');
    }
}
