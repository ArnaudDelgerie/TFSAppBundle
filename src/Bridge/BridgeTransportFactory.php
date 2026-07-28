<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge;

use Symfony\Component\HttpClient\HttpClient;

final class BridgeTransportFactory
{
    /**
     * @param (\Closure(): array<string, string>)|null $envReader defaults to
     *        reading the real process environment; tests inject a fake map
     *        instead of mutating $_SERVER/getenv
     */
    public function __construct(private readonly ?\Closure $envReader = null)
    {
    }

    public function create(): BridgeTransport
    {
        $reader = $this->envReader ?? static fn (): array => getenv();
        $env = $reader();

        $url = $env['TFS_BRIDGE_URL'] ?? '';

        if ('' === $url) {
            return new BridgeTransport(null);
        }

        $client = HttpClient::create()->withOptions([
            'base_uri' => $url,
            'headers' => [
                'Authorization' => 'Bearer '.($env['TFS_BRIDGE_TOKEN'] ?? ''),
            ],
        ]);

        return new BridgeTransport($client);
    }
}
