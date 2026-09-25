<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Bridge;

use ArnaudDelgerie\TFSAppBundle\Bridge\BackendCloseGuard;
use ArnaudDelgerie\TFSAppBundle\Bridge\BackendCloseGuardInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * The service as the container builds it: autowired through the
 * interface, sharing the single TFS_BRIDGE_URL/TFS_BRIDGE_TOKEN
 * transport with the secrets and update clients. The test process has
 * no bridge unless one of the env vars is deliberately set below, which
 * is itself the "no bridge" path the bundle ships with.
 */
final class BackendCloseGuardContainerTest extends TestCase
{
    private ?BackendCloseGuardTestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        restore_exception_handler();

        putenv('TFS_BRIDGE_URL');
        putenv('TFS_BRIDGE_TOKEN');
    }

    public function testServiceIsAutowirableThroughTheInterface(): void
    {
        $guard = $this->guardFromContainer();

        self::assertInstanceOf(BackendCloseGuard::class, $guard);
    }

    public function testNoBridgeThroughTheContainerYieldsNoFalseSuccess(): void
    {
        $guard = $this->guardFromContainer();

        self::assertFalse($guard->register('export:probe'));
        self::assertFalse($guard->remove('export:probe'));
    }

    public function testWiredServiceUsesTheBridgeEnvTransport(): void
    {
        putenv('TFS_BRIDGE_URL=http://127.0.0.1:1');
        putenv('TFS_BRIDGE_TOKEN=whatever');
        $guard = $this->guardFromContainer();

        // A configured bridge that cannot be reached is a transport
        // failure, never a silent "not installed": both stay
        // distinguishable from success.
        $this->expectException(TransportExceptionInterface::class);

        $guard->register('export:probe');
    }

    private function guardFromContainer(): BackendCloseGuardInterface
    {
        $this->kernel = new BackendCloseGuardTestKernel('test', false);
        $this->kernel->boot();

        $guard = $this->kernel->getContainer()->get('test.backend_close_guard');

        self::assertInstanceOf(BackendCloseGuardInterface::class, $guard);

        return $guard;
    }
}
