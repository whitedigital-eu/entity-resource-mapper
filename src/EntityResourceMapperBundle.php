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
use WhiteDigital\EntityResourceMapper\DependencyInjection\Traits\DefineOrmMappings;

use function array_is_list;
use function array_merge_recursive;
use function is_array;
use function ltrim;
use function str_starts_with;

use const PHP_INT_MAX;

class EntityResourceMapperBundle extends AbstractBundle
{
    use DefineOrmMappings;

    private const MAPPINGS = [
        'type' => 'attribute',
        'dir' => __DIR__ . '/Entity',
        'alias' => 'EntityResourceMapper',
        'prefix' => 'WhiteDigital\EntityResourceMapper\Entity',
        'is_bundle' => false,
        'mapping' => true,
    ];

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

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

        foreach (self::makeOneDimension(['whitedigital.entity_resource_mapper' => $config], onlyLast: true) as $key => $value) {
            $builder->setParameter($key, $value);
        }

        if ([] === $builder->getParameterBag()->get('whitedigital.entity_resource_mapper.maker.groups')) {
            $builder->setParameter('whitedigital.entity_resource_mapper.maker.groups', ['item', 'read', 'patch', 'write', ]);
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
                ->arrayNode('maker')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('namespaces')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('api_resource')->defaultValue('ApiResource')->end()
                                ->scalarNode('class_map_configurator')->defaultValue('Service\\Configurator')->end()
                                ->scalarNode('data_processor')->defaultValue('DataProcessor')->end()
                                ->scalarNode('data_provider')->defaultValue('DataProvider')->end()
                                ->scalarNode('entity')->defaultValue('Entity')->end()
                                ->scalarNode('root')->defaultValue('App')->end()
                            ->end()
                        ->end()
                        ->arrayNode('defaults')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('api_resource_suffix')->defaultValue('Resource')->end()
                                ->scalarNode('role_separator')->defaultValue(':')->end()
                                ->scalarNode('space')->defaultValue('_')->end()
                            ->end()
                        ->end()
                        ->arrayNode('groups')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $mapper = array_merge_recursive(...$builder->getExtensionConfig('entity_resource_mapper'));
        $mappings = $this->getOrmMappings($builder, $manager = $mapper['entity_manager'] ?? 'default');

        $this->addDoctrineConfig($container, $manager, $mappings, 'EntityResourceMapper', self::MAPPINGS, true);

        $container->extension('doctrine', [
            'orm' => [
                'entity_managers' => [
                    $manager => [
                        'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
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

    public static function makeOneDimension(array $array, string $base = '', string $separator = '.', bool $onlyLast = false, int $depth = 0, int $maxDepth = PHP_INT_MAX, array $result = []): array
    {
        if ($depth <= $maxDepth) {
            foreach ($array as $key => $value) {
                $key = ltrim(string: $base . '.' . $key, characters: '.');

                if (self::isAssociative(array: $value)) {
                    $result = self::makeOneDimension(array: $value, base: $key, separator: $separator, onlyLast: $onlyLast, depth: $depth + 1, maxDepth: $maxDepth, result: $result);

                    if ($onlyLast) {
                        continue;
                    }
                }

                $result[$key] = $value;
            }
        }

        return $result;
    }

    private static function isAssociative(mixed $array): bool
    {
        if (!is_array(value: $array) || [] === $array) {
            return false;
        }

        return !array_is_list(array: $array);
    }
}
