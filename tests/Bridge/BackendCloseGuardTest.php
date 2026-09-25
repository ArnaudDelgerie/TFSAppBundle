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

    private function guardFor(\Closure $responder): BackendCloseGuard
    {
        return new BackendCloseGuard(new BridgeTransport(new MockHttpClient($responder)));
    }
}
