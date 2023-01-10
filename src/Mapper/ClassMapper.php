<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Mapper;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use WhiteDigital\EntityResourceMapper\Attribute\Mapping;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;

#[Autoconfigure(configurator: '@WhiteDigital\EntityResourceMapper\Mapper\ClassMapperConfiguratorInterface')]
class ClassMapper
{
    public function __construct(private array $map = [])
    {
    }

    /**
     * @param string $dtoClass    Resource resource class
     * @param string $entityClass Entity class
     */
    public function registerMapping(string $dtoClass, string $entityClass, ?string $condition = null): void
    {
        $this->validate($dtoClass, $entityClass);

        $this->map[] = [
            'dto' => $dtoClass,
            'entity' => $entityClass,
            'condition' => $condition,
        ];
    }

    public function getMap(): array
    {
        return $this->map;
    }

    /**
     * @return class-string<BaseEntity>
     */
    public function byResource(string $resourceClass, ?string $condition = null): string
    {
        if (null !== $result = $this->mapFromAttribute($resourceClass, $condition)) {
            return $result;
        }

        if (empty($this->map)) {
            throw new RuntimeException(sprintf('%s not configured for Resource mapping. Please set up Configurator service or map classes manually.', __CLASS__));
        }

        return $this->lookup($resourceClass, 'dto', 'entity', $condition);
    }

    /**
     * @return class-string<BaseResource>
     */
    public function byEntity(string $entityClass, ?string $condition = null): string
    {
        if (null !== $result = $this->mapFromAttribute($entityClass, $condition)) {
            return $result;
        }

        if (empty($this->map)) {
            throw new RuntimeException(sprintf('%s not configured for Entity mapping. Please set up Configurator service or map classes manually.', __CLASS__));
        }

        return $this->lookup($entityClass, 'entity', 'dto', $condition);
    }

    private function validate(string $dtoClass, string $entityClass): void
    {
        if (!is_subclass_of($dtoClass, BaseResource::class)) {
            throw new InvalidArgumentException(sprintf('%s must extend %s', $dtoClass, BaseResource::class));
        }

        if (!is_subclass_of($entityClass, BaseEntity::class)) {
            throw new InvalidArgumentException(sprintf('%s must extend %s', $entityClass, BaseEntity::class));
        }
    }

    private function lookup(string $className, string $searchKey, string $returnKey, ?string $condition): string
    {
        $potentialMatches = [];
        foreach ($this->map as $mapping) {
            if ($mapping[$searchKey] === $className) {
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
        throw new RuntimeException("Mapping for class $className not found.");
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
