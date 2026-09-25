<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Bridge;

use ArnaudDelgerie\TFSAppBundle\Bridge\BridgeTransport;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgePayloadTooLargeException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgeProtocolException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgeUnavailableException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BridgeTransportTest extends TestCase
{
    public function testRequestSendsBearerHeaderToScopedBaseUri(): void
    {
        $captured = null;
        $mock = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured) {
            $captured = [$method, $url, $options];

            return new MockResponse('{"keys":[]}', ['http_code' => 200]);
        });
        $scoped = $mock->withOptions([
            'base_uri' => 'http://127.0.0.1:12345',
            'headers' => ['Authorization' => 'Bearer test-token'],
        ]);

        $response = (new BridgeTransport($scoped))->request('GET', '/secrets/keys');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('GET', $captured[0]);
        self::assertSame('http://127.0.0.1:12345/secrets/keys', $captured[1]);
        self::assertContains('Authorization: Bearer test-token', $captured[2]['headers']);
    }

    public function testNoClientMeansUnconfigured(): void
    {
        $transport = new BridgeTransport(null);

        self::assertFalse($transport->isConfigured());
    }

    public function testConfiguredTransportReportsItself(): void
    {
        $transport = new BridgeTransport(new MockHttpClient());

        self::assertTrue($transport->isConfigured());
    }

    public function testRequestWithNoClientThrowsBridgeUnavailable(): void
    {
        $this->expectException(BridgeUnavailableException::class);

        (new BridgeTransport(null))->request('GET', '/secrets/keys');
    }

    public function test401ThrowsBridgeProtocolException(): void
    {
        $mock = new MockHttpClient(static fn (): MockResponse => new MockResponse('{"error":"unauthorized"}', ['http_code' => 401]));

        $this->expectException(BridgeProtocolException::class);

        (new BridgeTransport($mock))->request('GET', '/secrets/keys');
    }

    public function test400ThrowsBridgeProtocolException(): void
    {
        $mock = new MockHttpClient(static fn (): MockResponse => new MockResponse('{"error":"invalid_body"}', ['http_code' => 400]));

        $this->expectException(BridgeProtocolException::class);

        (new BridgeTransport($mock))->request('POST', '/secrets/get');
    }

    public function test400WithRouteSpecificErrorCodePassesThroughUnthrown(): void
    {
        $mock = new MockHttpClient(static fn (): MockResponse => new MockResponse('{"error":"invalid_id"}', ['http_code' => 400]));

        $response = (new BridgeTransport($mock))->request('POST', '/close-guard/register');

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('invalid_id', $response->toArray(false)['error']);
    }

    public function test400WithUnparseableBodyStaysTransportLevel(): void
    {
        $mock = new MockHttpClient(static fn (): MockResponse => new MockResponse('<html>Bad Request</html>', ['http_code' => 400]));

        $this->expectException(BridgeProtocolException::class);

        (new BridgeTransport($mock))->request('POST', '/close-guard/register');
    }

    public function test413ThrowsBridgePayloadTooLarge(): void
    {
        $mock = new MockHttpClient(static fn (): MockResponse => new MockResponse('{"error":"payload_too_large"}', ['http_code' => 413]));

        $this->expectException(BridgePayloadTooLargeException::class);

        (new BridgeTransport($mock))->request('POST', '/secrets/set');
    }

    public function testRouteSpecificStatusesPassThroughUnthrown(): void
    {
        $mock404 = new MockHttpClient(static fn (): MockResponse => new MockResponse('{"error":"not_found"}', ['http_code' => 404]));
        $mock403 = new MockHttpClient(static fn (): MockResponse => new MockResponse('{"error":"key_not_declared"}', ['http_code' => 403]));

        $response404 = (new BridgeTransport($mock404))->request('GET', '/secrets/keys');
        $response403 = (new BridgeTransport($mock403))->request('POST', '/secrets/get');

        self::assertSame(404, $response404->getStatusCode());
        self::assertSame(403, $response403->getStatusCode());
    }
}
