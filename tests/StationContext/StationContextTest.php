<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\StationContext;

use ArnaudDelgerie\TFSAppBundle\StationContext\StationContext;
use PHPUnit\Framework\TestCase;

final class StationContextTest extends TestCase
{
    public function testGettersReflectConstructorArguments(): void
    {
        $context = new StationContext(
            identifier: 'dev.local.demo-project',
            version: '1.2.3',
            asyncWorker: true,
            keyringAvailable: true,
            bridgeEnabled: true,
            runningUnderStation: true,
        );

        self::assertSame('dev.local.demo-project', $context->identifier());
        self::assertSame('1.2.3', $context->version());
        self::assertTrue($context->isAsyncWorker());
        self::assertTrue($context->isKeyringAvailable());
        self::assertTrue($context->isBridgeEnabled());
        self::assertTrue($context->isRunningUnderStation());
    }

    public function testGettersReflectFalsyConstructorArguments(): void
    {
        $context = new StationContext(
            identifier: '',
            version: '',
            asyncWorker: false,
            keyringAvailable: false,
            bridgeEnabled: false,
            runningUnderStation: false,
        );

        self::assertSame('', $context->identifier());
        self::assertSame('', $context->version());
        self::assertFalse($context->isAsyncWorker());
        self::assertFalse($context->isKeyringAvailable());
        self::assertFalse($context->isBridgeEnabled());
        self::assertFalse($context->isRunningUnderStation());
    }
}
