<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\DependencyInjection\Compiler;

use AlterPHP\EasyAdminMongoOdmBundle\Configuration\ConfigManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class EasyAdminMongoOdmConfigPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $configPasses = $this->findAndSortTaggedServices('easyadmin_mongo_odm.config_pass', $container);
        $definition = $container->getDefinition(ConfigManager::class);

        foreach ($configPasses as $service) {
            $definition->addMethodCall('addConfigPass', [$service]);
        }
    }
}
