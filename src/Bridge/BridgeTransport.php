<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge;

use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgePayloadTooLargeException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgeProtocolException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgeUnavailableException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Low-level scoped client over TFS_BRIDGE_URL. A null $client means "no
 * bridge" (TFS_BRIDGE_URL absent) — request() never fabricates a call
 * against a null URL, it throws instead (CONTRACT.md §8).
 *
 * Maps the universal, route-agnostic wire errors (401 bad token, 413 body
 * cap, 400 invalid body) to typed exceptions here; route-specific ambiguous
 * ones (404, 403) are left for the caller to interpret with its own context.
 */
final class BridgeTransport
{
    public function __construct(private readonly ?HttpClientInterface $client)
    {
    }

    public function isConfigured(): bool
    {
        return null !== $this->client;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $path, array $options = []): ResponseInterface
    {
        if (null === $this->client) {
            throw BridgeUnavailableException::noBridge();
        }

        $response = $this->client->request($method, $path, $options);

        return match ($response->getStatusCode()) {
            400, 401 => throw BridgeProtocolException::forStatus($response->getStatusCode()),
            413 => throw BridgePayloadTooLargeException::create(),
            default => $response,
        };
    }
}
