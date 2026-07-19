<?php

declare(strict_types=1);

use ArnaudDelgerie\TFSAppBundle\EventListener\HealthzListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set(HealthzListener::class)
        ->tag('kernel.event_listener', [
            'event' => 'kernel.request',
            'method' => 'onKernelRequest',
            'priority' => 2048,
        ]);
};
