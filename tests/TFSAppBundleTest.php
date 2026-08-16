<?php

namespace ArnaudDelgerie\TFSAppBundle\Tests;

use ArnaudDelgerie\TFSAppBundle\TFSAppBundle;
use PHPUnit\Framework\TestCase;

final class TFSAppBundleTest extends TestCase
{
    public function testKernelBootsWithBundleRegistered(): void
    {
        $kernel = new TestKernel('test', false);
        $kernel->boot();

        $bundleClasses = array_map(static fn ($bundle) => $bundle::class, $kernel->getBundles());

        self::assertContains(TFSAppBundle::class, $bundleClasses);

        $kernel->shutdown();
        restore_exception_handler();
    }

    public function testSqlitePragmasDefaultsToEnabled(): void
    {
        $kernel = new TestKernel('test', false);
        $kernel->boot();

        self::assertTrue($kernel->getContainer()->getParameter('tfsapp.sqlite_pragmas'));

        $kernel->shutdown();
        restore_exception_handler();
    }
}
