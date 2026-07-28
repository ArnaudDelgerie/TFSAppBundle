<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Bridge;

use ArnaudDelgerie\TFSAppBundle\Bridge\BridgeTransport;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\BridgeUnavailableException;
use ArnaudDelgerie\TFSAppBundle\Bridge\Exception\UpdateNotEnabledException;
use ArnaudDelgerie\TFSAppBundle\Bridge\UpdateChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class UpdateCheckerTest extends TestCase
{
    public function testOkBodyIsParsedIntoResult(): void
    {
        $checker = $this->checkerFor(static fn (): MockResponse => new MockResponse(
            '{"status":"ok","current":"1.1.0","latest":"1.2.0","update_available":true,"release_url":"https://github.com/owner/repo/releases/tag/v1.2.0"}',
            ['http_code' => 200],
        ));

        $result = $checker->check();

        self::assertTrue($result->wasReached());
        self::assertSame('1.1.0', $result->current());
        self::assertSame('1.2.0', $result->latest());
        self::assertTrue($result->isUpdateAvailable());
        self::assertSame('https://github.com/owner/repo/releases/tag/v1.2.0', $result->releaseUrl());
    }

    public function testUnavailableBodyIsParsedIntoResult(): void
    {
        $checker = $this->checkerFor(static fn (): MockResponse => new MockResponse(
            '{"status":"unavailable","reason":"offline"}',
            ['http_code' => 200],
        ));

        $result = $checker->check();

        self::assertFalse($result->wasReached());
        self::assertFalse($result->isUpdateAvailable());
        self::assertSame('offline', $result->reason());
    }

    public function testGatedGroupThrowsUpdateNotEnabled(): void
    {
        $checker = $this->checkerFor(static fn (): MockResponse => new MockResponse('{"error":"not_found"}', ['http_code' => 404]));

        $this->expectException(UpdateNotEnabledException::class);

        $checker->check();
    }

    public function testNoBridgeUrlThrowsBridgeUnavailable(): void
    {
        $checker = new UpdateChecker(new BridgeTransport(null));

        $this->expectException(BridgeUnavailableException::class);

        $checker->check();
    }

    public function testNoBridgeUrlMakesIsAvailableFalseWithoutNetworkCall(): void
    {
        $checker = new UpdateChecker(new BridgeTransport(null));

        self::assertFalse($checker->isAvailable());
    }

    public function testConfiguredBridgeMakesIsAvailableTrueWithoutNetworkCall(): void
    {
        $checker = $this->checkerFor(static fn (): MockResponse => self::fail('isAvailable() must not perform a network call.'));

        self::assertTrue($checker->isAvailable());
    }

    private function checkerFor(\Closure $responder): UpdateChecker
    {
        return new UpdateChecker(new BridgeTransport(new MockHttpClient($responder)));
    }
}
