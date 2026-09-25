<?php

namespace ArnaudDelgerie\TFSAppBundle\Tests\Bridge;

use ArnaudDelgerie\TFSAppBundle\Bridge\BackendCloseGuardInterface;
use ArnaudDelgerie\TFSAppBundle\TFSAppBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

final class BackendCloseGuardTestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TFSAppBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'test' => true,
                'secret' => 'test',
            ]);

            // Nothing in the bundle consumes the guard, so without a public
            // alias the compiler removes it before a test can ask for it.
            $container->setAlias('test.backend_close_guard', BackendCloseGuardInterface::class)->setPublic(true);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/tfs-app-bundle-guard-tests/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/tfs-app-bundle-guard-tests/log';
    }
}
