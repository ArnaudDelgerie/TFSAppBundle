<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge\Exception;

final class BridgeProtocolException extends BridgeException
{
    public static function forStatus(int $status): self
    {
        return new self(sprintf('Unexpected bridge response (HTTP %d).', $status));
    }
}
