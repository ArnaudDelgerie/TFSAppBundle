<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\StationContext;

use ArnaudDelgerie\TFSAppBundle\StationContext\StationContextFactory;
use PHPUnit\Framework\TestCase;

final class StationContextFactoryTest extends TestCase
{
    public function testFullEnvIsReflectedByEveryGetter(): void
    {
        $context = (new StationContextFactory(static fn (): array => [
            'TFS_APP_IDENTIFIER' => 'dev.local.demo-project',
            'TFS_APP_VERSION' => '1.2.3',
            'TFS_ASYNC_WORKER' => '1',
            'TFS_WORKER_TRANSPORTS' => 'urgent,scheduled,background',
            'TFS_KEYRING_AVAILABLE' => '1',
            'TFS_BRIDGE_URL' => 'http://127.0.0.1:12345',
        ]))->create();

        self::assertSame('dev.local.demo-project', $context->identifier());
        self::assertSame('1.2.3', $context->version());
        self::assertTrue($context->isAsyncWorker());
        self::assertSame(['urgent', 'scheduled', 'background'], $context->workerTransports());
        self::assertTrue($context->isKeyringAvailable());
        self::assertTrue($context->isBridgeEnabled());
        self::assertTrue($context->isRunningUnderStation());
    }

    public function testEmptyEnvFallsBackToSafeDefaults(): void
    {
        $context = (new StationContextFactory(static fn (): array => []))->create();

        self::assertSame('', $context->identifier());
        self::assertSame('', $context->version());
        self::assertFalse($context->isAsyncWorker());
        self::assertSame([], $context->workerTransports());
        self::assertFalse($context->isKeyringAvailable());
        self::assertFalse($context->isBridgeEnabled());
        self::assertFalse($context->isRunningUnderStation());
    }

    public function testKeyringFlagAtZeroIsFalse(): void
    {
        $context = (new StationContextFactory(static fn (): array => [
            'TFS_APP_VERSION' => '1.0.0',
            'TFS_KEYRING_AVAILABLE' => '0',
        ]))->create();

        self::assertFalse($context->isKeyringAvailable());
    }

    public function testAsyncWorkerFlagAtOneIsTrue(): void
    {
        $context = (new StationContextFactory(static fn (): array => [
            'TFS_APP_VERSION' => '1.0.0',
            'TFS_ASYNC_WORKER' => '1',
        ]))->create();

        self::assertTrue($context->isAsyncWorker());
    }

    public function testWorkerTransportsAreTrimmedAndKeepTheirDeclarationOrder(): void
    {
        $context = (new StationContextFactory(static fn (): array => [
            'TFS_WORKER_TRANSPORTS' => ' urgent, scheduled , background, ',
        ]))->create();

        self::assertSame(['urgent', 'scheduled', 'background'], $context->workerTransports());
    }

    public function testWorkerTransportsDoNotSetTheAsyncWorkerFlag(): void
    {
        $context = (new StationContextFactory(static fn (): array => [
            'TFS_WORKER_TRANSPORTS' => 'urgent',
        ]))->create();

        self::assertSame(['urgent'], $context->workerTransports());
        self::assertFalse($context->isAsyncWorker());
    }

    public function testBridgeUrlPresentEnablesBridge(): void
    {
        $context = (new StationContextFactory(static fn (): array => [
            'TFS_BRIDGE_URL' => 'http://127.0.0.1:54321',
        ]))->create();

        self::assertTrue($context->isBridgeEnabled());
    }

    public function testBridgeUrlAbsentDisablesBridge(): void
    {
        $context = (new StationContextFactory(static fn (): array => [
            'TFS_APP_VERSION' => '1.0.0',
        ]))->create();

        self::assertFalse($context->isBridgeEnabled());
    }

    public function testDefaultEnvReaderReadsRealProcessEnvironment(): void
    {
        putenv('TFS_APP_IDENTIFIER=dev.local.real-env-test');

        try {
            $context = (new StationContextFactory())->create();

            self::assertSame('dev.local.real-env-test', $context->identifier());
        } finally {
            putenv('TFS_APP_IDENTIFIER');
        }
    }
}
