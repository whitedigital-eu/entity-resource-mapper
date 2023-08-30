<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Maker;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\OneToMany;
use Exception;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Annotation\Ignore;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\EntityResourceMapperBundle;
use WhiteDigital\EntityResourceMapper\Exception\ClassMapperNotConfiguredException;
use WhiteDigital\EntityResourceMapper\Mapper\ClassMapper;
use WhiteDigital\EntityResourceMapper\UTCDateTimeImmutable;

use function array_column;
use function array_merge;
use function array_merge_recursive;
use function array_multisort;
use function array_unique;
use function class_exists;
use function dirname;
use function end;
use function explode;
use function getcwd;
use function in_array;
use function is_a;
use function is_array;
use function is_subclass_of;
use function preg_replace;
use function sort;
use function sprintf;
use function str_contains;
use function str_replace;
use function strtolower;
use function unlink;

use const SORT_ASC;
use const SORT_REGULAR;

/**
 * @internal
 */
class MakeApiResource extends AbstractMaker
{
    public const F_ARRAY = 'array';
    public const F_BOOL = 'bool';
    public const F_DATE = 'date';
    public const F_ENUM = 'enum';
    public const F_NUMERIC = 'numeric';
    public const F_RANGE = 'range';
    public const F_SEARCH = 'search';

    private const FILTER_TYPES = [self::F_ARRAY, self::F_BOOL, self::F_DATE, self::F_ENUM, self::F_NUMERIC, self::F_RANGE, self::F_SEARCH, ];

    public function __construct(private readonly ClassMapper $mapper, private readonly ParameterBagInterface $bag)
    {
    }

    public static function getCommandName(): string
    {
        return 'make:api-resource';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a new ApiResource, DataProvider and DataProcessor. And regenerates ClassMapperConfigurator';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('entity', InputArgument::OPTIONAL, 'The name of the entity class (e.g. <fg=yellow>User</>) for which to create resource')
            ->addOption('no-properties', null, InputOption::VALUE_NONE, 'Use this option to disable resource property generation')
            ->addOption('delete-if-exists', null, InputOption::VALUE_NONE, 'Use this option to delete existing ApiResource, DataProvider and DataProcessor before generation')
            ->addOption('level', null, InputOption::VALUE_OPTIONAL, 'How deep generate filters', 1)
            ->setHelp('
<info>php %command.full_name% User</info>

If the argument is missing, the command will ask for the entity class name interactively.
            ');

        foreach (self::FILTER_TYPES as $type) {
            $command->addOption(sprintf('exclude-%s', $type), null, InputOption::VALUE_NONE, sprintf('Exclude %s filters', $type));
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    /**
     * @throws Exception
     */
    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $entityName = $input->getArgument('entity');
        $ns = '\\';
        $wd = 'whitedigital.entity_resource_mapper.maker';

        $entity = $generator->createClassNameDetails($entityName, $this->bag->get($wd . '.namespaces.entity') . $ns);

        if (!is_subclass_of($entity->getFullName(), BaseEntity::class)) {
            $io->caution(sprintf('%s does not exist or it does not extend BaseEntity', $entity->getShortName()));

            exit;
        }

        $provider = $generator->createClassNameDetails($entityName, ($dpn = $this->bag->get($wd . '.namespaces.data_provider')) . $ns, $dpn);

        if (class_exists($provider->getFullName())) {
            if ($input->getOption('delete-if-exists')) {
                unlink($this->fixPath($provider->getFullName()));
            } else {
                $io->caution(sprintf('Provaider %s already exists', $provider->getShortName()));

                exit;
            }
        }

        $resource = $generator->createClassNameDetails($entityName, $this->bag->get($wd . '.namespaces.api_resource') . $ns, $this->bag->get($wd . '.defaults.api_resource_suffix'));

        if (class_exists($resource->getFullName()) || class_exists($resource->getFullName(), false)) {
            if ($input->getOption('delete-if-exists')) {
                unlink($this->fixPath($resource->getFullName()));
            } else {
                $io->caution(sprintf('ApiResource %s already exists', $resource->getShortName()));

                exit;
            }
        }

        $processor = $generator->createClassNameDetails($entityName, ($pn = $this->bag->get($wd . '.namespaces.data_processor')) . $ns, $pn);

        if (class_exists($processor->getFullName())) {
            if ($input->getOption('delete-if-exists')) {
                unlink($this->fixPath($processor->getFullName()));
            } else {
                $io->caution(sprintf('Processor %s already exists', $processor->getShortName()));

                exit;
            }
        }

        $generator->generateClass(
            $provider->getFullName(),
            dirname(__DIR__, 2) . '/skeleton/DataProvider.tpl.php',
            [
                'resource' => $resource,
            ],
        );

        $generator->generateClass(
            $processor->getFullName(),
            dirname(__DIR__, 2) . '/skeleton/DataProcessor.tpl.php',
            [
                'entity' => $entity,
                'resource' => $resource,
            ],
        );

        $generator->generateClass(
            $resource->getFullName(),
            dirname(__DIR__, 2) . '/skeleton/ApiResource.tpl.php',
            [
                'entity_name' => $entityName,
                'prefix' => $this->toSnakeCase($entityName, $this->bag->get($wd . '.defaults.space')),
                'processor' => $processor,
                'provider' => $provider,
                'separator' => $this->bag->get($wd . '.defaults.role_separator'),
                'groups' => $groups = $this->bag->get($wd . '.groups'),
            ],
        );

        $generator->writeChanges();

        $mapping = $uses = [];
        foreach ($this->mapper->getMap() as $map) {
            if (null !== ($condition = $map['condition'])) {
                $class = $generator->createClassNameDetails($ns . $condition, '');
                if (class_exists($class->getFullName())) {
                    $uses[] = $class->getFullName();
                    $condition = $class->getShortName() . '::class';
                } else {
                    $condition = sprintf("'%s'", $condition);
                }
            }

            $mapping[] = [
                'entity' => ($e = $generator->createClassNameDetails($ns . $map['entity'], ''))->getShortName() . '::class',
                'resource' => ($d = $generator->createClassNameDetails($ns . $map['resource'], ''))->getShortName() . '::class',
                'condition' => $condition,
                'callback' => $map['callback'],
            ];
            $uses[] = $e->getFullName();
            $uses[] = $d->getFullName();
        }

        $uses[] = $entity->getFullName();
        $uses[] = $resource->getFullName();

        $mapping[] = [
            'entity' => $entity->getShortName() . '::class',
            'resource' => $resource->getShortName() . '::class',
            'condition' => null,
            'callback' => null,
        ];

        $mapping = array_unique($mapping, SORT_REGULAR);
        $uses = array_unique($uses, SORT_REGULAR);
        sort($uses);
        array_multisort(array_column($mapping, 'resource'), SORT_ASC, $mapping);

        $configurator = $generator->createClassNameDetails('ClassMapperConfigurator', $this->bag->get($wd . '.namespaces.class_map_configurator'));
        if (class_exists($configurator->getFullName())) {
            unlink($this->fixPath($configurator->getFullName()));
        }

        $generator->generateClass(
            $configurator->getFullName(),
            dirname(__DIR__, 2) . '/skeleton/ClassMapperConfigurator.tpl.php',
            [
                'uses' => $uses,
                'mapping' => $mapping,
            ],
        );

        $generator->writeChanges();

        if ($input->getOption('no-properties')) {
            $this->writeSuccessMessage($io);

            return;
        }

        $entityRef = new ReflectionClass($entity->getFullName());
        $excluded = ['id', 'createdAt', 'updatedAt', ];
        $properties = [];
        $resourceMapping = [];
        foreach ($entityRef->getProperties() as $property) {
            if (!in_array($property->getName(), $excluded, true)) {
                $prop = $property->getType();
                $header = null;
                if ($prop->isBuiltin()) {
                    $type = $prop->getName();
                } else {
                    $ref = new ReflectionClass($prop->getName());
                    if (Collection::class === $prop->getName()) {
                        $type = Type::BUILTIN_TYPE_ARRAY;
                        $orm = array_merge_recursive($property->getAttributes(ManyToMany::class), $property->getAttributes(OneToMany::class));
                        if ([] !== $orm) {
                            $header = $this->byEntity($orm[0]->getArguments()['targetEntity']);
                            $resourceMapping[] = $header;
                            $parts = explode('\\', $header);
                            $header = end($parts);
                        }
                    } elseif (is_a($prop->getName(), DateTimeInterface::class, true)) {
                        if (!is_a($prop->getName(), DateTimeImmutable::class, true)) {
                            throw new InvalidArgumentException(sprintf('Date/DateTime properties must be %s, property "%s" is not', DateTimeImmutable::class, $property->getName()));
                        }

                        $type = match (is_a($prop->getName(), UTCDateTimeImmutable::class, true)) {
                            true => (new ReflectionClass(UTCDateTimeImmutable::class))->getShortName(),
                            false => DateTimeImmutable::class,
                        };
                    } elseif (File::class === $prop->getName()) {
                        $type = File::class;
                        $resourceMapping[] = File::class;
                        $parts = explode('\\', $type);
                        $type = end($parts);
                    } elseif ($ref->implementsInterface(BackedEnum::class)) {
                        $type = $prop->getName();
                        $resourceMapping[] = $prop->getName();
                        $parts = explode('\\', $type);
                        $type = end($parts);
                    } else {
                        $type = $this->byEntity($prop->getName());
                        $resourceMapping[] = $type;
                        $parts = explode('\\', $type);
                        $type = end($parts);
                    }
                }

                $properties[$property->getName()] = [
                    'type' => $type,
                    'header' => $header,
                ];
            }
        }

        $newResource = $generator->createClassNameDetails($entityName, $this->bag->get($wd . '.namespaces.api_resource') . $ns, $this->bag->get($wd . '.defaults.api_resource_suffix'));

        $enums = [];
        $filters = $this->getFilters($entityRef, (int) $input->getOption('level'));
        $map = EntityResourceMapperBundle::makeOneDimension($filters, onlyLast: true);
        foreach ($map as $item => $values) {
            foreach ($values as $value) {
                if (is_array($value)) {
                    $ref = new ReflectionClass($value['type']);
                    $enums[strtr($item, ['enum' => $value['name']])] = $ref->getShortName() . '::class';
                    $resourceMapping[] = $value['type'];
                }
            }
        }
        $flattened = $this->flattenFilterMap($map);
        $flattened[self::F_RANGE] = $flattened[self::F_NUMERIC] ?? [];
        foreach (self::FILTER_TYPES as $type) {
            if ($input->getOption(sprintf('exclude-%s', $type))) {
                unset($flattened[$type]);
            }
        }

        @unlink($this->fixPath($newResource->getFullName()));
        $resourceMapping = array_unique($resourceMapping);

        $json = [];
        foreach ($flattened[self::F_ARRAY] ?? [] as $item) {
            $json[] = $item . "->>'order'";
        }

        $order = array_merge($flattened[self::F_NUMERIC] ?? [], $flattened[self::F_SEARCH] ?? [], $flattened[self::F_DATE] ?? [], $json);
        $generator->generateClass(
            $newResource->getFullName(),
            dirname(__DIR__, 2) . '/skeleton/ApiResourceExtended.tpl.php',
            [
                'entity_name' => $entityName,
                'prefix' => $this->toSnakeCase($entityName, $this->bag->get($wd . '.defaults.space')),
                'processor' => $processor,
                'provider' => $provider,
                'separator' => $this->bag->get($wd . '.defaults.role_separator'),
                'groups' => $groups,
                'properties' => $properties,
                'uses' => $resourceMapping,
                'filters' => $flattened,
                'enums' => $enums,
                'order' => $order,
            ],
        );

        $generator->writeChanges();

        $this->writeSuccessMessage($io);
    }

    /**
     * @throws ReflectionException
     */
    private function getFilters(ReflectionClass $entityRef, int $level = 0): array
    {
        if (0 === $level) {
            return [];
        }

        $result = [];
        foreach ($entityRef->getProperties() as $property) {
            if ([] !== $property->getAttributes(Ignore::class)) {
                continue;
            }

            $name = $property->getName();
            $prop = $property->getType();
            if ($prop->isBuiltin()) {
                $type = $prop->getName();
                if ('mixed' === $type && 'id' === $property->getName()) {
                    $type = Type::BUILTIN_TYPE_INT;
                }

                match ($type) {
                    Type::BUILTIN_TYPE_STRING, 'mixed' => $result[self::F_SEARCH][] = $name,
                    Type::BUILTIN_TYPE_INT, Type::BUILTIN_TYPE_FLOAT => $result[self::F_NUMERIC][] = $name,
                    Type::BUILTIN_TYPE_BOOL => $result[self::F_BOOL][] = $name,
                    Type::BUILTIN_TYPE_ARRAY => $result[self::F_ARRAY][] = $name,
                    default => null,
                };
            } else {
                $ref = new ReflectionClass($prop->getName());
                if (is_a($prop->getName(), DateTimeInterface::class, true)) {
                    $result[self::F_DATE][] = $name;
                    continue;
                }

                if ($ref->implementsInterface(BackedEnum::class)) {
                    $result[self::F_ENUM][] = ['name' => $name, 'type' => $ref->getName()];
                    continue;
                }

                if (Collection::class === $prop->getName()) {
                    $orm = array_merge_recursive($property->getAttributes(ManyToMany::class), $property->getAttributes(OneToMany::class));
                    if ([] !== $orm && 0 < $level - 1) {
                        $colRef = new ReflectionClass($this->byEntity($orm[0]->getArguments()['targetEntity']));
                        $result[$property->getName()] = $this->getFilters($colRef, $level - 1);
                    }
                    continue;
                }

                if (0 < $level - 1) {
                    $result[$property->getName()] = $this->getFilters($ref, $level - 1);
                }
            }
        }

        return $result;
    }

    private function flattenFilterMap(array $full): array
    {
        foreach ($full as $item => $values) {
            if (str_contains($item, '.')) {
                $parts = [];
                preg_match("/(^.*)\.(.*?)$/", $item, $parts);
                unset($full[$item]);
                foreach ($values as $value) {
                    if (is_array($value)) {
                        $value = $value['name'];
                    }

                    $full[$parts[2]][] = $parts[1] . '.' . $value;
                }
            } else {
                foreach ($values as $id => $value) {
                    if (is_array($value)) {
                        $value = $value['name'];
                    }

                    $full[$item][$id] = $value;
                }
            }
        }

        return $full;
    }

    private function fixPath(string $className): string
    {
        $className = preg_replace('#' . $this->bag->get('whitedigital.entity_resource_mapper.maker.namespaces.root') . '\\\\#', '', $className, 1);
        $className = str_replace('\\', '/', $className);

        return getcwd() . '/src/' . $className . '.php';
    }

    private function toSnakeCase(string $string, string $space): string
    {
        $string = preg_replace(pattern: [
            '#([A-Z\d]+)([A-Z][a-z])#',
            '#([a-z\d])([A-Z])#',
        ], replacement: '\1_\2', subject: $string);

        return strtolower(string: str_replace(search: '-', replace: $space, subject: (string) $string));
    }

    private function byEntity(string $class): string
    {
        try {
            return $this->mapper->byEntity($class);
        } catch (ClassMapperNotConfiguredException $exception) {
            throw new InvalidConfigurationException(sprintf('Unable to find mapped resource for class "%s"', $class), previous: $exception);
        }
    }
}
