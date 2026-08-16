<?php

namespace ArnaudDelgerie\TFSAppBundle;

use ArnaudDelgerie\TFSAppBundle\DependencyInjection\Compiler\RegisterSqlitePragmaMiddlewarePass;
use ArnaudDelgerie\TFSAppBundle\DependencyInjection\Compiler\RegisterTfsAppTwigGlobalPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class TFSAppBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('twig_globals')->defaultTrue()->end()
                ->booleanNode('sqlite_pragmas')->defaultTrue()->end()
            ->end();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterTfsAppTwigGlobalPass());
        $container->addCompilerPass(new RegisterSqlitePragmaMiddlewarePass());
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $builder->setParameter('tfsapp.twig_globals', $config['twig_globals']);
        $builder->setParameter('tfsapp.sqlite_pragmas', $config['sqlite_pragmas']);
    }
}
