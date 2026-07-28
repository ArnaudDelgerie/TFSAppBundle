<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge\Exception;

final class BridgePayloadTooLargeException extends BridgeException
{
    public static function create(): self
    {
        return new self('The bridge request exceeds the allowed size cap.');
    }
}
