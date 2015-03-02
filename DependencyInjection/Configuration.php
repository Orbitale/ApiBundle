<?php

namespace Pierstoval\Bundle\ApiBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('pierstoval_api');

        $rootNode
            ->children()
                ->arrayNode('allowed_origins')
                    ->defaultValue(array())
                    ->prototype('scalar')->cannotBeEmpty()->isRequired()->end()
                ->end()
                ->arrayNode('services')
                    ->useAttributeAsKey('name', false)
                    ->prototype('array')
                        ->children()
                            ->scalarNode('name')->end()
                            ->scalarNode('entity')->end()
                        ->end()
                    ->end()
            ->end();

        return $treeBuilder;
    }
}
