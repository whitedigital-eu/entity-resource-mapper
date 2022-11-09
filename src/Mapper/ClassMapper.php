<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Mapper;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;

#[Autoconfigure(configurator: '@WhiteDigital\EntityResourceMapper\Mapper\ClassMapperConfiguratorInterface')]
class ClassMapper
{
    public function __construct(private array $map = [])
    {
    }

    /**
     * @param string $dtoClass Resource resource class
     * @param string $entityClass Entity class
     * @param string|null $condition
     * @return void
     */
    public function registerMapping(string $dtoClass, string $entityClass, ?string $condition = null): void
    {
        //TODO validate if dto class is based on BaseResource and entity class based on BaseEntity
        $this->map[] = [
            'dto' => $dtoClass,
            'entity' => $entityClass,
            'condition' => $condition
        ];
    }

    /**
     * @param string $resourceClass
     * @param string|null $condition
     * @return class-string<BaseEntity>
     */
    public function byResource(string $resourceClass, ?string $condition = null): string
    {
        if (empty($this->map)) {
            throw new \RuntimeException(sprintf('%s not configured for Resource mapping. Please set up Configurator service or map classes manually.', __CLASS__));
        }
        return $this->lookup($resourceClass, 'dto', 'entity', $condition);
    }

    /**
     * @param string $entityClass
     * @param string|null $condition
     * @return class-string<BaseResource>
     */
    public function byEntity(string $entityClass, ?string $condition = null): string
    {
        if (empty($this->map)) {
            throw new \RuntimeException(sprintf('%s not configured for Entity mapping. Please set up Configurator service or map classes manually.', __CLASS__));
        }
        return $this->lookup($entityClass, 'entity', 'dto', $condition);
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
            throw new \RuntimeException("Mapping found but condition not matched for {$className}.");
        }
        throw new \RuntimeException("Mapping for class {$className} not found.");
    }
}
