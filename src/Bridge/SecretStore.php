<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Bridge;

use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgeUnavailableException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\SecretKeyNotDeclaredException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\SecretStorageFailedException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\SecretsNotEnabledException;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SecretStore implements SecretStoreInterface
{
    private ?bool $available = null;

    public function __construct(private readonly BridgeTransport $transport)
    {
    }

    public function isAvailable(): bool
    {
        if (null !== $this->available) {
            return $this->available;
        }

        if (!$this->transport->isConfigured()) {
            return $this->available = false;
        }

        try {
            $response = $this->transport->request('GET', '/secrets/keys');
            $this->available = 200 === $response->getStatusCode();
        } catch (\Throwable) {
            $this->available = false;
        }

        return $this->available;
    }

    public function keys(): array
    {
        $this->ensureAvailable();

        /** @var array{keys: list<array{key: string, set: bool}>} $data */
        $data = $this->assertStored($this->transport->request('GET', '/secrets/keys'))->toArray();

        return array_map(
            static fn (array $entry): SecretKey => new SecretKey($entry['key'], $entry['set']),
            $data['keys'],
        );
    }

    public function has(string $key): bool
    {
        $this->ensureAvailable();

        /** @var array{has: bool} $data */
        $data = $this->requestForKey('POST', '/secrets/has', $key)->toArray();

        return $data['has'];
    }

    public function get(string $key): ?string
    {
        $this->ensureAvailable();

        $response = $this->assertStored($this->transport->request('POST', '/secrets/get', ['json' => ['key' => $key]]));

        return match ($response->getStatusCode()) {
            404 => null, // declared-but-unset — availability already confirmed above
            403 => throw SecretKeyNotDeclaredException::forKey($key),
            default => $response->toArray()['value'],
        };
    }

    public function set(string $key, string $value): void
    {
        $this->ensureAvailable();

        $this->requestForKey('POST', '/secrets/set', $key, ['key' => $key, 'value' => $value]);
    }

    public function delete(string $key): bool
    {
        $this->ensureAvailable();

        /** @var array{ok: bool} $data */
        $data = $this->requestForKey('POST', '/secrets/delete', $key)->toArray();

        return $data['ok'];
    }

    /**
     * @param array<string, string>|null $body defaults to {"key": $key}
     */
    private function requestForKey(string $method, string $path, string $key, ?array $body = null): ResponseInterface
    {
        $response = $this->transport->request($method, $path, ['json' => $body ?? ['key' => $key]]);

        if (403 === $response->getStatusCode()) {
            throw SecretKeyNotDeclaredException::forKey($key);
        }

        return $this->assertStored($response);
    }

    /**
     * Contract §7: a 500 is the hub's secret storage failing, and its body
     * names it. Any other 500 is not a documented answer and is left to the
     * HttpClient as before.
     */
    private function assertStored(ResponseInterface $response): ResponseInterface
    {
        if (500 === $response->getStatusCode()) {
            try {
                $storageFailed = ($response->toArray(false)['error'] ?? null) === 'storage_failed';
            } catch (\Throwable) {
                $storageFailed = false;
            }

            if ($storageFailed) {
                throw SecretStorageFailedException::create();
            }
        }

        return $response;
    }

    private function ensureAvailable(): void
    {
        if (!$this->transport->isConfigured()) {
            throw BridgeUnavailableException::noBridge();
        }

        if (!$this->isAvailable()) {
            throw SecretsNotEnabledException::create();
        }
    }
}
