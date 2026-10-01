<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\DependencyInjection\Compiler;

use ArnaudDelgerie\TFSAppBundle\Doctrine\SqlitePragmaMiddleware;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers SqlitePragmaMiddleware tagged `doctrine.middleware`, but only when doctrine/dbal is
 * actually installed — the bundle must still boot in a Doctrine-less app — and gated on
 * `sqlite_pragmas`, mirroring RegisterTfsAppTwigGlobalPass's twig_globals gate.
 *
 * TFSAppBundle registers this pass with priority 1, above DoctrineBundle's MiddlewaresPass
 * (priority 0): MiddlewaresPass collects `doctrine.middleware`-tagged services, and at equal
 * priority passes run in bundle order — a fresh app lists this bundle after DoctrineBundle
 * (`composer require` appends it), so without the higher priority the tag lands after
 * MiddlewaresPass has collected, and the middleware never reaches the connection's chain.
 */
final class RegisterSqlitePragmaMiddlewarePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('tfsapp.sqlite_pragmas') || !$container->getParameter('tfsapp.sqlite_pragmas')) {
            return;
        }

        if (!interface_exists(\Doctrine\DBAL\Driver\Middleware::class)) {
            return;
        }

        $container->register(SqlitePragmaMiddleware::class, SqlitePragmaMiddleware::class)
            ->addTag('doctrine.middleware');
    }
}
