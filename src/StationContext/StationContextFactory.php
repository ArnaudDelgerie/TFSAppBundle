<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\StationContext;

final class StationContextFactory
{
    /**
     * @param (\Closure(): array<string, string>)|null $envReader defaults to
     *        reading the real process environment; tests inject a fake map
     *        instead of mutating $_SERVER/getenv
     */
    public function __construct(private readonly ?\Closure $envReader = null)
    {
    }

    public function create(): StationContextInterface
    {
        $reader = $this->envReader ?? static fn (): array => getenv();
        $env = $reader();

        $version = self::stringValue($env, 'TFS_APP_VERSION');

        return new StationContext(
            identifier: self::stringValue($env, 'TFS_APP_IDENTIFIER'),
            version: $version,
            asyncWorker: self::isFlagSet($env, 'TFS_ASYNC_WORKER'),
            workerTransports: self::workerTransports($env),
            keyringAvailable: self::isFlagSet($env, 'TFS_KEYRING_AVAILABLE'),
            bridgeEnabled: '' !== self::stringValue($env, 'TFS_BRIDGE_URL'),
            runningUnderStation: '' !== $version,
        );
    }

    /**
     * @param array<string, string> $env
     */
    private static function stringValue(array $env, string $key): string
    {
        return $env[$key] ?? '';
    }

    /**
     * @param array<string, string> $env
     */
    private static function isFlagSet(array $env, string $key): bool
    {
        return '1' === ($env[$key] ?? null);
    }

    /**
     * @param array<string, string> $env
     *
     * @return list<string>
     */
    private static function workerTransports(array $env): array
    {
        return array_values(array_filter(array_map(
            trim(...),
            explode(',', self::stringValue($env, 'TFS_WORKER_TRANSPORTS')),
        ), static fn (string $transport): bool => '' !== $transport));
    }
}
