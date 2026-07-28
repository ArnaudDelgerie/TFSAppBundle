<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge\Exception;

final class UpdateNotEnabledException extends BridgeException
{
    public static function create(): self
    {
        return new self('The update bridge is not enabled: declare "actions.update.bridge: true" in tfsapp.config.json.');
    }
}
