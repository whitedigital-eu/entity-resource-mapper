<?php

namespace WhiteDigital\EntityResourceMapper\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class EntityResourceMapperExtension extends Extension
{
    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
      $loader = new YamlFileLoader(
          $container,
          new FileLocator(__DIR__.'/../Resources/config')
      );
      $loader->load('services.yaml');
    }
}
