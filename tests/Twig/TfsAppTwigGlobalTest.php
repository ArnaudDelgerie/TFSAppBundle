<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Twig;

use ArnaudDelgerie\TFSAppBundle\Twig\TfsAppTwigGlobal;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

final class TfsAppTwigGlobalTest extends TestCase
{
    private ?TwigGlobalTestKernel $kernel = null;

    protected function setUp(): void
    {
        putenv('TFS_APP_VERSION=1.2.3');
        putenv('TFS_ASYNC_WORKER=1');
        putenv('TFS_KEYRING_AVAILABLE=1');
        putenv('TFS_BRIDGE_URL=http://127.0.0.1:54321');
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        restore_exception_handler();

        putenv('TFS_APP_VERSION');
        putenv('TFS_ASYNC_WORKER');
        putenv('TFS_KEYRING_AVAILABLE');
        putenv('TFS_BRIDGE_URL');
    }

    public function testGlobalResolvesToTheStationContextWhenTwigIsPresent(): void
    {
        $this->kernel = new TwigGlobalTestKernel();
        $this->kernel->boot();

        $twig = $this->kernel->getContainer()->get('test.service_container')->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $global = $twig->getGlobals()['tfsapp'] ?? null;

        self::assertInstanceOf(TfsAppTwigGlobal::class, $global);
        self::assertSame('1.2.3', $global->version);
        self::assertTrue($global->async_worker);
        self::assertTrue($global->keyring_available);
        self::assertTrue($global->bridge_enabled);
        self::assertTrue($global->running_under_station);
    }

    public function testGlobalIsNotRegisteredWhenToggledOff(): void
    {
        $this->kernel = new TwigGlobalTestKernel(false);
        $this->kernel->boot();

        $twig = $this->kernel->getContainer()->get('test.service_container')->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        self::assertArrayNotHasKey('tfsapp', $twig->getGlobals());
    }
}
