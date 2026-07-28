<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge\Exception;

final class SecretsNotEnabledException extends BridgeException
{
    public static function create(): self
    {
        return new self('The secrets bridge is not enabled: declare "actions.secrets.bridge: true" in tfsapp.config.json.');
    }
}
