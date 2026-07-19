<?php

namespace ArnaudDelgerie\TFSAppBundle\Tests;

use ArnaudDelgerie\TFSAppBundle\TFSAppBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

final class TestKernel extends Kernel
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
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/tfs-app-bundle-tests/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/tfs-app-bundle-tests/log';
    }
}
