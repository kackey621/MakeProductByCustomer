<?php

namespace Plugin\MPBC43;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MPBCBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        
        // Register entity mapping for this plugin
        $mappings = [
            realpath(__DIR__ . '/Entity') => 'Plugin\MPBC43\Entity',
        ];
        
        $container->addCompilerPass(
            DoctrineOrmMappingsPass::createAnnotationMappingDriver(
                $mappings,
                ['Plugin\MPBC43\Entity']
            )
        );
    }
    
    public function getPath(): string
    {
        return __DIR__;
    }
}
