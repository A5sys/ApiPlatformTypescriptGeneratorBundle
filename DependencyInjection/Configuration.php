<?php

namespace A5sys\ApiPlatformTypescriptGeneratorBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('api_platform_typescript_generator');
        $rootNode
            ->children()
                ->scalarNode('path')
            ->end()
            ->arrayNode('prefix_removal')
                ->prototype('scalar')->end()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
