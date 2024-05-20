<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Mapper;

use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use WhiteDigital\EntityResourceMapper\Attribute\Mapping;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Exception\ClassMapperNotConfiguredException;
use WhiteDigital\EntityResourceMapper\Exception\MappingNotFoundException;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;

#[Autoconfigure(configurator: '@WhiteDigital\EntityResourceMapper\Mapper\ClassMapperConfiguratorInterface')]
class ClassMapper
{
    public function __construct(private array $map = [])
    {
    }

    /**
     * If callback closure is provided it must return TRUE in order to use the mapping.
     *
     * @param string $resourceClass Resource resource class
     * @param string $entityClass   Entity class
     */
    public function registerMapping(string $resourceClass, string $entityClass, ?string $condition = null, ?Closure $callback = null): void
    {
        $this->validate($resourceClass, $entityClass);

        $this->map[] = [
            'resource' => $resourceClass,
            'entity' => $entityClass,
            'condition' => $condition,
            'callback' => $callback,
        ];
    }

    public function getMap(): array
    {
        return $this->map;
    }

    /**
     * @return class-string<BaseEntity>
     */
    public function byResource(string $resourceClass, ?string $condition = null, array $context = []): string
    {
        try {
            return $this->lookup($resourceClass, 'resource', 'entity', $condition, $context);
        } catch (MappingNotFoundException) {
            if (null !== $result = $this->mapFromAttribute($resourceClass, $condition)) {
                return $result;
            }

            throw new RuntimeException(sprintf('%s not configured for Resource mapping. Please set up Configurator service or map classes manually.', $resourceClass));
        }
    }

    /**
     * @return class-string<BaseResource>
     */
    public function byEntity(string $entityClass, ?string $condition = null, array $context = []): string
    {
        try {
            return $this->lookup($entityClass, 'entity', 'resource', $condition, $context);
        } catch (MappingNotFoundException) {
            if (null !== $result = $this->mapFromAttribute($entityClass, $condition)) {
                return $result;
            }

            throw new ClassMapperNotConfiguredException(sprintf('%s not configured for Entity mapping. Please set up Configurator service or map classes manually.', $entityClass));
        }
    }

    private function validate(string $resourceClass, string $entityClass): void
    {
        if (!is_subclass_of($resourceClass, BaseResource::class)) {
            throw new InvalidArgumentException(sprintf('%s must extend %s', $resourceClass, BaseResource::class));
        }

        if (!is_subclass_of($entityClass, BaseEntity::class)) {
            throw new InvalidArgumentException(sprintf('%s must extend %s', $entityClass, BaseEntity::class));
        }
    }

    private function lookup(string $className, string $searchKey, string $returnKey, ?string $condition, ?array $context = []): string
    {
        $potentialMatches = [];
        foreach ($this->map as $mapping) {
            if ($mapping[$searchKey] === $className) {
                if (null !== $mapping['callback'] && true !== $mapping['callback']($context)) {
                    continue;
                }
                $potentialMatches[] = $mapping;
            }
        }
        if (1 === count($potentialMatches)) {
            return $potentialMatches[0][$returnKey];
        }
        foreach ($potentialMatches as $mapping) {
            if ($mapping['condition'] === $condition) {
                return $mapping[$returnKey];
            }
        }
        if (count($potentialMatches) > 1) {
            throw new RuntimeException("Mapping found but condition not matched for $className.");
        }
        throw new MappingNotFoundException("Mapping for class $className not found.");
    }

    private function mapFromAttribute(string $class, ?string $condition = null): ?string
    {
        try {
            $reflection = new ReflectionClass($class);
            if ([] !== $attributes = $reflection->getAttributes(Mapping::class)) {
                if (1 === count($attributes)) {
                    return $attributes[0]->newInstance()->getMappedClass();
                }

                foreach ($attributes as $attribute) {
                    if ($condition === ($new = $attribute->newInstance())->getCondition()) {
                        return $new->getMappedClass();
                    }
                }
            }
        } catch (ReflectionException) {
            return null;
        }

        return null;
    }
}
