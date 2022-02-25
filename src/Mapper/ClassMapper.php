<?php

namespace WhiteDigital\EntityDtoMapper\Mapper;

use WhiteDigital\EntityDtoMapper\Dto\BaseDto;
use WhiteDigital\EntityDtoMapper\Entity\BaseEntity;

class ClassMapper
{
    private array $map;


    public function __construct()
    {
        $this->map = [];
    }

    /**
     * @param string $dtoClass Dto resource class
     * @param string $entityClass Entity class
     * @param string|null $condition
     * @return void
     */
    public function registerMapping(string $dtoClass, string $entityClass, string $condition = null): void
    {
        //TODO validate if dto class is based on BaseDto and entity class based on BaseEntity
        $this->map[] = [
            'dto' => $dtoClass,
            'entity' => $entityClass,
            'condition' => $condition
        ];
    }

    /**
     * @param string $dtoClass
     * @param string|null $condition
     * @return class-string<BaseEntity>
     */
    public function byDto(string $dtoClass, string $condition = null): string
    {
        if (empty($this->map)) {
            throw new \RuntimeException(sprintf('%s not configured for Dto mapping. Please set up Configurator service or map classes manually.', __CLASS__));
        }
        return $this->lookup($dtoClass, 'dto', 'entity', $condition);
    }

    /**
     * @param string $entityClass
     * @param string|null $condition
     * @return class-string<BaseDto>
     */
    public function byEntity(string $entityClass, string $condition = null): string
    {
        if (empty($this->map)) {
            throw new \RuntimeException(sprintf('%s not configured for Entity mapping. Please set up Configurator service or map classes manually.', __CLASS__));
        }
        return $this->lookup($entityClass, 'entity', 'dto', $condition);
    }

    private function lookup(string $className, string $searchkey, string $returnKey, $condition): string
    {
        $potentialMatches = [];
        foreach ($this->map as $mapping) {
            if ($mapping[$searchkey] === $className) {
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
