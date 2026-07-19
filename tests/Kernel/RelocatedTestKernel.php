<?php

namespace ArnaudDelgerie\TFSAppBundle\Tests\Kernel;

use ArnaudDelgerie\TFSAppBundle\Kernel\TFSAppKernelTrait;
use ArnaudDelgerie\TFSAppBundle\TFSAppBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

final class RelocatedTestKernel extends Kernel
{
    use TFSAppKernelTrait;

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
}
