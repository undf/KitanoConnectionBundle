<?php

namespace Kitano\ConnectionBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
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
        $rootNode = $treeBuilder->root('kitano_connection');

        $this->addPersistenceSection($rootNode);

        return $treeBuilder;
    }

    /**
     * Parses the kitano_connection.persistence config section
     * Example for yaml driver:
     * kitano_connection:
     *     persistence:
     *         type:
     *
     * @param  ArrayNodeDefinition $node
     * @return void
     */
    private function addPersistenceSection(ArrayNodeDefinition $node)
    {
        $supportedDrivers = array('doctrine_orm', 'doctrine_mongodb', 'array', 'custom');

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('persistence')
                ->cannotBeEmpty()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('type')
                            ->defaultValue('doctrine_orm')
                            ->validate()
                                ->ifNotInArray($supportedDrivers)
                                ->thenInvalid('The driver %s is not supported. Please choose one of '.json_encode($supportedDrivers))
                            ->end()
                            ->cannotBeOverwritten()
                            ->isRequired()
                            ->cannotBeEmpty()
                        ->end()
                        ->arrayNode('managed_class')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('connection')->defaultValue('Undf\ConnectBundle\Entity\Connection')->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(function($v) {
                    return 'doctrine_orm' === $v['persistence']['type']
                        && (!isset($v['persistence']['managed_class']) ||
                            !isset($v['persistence']['managed_class']['connection']));
                })
                ->thenInvalid('You need to specify a "managed_class" when using doctrine_orm persistence type.')
            ->end()
        ;
    }
}
