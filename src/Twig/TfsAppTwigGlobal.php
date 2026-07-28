<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Twig;

use ArnaudDelgerie\TFSAppBundle\StationContext\StationContextInterface;

final class TfsAppTwigGlobal
{
    public readonly string $version;

    // snake_case: these are read as `tfsapp.keyring_available` etc. in Twig,
    // which resolves plain public properties by exact name.
    public readonly bool $keyring_available;

    public readonly bool $async_worker;

    public readonly bool $running_under_station;

    public readonly bool $bridge_enabled;

    public function __construct(StationContextInterface $context)
    {
        $this->version = $context->version();
        $this->keyring_available = $context->isKeyringAvailable();
        $this->async_worker = $context->isAsyncWorker();
        $this->running_under_station = $context->isRunningUnderStation();
        $this->bridge_enabled = $context->isBridgeEnabled();
    }
}
