<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge\Exception;

final class BridgeUnavailableException extends BridgeException
{
    public static function noBridge(): self
    {
        return new self('No TFS bridge is available: running outside the station, or no "actions" group declared "bridge" in tfsapp.config.json.');
    }
}
