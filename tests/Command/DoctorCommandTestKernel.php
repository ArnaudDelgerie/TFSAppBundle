<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Command;

use ArnaudDelgerie\TFSAppBundle\TFSAppBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

final class DoctorCommandTestKernel extends Kernel
{
    public function __construct(private readonly string $projectDir)
    {
        parent::__construct('test', false);
    }

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

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function getCacheDir(): string
    {
        return $this->projectDir . '/var/cache/test';
    }

    public function getLogDir(): string
    {
        return $this->projectDir . '/var/log';
    }
}
