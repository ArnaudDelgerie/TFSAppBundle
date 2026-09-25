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
 * A 400 passes through only on the close-guard routes and only when its body
 * carries their invalid_id — every other 400, known or not, stays the
 * generic protocol error so an unexpected answer can never be mistaken for
 * a documented route error.
 */
final class BridgeTransport
{
    private const CLOSE_GUARD_ROUTES = ['/close-guard/register', '/close-guard/remove'];

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
            400 => (\in_array($path, self::CLOSE_GUARD_ROUTES, true) && $this->hasError($response, 'invalid_id'))
                ? $response
                : throw BridgeProtocolException::forStatus(400),
            413 => throw BridgePayloadTooLargeException::create(),
            default => $response,
        };
    }

    /**
     * True when this 400's body names exactly the expected error code. An
     * unparseable body or any other code stays transport-level: the generic
     * mapping must never let a malformed or unknown error pass through.
     */
    private function hasError(ResponseInterface $response, string $error): bool
    {
        try {
            return ($response->toArray(false)['error'] ?? null) === $error;
        } catch (\Throwable) {
            return false;
        }
    }
}
