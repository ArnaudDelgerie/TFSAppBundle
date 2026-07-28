<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Twig;

use ArnaudDelgerie\TFSAppBundle\TFSAppBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

final class TwigGlobalTestKernel extends Kernel
{
    public function __construct(private readonly ?bool $twigGlobals = null)
    {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TFSAppBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'test' => true,
                'secret' => 'test',
            ]);

            $container->loadFromExtension('twig', []);

            if (null !== $this->twigGlobals) {
                $container->loadFromExtension('tfs_app', [
                    'twig_globals' => $this->twigGlobals,
                ]);
            }
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/tfs-app-bundle-twig-tests/cache-' . $this->configSuffix();
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/tfs-app-bundle-twig-tests/log-' . $this->configSuffix();
    }

    private function configSuffix(): string
    {
        return match ($this->twigGlobals) {
            null => 'default',
            true => 'enabled',
            false => 'disabled',
        };
    }
}
