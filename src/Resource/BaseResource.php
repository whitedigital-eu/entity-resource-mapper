<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Resource;


use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\RuntimeException;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Mapper\EntityToResourceMapper;

abstract class BaseResource
{
    public ?int $id = null;

    private static EntityToResourceMapper $entityToResourceMapper;

    /**
     * Service is set in EntityToResourceMapper constructor itself.
     * @param EntityToResourceMapper $mapper
     * @return void
     */
    public static function setEntityToResourceMapper(EntityToResourceMapper $mapper): void
    {
        self::$entityToResourceMapper = $mapper;
    }

    /**
     * Factory method to create a Resource from Entity, by using EntityToResourceMapper
     * If entity is array, queryBuilder object contains BaseEntity plus SQL calculated fields for merging with final resource.
     * @param BaseEntity|array<string|int, mixed> $entity
     * @param array<string, mixed> $context // Must contain at least operation type & normalization groups
     * @return static
     * @throws ExceptionInterface
     * @throws ResourceClassNotFoundException
     */
    public static function create(BaseEntity|array $entity, array $context): static
    {
        if (is_array($entity)) { // Same happens in ArrayNormalizer, create trait?
            $resource = self::$entityToResourceMapper->map($entity[0], $context);
            foreach ($entity as $key => $value) {
                if ($value instanceof BaseEntity) {
                    continue;
                }
                if (!property_exists($resource, $key)) {
                    throw new RuntimeException("Custom SQL property $key does not exist on " . $resource::class);
                }
                $resource->{$key} = $value;
            }
        } else {
            $resource = self::$entityToResourceMapper->map($entity, $context);
        }
        if (!($resource instanceof static)) {
            throw new \RuntimeException(sprintf("Wrong type (%s instead of %s) in Resource factory.", get_class($resource), static::class));
        }
        return $resource;
    }
}
