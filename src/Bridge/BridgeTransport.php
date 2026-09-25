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
 * A 400 is route-specific when its body carries any error other than the
 * transport's own invalid_body (the close-guard routes' invalid_id today)
 * and passes through the same way.
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
            401 => throw BridgeProtocolException::forStatus(401),
            400 => $this->hasRouteSpecificError($response)
                ? $response
                : throw BridgeProtocolException::forStatus(400),
            413 => throw BridgePayloadTooLargeException::create(),
            default => $response,
        };
    }

    /**
     * True when this 400's body names a route-specific error code rather
     * than the transport's own invalid_body. An unparseable body stays
     * transport-level: the generic mapping must never let a malformed
     * error slip through unthrown.
     */
    private function hasRouteSpecificError(ResponseInterface $response): bool
    {
        try {
            $error = $response->toArray(false)['error'] ?? null;
        } catch (\Throwable) {
            return false;
        }

        return \is_string($error) && 'invalid_body' !== $error;
    }
}
