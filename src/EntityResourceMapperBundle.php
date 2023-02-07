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
use WhiteDigital\EntityResourceMapper\Security\EmptyRoles;

use function str_starts_with;

class EntityResourceMapperBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }

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
                throw new InvalidConfigurationException('not_backed_enum');
            }

            foreach ($enum->getConstants() as $constant) {
                if (!is_array($constant) && str_starts_with($constant->name, 'ROLE_')) {
                    $roles[] = $constant->value;
                }
            }

            $builder->setParameter('whitedigital.entity_resource_mapper.roles', $roles);
        }
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition
            ->rootNode()
            ->children()
                ->scalarNode('roles_enum')->defaultValue(EmptyRoles::class)->end()
            ->end();
    }
}
