<?php

namespace AlterPHP\EasyAdminMongoOdmBundle;

use AlterPHP\EasyAdminMongoOdmBundle\DependencyInjection\Compiler\EasyAdminMongoOdmConfigPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EasyAdminMongoOdmBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new EasyAdminMongoOdmConfigPass());
    }
}
