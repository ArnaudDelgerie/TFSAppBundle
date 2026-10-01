<?php

namespace ArnaudDelgerie\TFSAppBundle\Tests;

use ArnaudDelgerie\TFSAppBundle\DependencyInjection\Compiler\RegisterSqlitePragmaMiddlewarePass;
use ArnaudDelgerie\TFSAppBundle\TFSAppBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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

    public function testPragmaMiddlewarePassRunsBeforeDoctrineMiddlewaresPass(): void
    {
        $container = new ContainerBuilder();
        // DoctrineBundle is declared before this bundle in a fresh app's bundles.php, so its
        // MiddlewaresPass (default priority 0) is registered first; this pass stands in for it.
        $middlewaresPass = $this->createMock(CompilerPassInterface::class);
        $container->addCompilerPass($middlewaresPass);

        (new TFSAppBundle())->build($container);

        $passes = array_map(
            static fn (CompilerPassInterface $pass): string => $pass::class,
            $container->getCompilerPassConfig()->getBeforeOptimizationPasses(),
        );

        $pragmaPassPosition = array_search(RegisterSqlitePragmaMiddlewarePass::class, $passes, true);
        $middlewaresPassPosition = array_search($middlewaresPass::class, $passes, true);

        self::assertIsInt($pragmaPassPosition);
        self::assertIsInt($middlewaresPassPosition);
        self::assertLessThan($middlewaresPassPosition, $pragmaPassPosition);
    }
}
