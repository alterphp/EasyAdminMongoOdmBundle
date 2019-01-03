<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('easy_admin_mongo_odm');

        $this->addDocumentsSection($treeBuilder->getRootNode());

        return $treeBuilder;
    }

    private function addDocumentsSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
                ->arrayNode('documents')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name', false)
                    ->defaultValue(array())
                    ->info('The list of documents to manage in the administration zone.')
                    ->prototype('variable')
                ->end()
            ->end()
        ;
    }
}
