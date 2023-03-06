<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\DependencyInjection\Traits;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

trait DefineOrmMappings
{
    protected function getOrmMappings(ContainerBuilder $builder, string $manager): array
    {
        $orm = array_merge_recursive(...$builder->getExtensionConfig('doctrine'))['orm'] ?? [];

        if ([] === ($mappings = $orm['entity_managers'][$manager]['mappings'] ?? [])) {
            $mappings = $orm['mappings'] ?? [];
        }

        return $mappings;
    }

    private function addDoctrineConfig(ContainerConfigurator $container, string $entityManager, array $mappings, string $alias, array $bundleMappings, bool $addDefault = false): void
    {
        if (false === $addDefault) {
            $mappings = [];
        }
        $mappings[$alias] = $bundleMappings;

        $container->extension('doctrine', [
            'orm' => [
                'entity_managers' => [
                    $entityManager => [
                        'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                        'mappings' => $mappings,
                    ],
                ],
            ],
        ]);
    }
}
