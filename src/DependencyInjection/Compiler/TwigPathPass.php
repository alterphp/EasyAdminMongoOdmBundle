<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\DependencyInjection\Compiler;

use AlterPHP\EasyAdminMongoOdmBundle\EasyAdminMongoOdmBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class TwigPathPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $twigLoaderFilesystemId = $container->getAlias('twig.loader')->__toString();
        $twigLoaderFilesystemDefinition = $container->getDefinition($twigLoaderFilesystemId);

        $easyAdminBundleRefl = new \ReflectionClass(EasyAdminBundle::class);
        if ($easyAdminBundleRefl->isUserDefined()) {
            $nativeEasyAdminBundlePath = dirname((string) $easyAdminBundleRefl->getFileName());
            $nativeEasyAdminTwigPath = $nativeEasyAdminBundlePath.'/Resources/views';
            // Defines a namespace from native EasyAdmin templates
            $twigLoaderFilesystemDefinition->addMethodCall(
                'addPath',
                array($nativeEasyAdminTwigPath, 'EasyAdminMongoOdm')
            );
        }
    }
}
