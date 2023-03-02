<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper;

use BackedEnum;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use WhiteDigital\EntityResourceMapper\DBAL\Functions\JsonArrayLength;
use WhiteDigital\EntityResourceMapper\DBAL\Functions\JsonbPathExists;
use WhiteDigital\EntityResourceMapper\DBAL\Functions\JsonContains;
use WhiteDigital\EntityResourceMapper\DBAL\Functions\JsonGetText;
use WhiteDigital\EntityResourceMapper\DBAL\Types\UTCDateTimeImmutableType;
use WhiteDigital\EntityResourceMapper\DBAL\Types\UTCDateTimeType;

class EntityResourceMapperBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');

        $enumClass = $config['roles_enum'] ?? null;

        $roles = [];
        if (null !== $enumClass) {
            try {
                $enum = (new ReflectionClass($enumClass));
            } catch (ReflectionException $exception) {
                throw new InvalidConfigurationException($exception->getMessage(), previous: $exception);
            }

            if (!$enum->implementsInterface(BackedEnum::class)) {
                throw new InvalidConfigurationException('"roles_enum" must be backed enum');
            }

            foreach ($enum->getConstants() as $constant) {
                if (!is_array($constant) && str_starts_with($constant->name, 'ROLE_')) {
                    $roles[] = $constant->value;
                }
            }
        }

        $builder->setParameter('whitedigital.entity_resource_mapper.roles', $roles);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition
            ->rootNode()
            ->children()
                ->scalarNode('roles_enum')->defaultNull()->end()
                ->scalarNode('entity_manager')->defaultValue('default')->end()
            ->end();
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $mapper = array_merge_recursive(...$builder->getExtensionConfig('entity_resource_mapper') ?? []);
        $mappings['EntityResourceMapper'] = [
            'type' => 'attribute',
            'dir' => __DIR__ . '/Entity',
            'alias' => 'EntityResourceMapper',
            'prefix' => 'WhiteDigital\EntityResourceMapper\Entity',
            'is_bundle' => false,
            'mapping' => true,
        ];

        $container->extension('doctrine', [
            'orm' => [
                'entity_managers' => [
                    $mapper['entity_manager'] ?? 'default' => [
                        'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                        'mappings' => $mappings,
                        'dql' => [
                            'string_functions' => [
                                'JSONB_PATH_EXISTS' => JsonbPathExists::class,
                                'JSON_GET_TEXT' => JsonGetText::class,
                                'JSON_CONTAINS' => JsonContains::class,
                                'JSON_ARRAY_LENGTH' => JsonArrayLength::class,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $container->extension('doctrine', [
            'dbal' => [
                'types' => [
                    'date' => UTCDateTimeType::class,
                    'datetime' => UTCDateTimeType::class,
                    'date_immutable' => UTCDateTimeImmutableType::class,
                    'datetime_immutable' => UTCDateTimeImmutableType::class,
                ],
            ],
        ]);
    }
}
