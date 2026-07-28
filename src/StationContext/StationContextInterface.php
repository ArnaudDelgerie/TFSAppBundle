<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\StationContext;

interface StationContextInterface
{
    public function identifier(): string;

    public function version(): string;

    public function isAsyncWorker(): bool;

    public function isKeyringAvailable(): bool;

    public function isBridgeEnabled(): bool;

    public function isRunningUnderStation(): bool;
}
