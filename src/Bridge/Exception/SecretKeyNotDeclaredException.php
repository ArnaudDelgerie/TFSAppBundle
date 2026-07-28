<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge\Exception;

final class SecretKeyNotDeclaredException extends BridgeException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf('Secret key "%s" is reserved, or not declared in "actions.secrets.keys".', $key));
    }
}
