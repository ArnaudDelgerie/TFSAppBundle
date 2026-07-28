<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Command;

use ArnaudDelgerie\TFSAppBundle\Tests\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class DoctorCommandTest extends TestCase
{
    private ?TestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        restore_exception_handler();

        putenv('TFS_APP_IDENTIFIER');
        putenv('TFS_APP_VERSION');
        putenv('TFS_ASYNC_WORKER');
        putenv('TFS_KEYRING_AVAILABLE');
    }

    public function testOutputCarriesStationContextAndAvailabilityLines(): void
    {
        putenv('TFS_APP_IDENTIFIER=dev.local.demo-project');
        putenv('TFS_APP_VERSION=1.2.3');
        putenv('TFS_ASYNC_WORKER=1');
        putenv('TFS_KEYRING_AVAILABLE=1');

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());

        $display = $tester->getDisplay();

        self::assertStringContainsString('1.2.3', $display);
        self::assertStringContainsString('keyring_available', $display);
        self::assertStringContainsString('async_worker', $display);
        self::assertStringContainsString('bridge_enabled', $display);
    }

    public function testAlwaysExitsSuccessWithoutBridgeConfigured(): void
    {
        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('secrets_available', $tester->getDisplay());
        self::assertStringContainsString('update_check_available', $tester->getDisplay());
    }

    private function createTester(): CommandTester
    {
        $this->kernel = new TestKernel('test', false);
        $this->kernel->boot();

        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        return new CommandTester($application->find('tfsapp:doctor'));
    }
}
