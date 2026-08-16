<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\StationContext;

final class StationContext implements StationContextInterface
{
    public function __construct(
        private readonly string $identifier,
        private readonly string $version,
        private readonly bool $asyncWorker,
        /** @var list<string> */
        private readonly array $workerTransports,
        private readonly bool $keyringAvailable,
        private readonly bool $bridgeEnabled,
        private readonly bool $runningUnderStation,
    ) {
    }

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function isAsyncWorker(): bool
    {
        return $this->asyncWorker;
    }

    public function workerTransports(): array
    {
        return $this->workerTransports;
    }

    public function isKeyringAvailable(): bool
    {
        return $this->keyringAvailable;
    }

    public function isBridgeEnabled(): bool
    {
        return $this->bridgeEnabled;
    }

    public function isRunningUnderStation(): bool
    {
        return $this->runningUnderStation;
    }
}
