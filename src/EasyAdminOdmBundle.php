<?php

namespace AlterPHP\EasyAdminOdmBundle;

use AlterPHP\EasyAdminOdmBundle\DependencyInjection\Compiler\EasyAdminOdmConfigPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class EasyAdminOdmBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new EasyAdminOdmConfigPass());
    }
}
