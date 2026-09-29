<?php

declare(strict_types=1);

use ArnaudDelgerie\TFSAppBundle\Bridge\BackendCloseGuard;
use ArnaudDelgerie\TFSAppBundle\Bridge\BackendCloseGuardInterface;
use ArnaudDelgerie\TFSAppBundle\Bridge\BridgeTransport;
use ArnaudDelgerie\TFSAppBundle\Bridge\BridgeTransportFactory;
use ArnaudDelgerie\TFSAppBundle\Bridge\SecretStore;
use ArnaudDelgerie\TFSAppBundle\Bridge\SecretStoreInterface;
use ArnaudDelgerie\TFSAppBundle\Bridge\UpdateChecker;
use ArnaudDelgerie\TFSAppBundle\Bridge\UpdateCheckerInterface;
use ArnaudDelgerie\TFSAppBundle\Command\DoctorCommand;
use ArnaudDelgerie\TFSAppBundle\Command\InitCommand;
use ArnaudDelgerie\TFSAppBundle\EventListener\HealthzListener;
use ArnaudDelgerie\TFSAppBundle\HubContext\HubContext;
use ArnaudDelgerie\TFSAppBundle\HubContext\HubContextFactory;
use ArnaudDelgerie\TFSAppBundle\HubContext\HubContextInterface;
use ArnaudDelgerie\TFSAppBundle\Storage\UploadStorage;
use ArnaudDelgerie\TFSAppBundle\Storage\UploadStorageInterface;
use ArnaudDelgerie\TFSAppBundle\Twig\TfsAppTwigGlobal;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set(HealthzListener::class)
        ->tag('kernel.event_listener', [
            'event' => 'kernel.request',
            'method' => 'onKernelRequest',
            'priority' => 2048,
        ]);

    $container->services()
        ->set(InitCommand::class)
        ->autowire()
        ->tag('console.command');

    $container->services()
        ->set(DoctorCommand::class)
        ->autowire()
        ->arg('$receiverLocator', service('messenger.receiver_locator')->nullOnInvalid())
        ->tag('console.command');

    $container->services()
        ->set(HubContextFactory::class)
        ->autowire();

    $container->services()
        ->set(HubContext::class)
        ->factory([service(HubContextFactory::class), 'create'])
        ->autowire();

    $container->services()
        ->alias(HubContextInterface::class, HubContext::class);

    $container->services()
        ->set(BridgeTransportFactory::class)
        ->autowire();

    $container->services()
        ->set(BridgeTransport::class)
        ->factory([service(BridgeTransportFactory::class), 'create'])
        ->autowire();

    $container->services()
        ->set(SecretStore::class)
        ->autowire();

    $container->services()
        ->alias(SecretStoreInterface::class, SecretStore::class);

    $container->services()
        ->set(UpdateChecker::class)
        ->autowire();

    $container->services()
        ->alias(UpdateCheckerInterface::class, UpdateChecker::class);

    $container->services()
        ->set(BackendCloseGuard::class)
        ->autowire();

    $container->services()
        ->alias(BackendCloseGuardInterface::class, BackendCloseGuard::class);

    $container->services()
        ->set(TfsAppTwigGlobal::class)
        ->autowire();

    // Outside the hub (symfony server:start, PHPUnit, CI) APP_UPLOAD_DIR is
    // absent; fall back to the same var/uploads the hub's dev mode injects.
    $container->parameters()
        ->set('tfsapp.upload_dir_fallback', '%kernel.project_dir%/var/uploads');

    $container->services()
        ->set(UploadStorage::class)
        ->arg('$root', '%env(default:tfsapp.upload_dir_fallback:APP_UPLOAD_DIR)%');

    $container->services()
        ->alias(UploadStorageInterface::class, UploadStorage::class);
};
