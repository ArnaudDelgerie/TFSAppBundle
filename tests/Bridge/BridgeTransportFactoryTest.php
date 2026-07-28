<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Bridge;

use ArnaudDelgerie\TFSAppBundle\Bridge\BridgeTransportFactory;
use PHPUnit\Framework\TestCase;

final class BridgeTransportFactoryTest extends TestCase
{
    public function testAbsentBridgeUrlYieldsUnconfiguredTransport(): void
    {
        $transport = (new BridgeTransportFactory(static fn (): array => []))->create();

        self::assertFalse($transport->isConfigured());
    }

    public function testPresentBridgeUrlYieldsConfiguredTransport(): void
    {
        $transport = (new BridgeTransportFactory(static fn (): array => [
            'TFS_BRIDGE_URL' => 'http://127.0.0.1:12345',
            'TFS_BRIDGE_TOKEN' => 'test-token',
        ]))->create();

        self::assertTrue($transport->isConfigured());
    }
}
