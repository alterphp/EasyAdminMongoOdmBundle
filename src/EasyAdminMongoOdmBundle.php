<?php

namespace AlterPHP\EasyAdminMongoOdmBundle;

use AlterPHP\EasyAdminMongoOdmBundle\DependencyInjection\Compiler\EasyAdminMongoOdmConfigPass;
use AlterPHP\EasyAdminMongoOdmBundle\DependencyInjection\Compiler\TwigPathPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EasyAdminMongoOdmBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new TwigPathPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $container->addCompilerPass(new EasyAdminMongoOdmConfigPass());
    }
}
