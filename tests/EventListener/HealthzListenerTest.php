<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\EventListener;

use ArnaudDelgerie\TFSAppBundle\Tests\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class HealthzListenerTest extends TestCase
{
    public function testGetHealthzReturnsOk(): void
    {
        $kernel = new TestKernel('test', false);
        $response = $kernel->handle(Request::create('/healthz', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $response->getContent());

        $kernel->shutdown();
        restore_exception_handler();
    }

    public function testHeadHealthzReturns200(): void
    {
        $kernel = new TestKernel('test', false);
        $response = $kernel->handle(Request::create('/healthz', 'HEAD'));

        self::assertSame(200, $response->getStatusCode());

        $kernel->shutdown();
        restore_exception_handler();
    }

    public function testUnrelatedPathIsNotIntercepted(): void
    {
        $kernel = new TestKernel('test', false);
        $response = $kernel->handle(Request::create('/healthz-other', 'GET'));

        self::assertSame(404, $response->getStatusCode());

        $kernel->shutdown();
        restore_exception_handler();
    }

    public function testAnotherUnrelatedPathIsNotIntercepted(): void
    {
        $kernel = new TestKernel('test', false);
        $response = $kernel->handle(Request::create('/some-other-path', 'GET'));

        self::assertSame(404, $response->getStatusCode());

        $kernel->shutdown();
        restore_exception_handler();
    }

    public function testPostHealthzIsNotIntercepted(): void
    {
        $kernel = new TestKernel('test', false);
        $response = $kernel->handle(Request::create('/healthz', 'POST'));

        self::assertSame(404, $response->getStatusCode());

        $kernel->shutdown();
        restore_exception_handler();
    }
}
