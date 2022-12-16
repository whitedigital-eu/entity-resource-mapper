<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Resource;

use ApiPlatform\Exception\ResourceClassNotFoundException;
use ReflectionException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\RuntimeException;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Mapper\EntityToResourceMapper;

use function is_subclass_of;

abstract class BaseResource
{
    public mixed $id = null;

    public bool $isRestricted = false; // must set this property in resource class with correct Normalization group, if GrantType::OWN used on the resource

    private static EntityToResourceMapper $entityToResourceMapper;

    /**
     * Service is set in EntityToResourceMapper constructor itself.
     */
    public static function setEntityToResourceMapper(EntityToResourceMapper $mapper): void
    {
        self::$entityToResourceMapper = $mapper;
    }

    /**
     * Factory method to create a Resource from Entity, by using EntityToResourceMapper
     * If entity is array, queryBuilder object contains BaseEntity plus SQL calculated fields for merging with final resource.
     *
     * @param BaseEntity|array<string|int, mixed> $entity
     * @param array<string, mixed>                $context // Must contain at least operation type & normalization groups
     *
     * @throws ReflectionException
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
        if (!is_subclass_of($resource, self::class)) {
            throw new RuntimeException(sprintf('Wrong type (%s instead of %s) in Resource factory.', $resource::class, static::class));
        }

        return $resource;
    }
}
