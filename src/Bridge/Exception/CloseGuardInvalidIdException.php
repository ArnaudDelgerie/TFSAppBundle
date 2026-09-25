<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge\Exception;

final class CloseGuardInvalidIdException extends BridgeException
{
    public static function forId(string $id): self
    {
        return new self(sprintf(
            'The hub refused the guard id "%s": it must be a non-empty string of at most 128 bytes of UTF-8.',
            $id,
        ));
    }
}
