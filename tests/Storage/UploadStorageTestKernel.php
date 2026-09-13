<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Storage;

use ArnaudDelgerie\TFSAppBundle\Storage\UploadStorageInterface;
use ArnaudDelgerie\TFSAppBundle\TFSAppBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

final class UploadStorageTestKernel extends Kernel
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

            // Nothing in the bundle consumes the storage, so without a public
            // alias the compiler removes it before a test can ask for it.
            $container->setAlias('test.upload_storage', UploadStorageInterface::class)->setPublic(true);
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
