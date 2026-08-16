<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\DependencyInjection\Compiler;

use ArnaudDelgerie\TFSAppBundle\DependencyInjection\Compiler\RegisterSqlitePragmaMiddlewarePass;
use ArnaudDelgerie\TFSAppBundle\Doctrine\SqlitePragmaMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RegisterSqlitePragmaMiddlewarePassTest extends TestCase
{
    public function testMiddlewareIsRegisteredAndTaggedWhenEnabled(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('tfsapp.sqlite_pragmas', true);

        (new RegisterSqlitePragmaMiddlewarePass())->process($container);

        self::assertTrue($container->hasDefinition(SqlitePragmaMiddleware::class));
        self::assertTrue($container->getDefinition(SqlitePragmaMiddleware::class)->hasTag('doctrine.middleware'));
    }

    public function testMiddlewareIsNotRegisteredWhenToggledOff(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('tfsapp.sqlite_pragmas', false);

        (new RegisterSqlitePragmaMiddlewarePass())->process($container);

        self::assertFalse($container->hasDefinition(SqlitePragmaMiddleware::class));
    }

    public function testMiddlewareIsNotRegisteredWhenTheParameterIsMissing(): void
    {
        $container = new ContainerBuilder();

        (new RegisterSqlitePragmaMiddlewarePass())->process($container);

        self::assertFalse($container->hasDefinition(SqlitePragmaMiddleware::class));
    }
}
