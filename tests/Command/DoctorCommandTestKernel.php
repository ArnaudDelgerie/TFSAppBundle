<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Command;

use ArnaudDelgerie\TFSAppBundle\Bridge\UpdateCheckerInterface;
use ArnaudDelgerie\TFSAppBundle\TFSAppBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Kernel;

final class DoctorCommandTestKernel extends Kernel
{
    public function __construct(
        private readonly string $projectDir,
        private readonly ?bool $sqlitePragmas = null,
        private readonly ?array $receiverTransports = null,
        private readonly ?bool $updateCheckerAvailable = null,
        private readonly ?array $sessionConfig = null,
    ) {
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
            $framework = [
                'test' => true,
                'secret' => 'test',
            ];

            if (null !== $this->sessionConfig) {
                $framework['session'] = $this->sessionConfig;
            }

            $container->loadFromExtension('framework', $framework);

            if (null !== $this->sqlitePragmas) {
                $container->loadFromExtension('tfs_app', [
                    'sqlite_pragmas' => $this->sqlitePragmas,
                ]);
            }

            if (null !== $this->receiverTransports) {
                $container->setDefinition('messenger.receiver_locator', new Definition(
                    TestReceiverLocator::class,
                    [$this->receiverTransports],
                ));
            }

            if (null !== $this->updateCheckerAvailable) {
                // This closure runs before the bundle's services.php, whose
                // UpdateCheckerInterface alias would override anything set
                // here — so repoint that alias from a compiler pass instead,
                // after the merge.
                $available = $this->updateCheckerAvailable;
                $container->addCompilerPass(new class ($available) implements CompilerPassInterface {
                    public function __construct(private readonly bool $available)
                    {
                    }

                    public function process(ContainerBuilder $container): void
                    {
                        $container->setDefinition('tests.update_checker', new Definition(
                            TestUpdateChecker::class,
                            [$this->available],
                        ));
                        $container->setAlias(UpdateCheckerInterface::class, 'tests.update_checker');
                    }
                });
            }
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
