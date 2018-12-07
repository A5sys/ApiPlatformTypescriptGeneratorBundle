<?php

namespace A5sys\ApiPlatformTypescriptGeneratorBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

class ApiPlatformTypescriptGeneratorExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $configuration = $this->processConfiguration($configuration, $configs);
        $path = $configuration['path'];
        $container->setParameter('api_platform_typescript_generator.path', $path);

        $prefixRemoval = $configuration['prefix_removal'];
        $container->setParameter('api_platform_typescript_generator.prefix_removal', $prefixRemoval);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }
}
