<?php

declare(strict_types=1);

use ArnaudDelgerie\TFSAppBundle\Bridge\BridgeTransport;
use ArnaudDelgerie\TFSAppBundle\Bridge\BridgeTransportFactory;
use ArnaudDelgerie\TFSAppBundle\Bridge\SecretStore;
use ArnaudDelgerie\TFSAppBundle\Bridge\SecretStoreInterface;
use ArnaudDelgerie\TFSAppBundle\Command\InitCommand;
use ArnaudDelgerie\TFSAppBundle\EventListener\HealthzListener;
use ArnaudDelgerie\TFSAppBundle\StationContext\StationContext;
use ArnaudDelgerie\TFSAppBundle\StationContext\StationContextFactory;
use ArnaudDelgerie\TFSAppBundle\StationContext\StationContextInterface;
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
        ->set(StationContextFactory::class)
        ->autowire();

    $container->services()
        ->set(StationContext::class)
        ->factory([service(StationContextFactory::class), 'create'])
        ->autowire();

    $container->services()
        ->alias(StationContextInterface::class, StationContext::class);

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
};
