<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\HubContext;

interface HubContextInterface
{
    public function identifier(): string;

    public function version(): string;

    public function isAsyncWorker(): bool;

    /**
     * The transports the host reports it is consuming.
     *
     * @return list<string>
     */
    public function workerTransports(): array;

    public function isKeyringAvailable(): bool;

    public function isBridgeEnabled(): bool;

    public function isRunningUnderHub(): bool;
}
