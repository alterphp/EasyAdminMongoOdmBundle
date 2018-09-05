<?php

namespace AlterPHP\EasyAdminOdmBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class EasyAdminOdmConfigPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $configPasses = $this->findAndSortTaggedServices('easyadmin_odm.config_pass', $container);
        $definition = $container->getDefinition('easyadmin_odm.config.manager');

        foreach ($configPasses as $service) {
            $definition->addMethodCall('addConfigPass', array($service));
        }
    }
}
