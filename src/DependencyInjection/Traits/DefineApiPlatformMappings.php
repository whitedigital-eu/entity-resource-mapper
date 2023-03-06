<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\DependencyInjection\Traits;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

trait DefineApiPlatformMappings
{
    protected function addApiPlatformPaths(ContainerConfigurator $container, array $bundlePaths): void
    {
        $paths = array_unique($bundlePaths);

        $container->extension('api_platform', [
            'mapping' => [
                'paths' => $paths,
            ],
        ]);
    }
}
