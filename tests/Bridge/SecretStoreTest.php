<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Bridge;

use ArnaudDelgerie\TFSAppBundle\Bridge\BridgeTransport;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgePayloadTooLargeException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgeUnavailableException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\SecretKeyNotDeclaredException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\SecretsNotEnabledException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\SecretStorageFailedException;
use ArnaudDelgerie\TFSAppBundle\Bridge\SecretKey;
use ArnaudDelgerie\TFSAppBundle\Bridge\SecretStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SecretStoreTest extends TestCase
{
    public function testKeysAreParsedIntoSecretKeyObjects(): void
    {
        $store = $this->storeFor(static fn (string $method, string $url): MockResponse => match (true) {
            str_contains($url, '/secrets/keys') => new MockResponse('{"keys":[{"key":"openai","set":true},{"key":"anthropic","set":false}]}', ['http_code' => 200]),
            default => new MockResponse('{}', ['http_code' => 404]),
        });

        self::assertEquals(
            [new SecretKey('openai', true), new SecretKey('anthropic', false)],
            $store->keys(),
        );
    }

    public function testHasReturnsBooleanFromWire(): void
    {
        $store = $this->storeFor(static fn (string $method, string $url): MockResponse => match (true) {
            str_contains($url, '/secrets/keys') => new MockResponse('{"keys":[]}', ['http_code' => 200]),
            str_contains($url, '/secrets/has') => new MockResponse('{"has":true}', ['http_code' => 200]),
            default => new MockResponse('{}', ['http_code' => 404]),
        });

        self::assertTrue($store->has('openai'));
    }

    public function testGetReturnsValueForSetKey(): void
    {
        $store = $this->storeFor(static fn (string $method, string $url): MockResponse => match (true) {
            str_contains($url, '/secrets/keys') => new MockResponse('{"keys":[]}', ['http_code' => 200]),
            str_contains($url, '/secrets/get') => new MockResponse('{"value":"sk-123"}', ['http_code' => 200]),
            default => new MockResponse('{}', ['http_code' => 404]),
        });

        self::assertSame('sk-123', $store->get('openai'));
    }

    public function testGetReturnsNullForDeclaredButUnsetKey(): void
    {
        $store = $this->storeFor(static fn (string $method, string $url): MockResponse => match (true) {
            str_contains($url, '/secrets/keys') => new MockResponse('{"keys":[]}', ['http_code' => 200]),
            str_contains($url, '/secrets/get') => new MockResponse('{"error":"not_found"}', ['http_code' => 404]),
            default => new MockResponse('{}', ['http_code' => 404]),
        });

        self::assertNull($store->get('openai'));
    }

    public function testSetReturnsVoidOnSuccess(): void
    {
        $store = $this->storeFor(static fn (string $method, string $url): MockResponse => match (true) {
            str_contains($url, '/secrets/keys') => new MockResponse('{"keys":[]}', ['http_code' => 200]),
            str_contains($url, '/secrets/set') => new MockResponse('{"ok":true}', ['http_code' => 200]),
            default => new MockResponse('{}', ['http_code' => 404]),
        });

        $store->set('openai', 'sk-123');
        $this->addToAssertionCount(1);
    }

    public function testDeleteReturnsTrueWhenValueExisted(): void
    {
        $store = $this->storeFor(static fn (string $method, string $url): MockResponse => match (true) {
            str_contains($url, '/secrets/keys') => new MockResponse('{"keys":[]}', ['http_code' => 200]),
            str_contains($url, '/secrets/delete') => new MockResponse('{"ok":true}', ['http_code' => 200]),
            default => new MockResponse('{}', ['http_code' => 404]),
        });

        self::assertTrue($store->delete('openai'));
    }

    public function testDeleteReturnsFalseWhenValueDidNotExist(): void
    {
        $store = $this->storeFor(static fn (string $method, string $url): MockResponse => match (true) {
            str_contains($url, '/secrets/keys') => new MockResponse('{"keys":[]}', ['http_code' => 200]),
            str_contains($url, '/secrets/delete') => new MockResponse('{"ok":false}', ['http_code' => 200]),
            default => new MockResponse('{}', ['http_code' => 404]),
        });

        self::assertFalse($store->delete('openai'));
    }

    public function testGatedGroupMakesIsAvailableFalse(): void
    {
        $store = $this->storeFor(static fn (): MockResponse => new MockResponse('{"error":"not_found"}', ['http_code' => 404]));

        self::assertFalse($store->isAvailable());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function operationsProvider(): iterable
    {
        yield 'keys' => ['keys'];
        yield 'has' => ['has'];
        yield 'get' => ['get'];
        yield 'set' => ['set'];
        yield 'delete' => ['delete'];
    }

    #[DataProvider('operationsProvider')]
    public function testGatedGroupMakesEveryOperationThrowSecretsNotEnabled(string $operation): void
    {
        $store = $this->storeFor(static fn (): MockResponse => new MockResponse('{"error":"not_found"}', ['http_code' => 404]));

        $this->expectException(SecretsNotEnabledException::class);

        $this->callOperation($store, $operation);
    }

    #[DataProvider('operationsProvider')]
    public function testNoBridgeUrlThrowsBridgeUnavailableOnEveryOperation(string $operation): void
    {
        $store = new SecretStore(new BridgeTransport(null));

        $this->expectException(BridgeUnavailableException::class);

        $this->callOperation($store, $operation);
    }

    public function testNoBridgeUrlNeverThrowsFromIsAvailable(): void
    {
        $store = new SecretStore(new BridgeTransport(null));

        self::assertFalse($store->isAvailable());
    }

    public function test403ThrowsSecretKeyNotDeclared(): void
    {
        $store = $this->storeFor(static fn (string $method, string $url): MockResponse => match (true) {
            str_contains($url, '/secrets/keys') => new MockResponse('{"keys":[]}', ['http_code' => 200]),
            default => new MockResponse('{"error":"key_not_declared"}', ['http_code' => 403]),
        });

        $this->expectException(SecretKeyNotDeclaredException::class);

        $store->get('app-secret');
    }

    public function test413OnSetThrowsBridgePayloadTooLarge(): void
    {
        $store = $this->storeFor(static fn (string $method, string $url): MockResponse => match (true) {
            str_contains($url, '/secrets/keys') => new MockResponse('{"keys":[]}', ['http_code' => 200]),
            str_contains($url, '/secrets/set') => new MockResponse('{"error":"value_too_large"}', ['http_code' => 413]),
            default => new MockResponse('{}', ['http_code' => 404]),
        });

        $this->expectException(BridgePayloadTooLargeException::class);

        $store->set('openai', str_repeat('x', 9000));
    }

    #[DataProvider('operationsProvider')]
    public function test500StorageFailedThrowsSecretStorageFailedOnEveryOperation(string $operation): void
    {
        $probes = 0;
        // the availability probe (first GET /secrets/keys) succeeds, the operation itself gets the 500
        $store = $this->storeFor(static function (string $method, string $url) use (&$probes): MockResponse {
            if (str_contains($url, '/secrets/keys') && 0 === $probes++) {
                return new MockResponse('{"keys":[]}', ['http_code' => 200]);
            }

            return new MockResponse('{"error":"storage_failed"}', ['http_code' => 500]);
        });

        $this->expectException(SecretStorageFailedException::class);

        $this->callOperation($store, $operation);
    }

    private function callOperation(SecretStore $store, string $operation): void
    {
        match ($operation) {
            'keys' => $store->keys(),
            'has' => $store->has('openai'),
            'get' => $store->get('openai'),
            'set' => $store->set('openai', 'sk-123'),
            'delete' => $store->delete('openai'),
        };
    }

    private function storeFor(\Closure $responder): SecretStore
    {
        return new SecretStore(new BridgeTransport(new MockHttpClient($responder)));
    }
}
