<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Bridge;

use ArnaudDelgerie\TFSAppBundle\Bridge\BackendCloseGuard;
use ArnaudDelgerie\TFSAppBundle\Bridge\BridgeTransport;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgePayloadTooLargeException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgeProtocolException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\CloseGuardClosingException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\CloseGuardInvalidIdException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\CloseGuardTooManyException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BackendCloseGuardTest extends TestCase
{
    public function testRegisterAcknowledgedByHubReturnsTrue(): void
    {
        $guard = $this->guardFor(static fn (): MockResponse => new MockResponse('{"ok":true}', ['http_code' => 200]));

        self::assertTrue($guard->register('export:42'));
    }

    public function testRemoveAcknowledgedByHubReturnsTrue(): void
    {
        $guard = $this->guardFor(static fn (): MockResponse => new MockResponse('{"ok":true}', ['http_code' => 200]));

        self::assertTrue($guard->remove('export:42'));
    }

    public function testNoBridgeReturnsFalseWithoutImplyingProtection(): void
    {
        $guard = new BackendCloseGuard(new BridgeTransport(null));

        self::assertFalse($guard->register('export:42'));
        self::assertFalse($guard->remove('export:42'));
    }

    public function testGatedRouteReturnsFalseEvenThoughBridgeIsUp(): void
    {
        // Another action group started the bridge; close_guard did not
        // declare "bridge", so its routes answer 404 like unknown paths.
        $guard = $this->guardFor(static fn (): MockResponse => new MockResponse('{"error":"not_found"}', ['http_code' => 404]));

        self::assertFalse($guard->register('export:42'));
        self::assertFalse($guard->remove('export:42'));
    }

    public function testInvalidIdThrowsTypedException(): void
    {
        $guard = $this->guardFor(static fn (): MockResponse => new MockResponse('{"error":"invalid_id"}', ['http_code' => 400]));

        $this->expectException(CloseGuardInvalidIdException::class);

        $guard->register('');
    }

    public function testRemoveInvalidIdThrowsTypedException(): void
    {
        $guard = $this->guardFor(static fn (): MockResponse => new MockResponse('{"error":"invalid_id"}', ['http_code' => 400]));

        $this->expectException(CloseGuardInvalidIdException::class);

        $guard->remove('job');
    }

    public function testInvalidBodyThrowsBridgeProtocolException(): void
    {
        $guard = $this->guardFor(static fn (): MockResponse => new MockResponse('{"error":"invalid_body"}', ['http_code' => 400]));

        $this->expectException(BridgeProtocolException::class);

        $guard->register('export:42');
    }

    public function testUnauthorizedThrowsBridgeProtocolException(): void
    {
        $guard = $this->guardFor(static fn (): MockResponse => new MockResponse('{"error":"unauthorized"}', ['http_code' => 401]));

        $this->expectException(BridgeProtocolException::class);

        $guard->register('export:42');
    }

    public function testPayloadTooLargeThrowsTypedException(): void
    {
        $guard = $this->guardFor(static fn (): MockResponse => new MockResponse('{"error":"payload_too_large"}', ['http_code' => 413]));

        $this->expectException(BridgePayloadTooLargeException::class);

        $guard->register('export:42');
    }

    public function testTooManyGuardsThrowsTypedException(): void
    {
        $guard = $this->guardFor(static fn (): MockResponse => new MockResponse('{"error":"too_many_guards"}', ['http_code' => 429]));

        $this->expectException(CloseGuardTooManyException::class);

        $guard->register('export:42');
    }

    public function testClosingThrowsTypedException(): void
    {
        $guard = $this->guardFor(static fn (): MockResponse => new MockResponse('{"error":"closing"}', ['http_code' => 503]));

        $this->expectException(CloseGuardClosingException::class);

        $guard->register('export:42');
    }

    public function testRemoveWhileClosingThrowsTypedException(): void
    {
        $guard = $this->guardFor(static fn (): MockResponse => new MockResponse('{"error":"closing"}', ['http_code' => 503]));

        $this->expectException(CloseGuardClosingException::class);

        $guard->remove('export:42');
    }

    public function testUnexpectedStatusThrowsBridgeProtocolException(): void
    {
        $guard = $this->guardFor(static fn (): MockResponse => new MockResponse('{"error":"boom"}', ['http_code' => 500]));

        $this->expectException(BridgeProtocolException::class);

        $guard->register('export:42');
    }

    public function testTransportFailurePropagates(): void
    {
        $guard = $this->guardFor(static fn (): MockResponse => throw new TransportException('connection refused'));

        $this->expectException(TransportException::class);

        $guard->register('export:42');
    }

    public function testRegisterSendsOnlyTheIdToTheRegisterRoute(): void
    {
        $captured = [];
        $guard = $this->capturingGuard($captured);

        $guard->register('export:42');

        self::assertCount(1, $captured);
        [$method, $url, $body] = $captured[0];
        self::assertSame('POST', $method);
        self::assertSame('http://127.0.0.1:12345/close-guard/register', $url);
        self::assertSame('{"id":"export:42"}', $body);
    }

    public function testRemoveSendsOnlyTheIdToTheRemoveRoute(): void
    {
        $captured = [];
        $guard = $this->capturingGuard($captured);

        $guard->remove('mail:42');

        self::assertCount(1, $captured);
        [$method, $url, $body] = $captured[0];
        self::assertSame('POST', $method);
        self::assertSame('http://127.0.0.1:12345/close-guard/remove', $url);
        self::assertSame('{"id":"mail:42"}', $body);
    }

    public function testRepeatedRegistrationIsIdempotent(): void
    {
        $guard = $this->guardFor(static fn (): MockResponse => new MockResponse('{"ok":true}', ['http_code' => 200]));

        self::assertTrue($guard->register('export:42'));
        self::assertTrue($guard->register('export:42'));
    }

    public function testRemovingAnAbsentIdIsAcknowledgedHarmlessly(): void
    {
        $guard = $this->guardFor(static fn (): MockResponse => new MockResponse('{"ok":true}', ['http_code' => 200]));

        self::assertTrue($guard->remove('export:42'));
        self::assertTrue($guard->remove('export:42'));
    }

    public function testRemovingOneIdNeverNamesAnother(): void
    {
        $captured = [];
        $guard = $this->capturingGuard($captured);

        $guard->register('export:probe');
        $guard->register('mail:probe');
        $guard->remove('export:probe');

        self::assertCount(3, $captured);
        self::assertSame('{"id":"export:probe"}', $captured[0][2]);
        self::assertSame('{"id":"mail:probe"}', $captured[1][2]);
        // The removal names only its own id: mail:probe's guard survives
        // export:probe completing, on the hub's side and on ours.
        self::assertSame('http://127.0.0.1:12345/close-guard/remove', $captured[2][1]);
        self::assertSame('{"id":"export:probe"}', $captured[2][2]);
    }

    private function capturingGuard(array &$captured): BackendCloseGuard
    {
        $mock = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured[] = [$method, $url, $options['body'] ?? ''];

            return new MockResponse('{"ok":true}', ['http_code' => 200]);
        });
        $scoped = $mock->withOptions([
            'base_uri' => 'http://127.0.0.1:12345',
            'headers' => ['Authorization' => 'Bearer test-token'],
        ]);

        return new BackendCloseGuard(new BridgeTransport($scoped));
    }

    private function guardFor(\Closure $responder): BackendCloseGuard
    {
        return new BackendCloseGuard(new BridgeTransport(new MockHttpClient($responder)));
    }
}
