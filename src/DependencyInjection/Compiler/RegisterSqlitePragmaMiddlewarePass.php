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
