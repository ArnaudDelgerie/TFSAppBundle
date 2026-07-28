<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\DependencyInjection\Compiler;

use ArnaudDelgerie\TFSAppBundle\Twig\TfsAppTwigGlobal;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires the `tfsapp` Twig global onto the `twig` service definition, but only
 * when TwigBundle actually registered one — the bundle must still boot in a
 * Twig-less app.
 */
final class RegisterTfsAppTwigGlobalPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('tfsapp.twig_globals') || !$container->getParameter('tfsapp.twig_globals')) {
            return;
        }

        if (!$container->has('twig') || !$container->has(TfsAppTwigGlobal::class)) {
            return;
        }

        $container->getDefinition('twig')
            ->addMethodCall('addGlobal', ['tfsapp', new Reference(TfsAppTwigGlobal::class)]);
    }
}
