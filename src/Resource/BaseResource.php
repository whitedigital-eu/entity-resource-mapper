<?php

namespace WhiteDigital\EntityResourceMapper\Resource;


use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
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
     * @param BaseEntity $entity
     * @param array<string> $context
     * @return static
     * @throws ExceptionInterface
     * @throws ResourceClassNotFoundException
     */
    public static function create(BaseEntity $entity, array $context): static
    {
        $resource = self::$entityToResourceMapper->map($entity, $context);
        if (!($resource instanceof static)) {
            throw new \RuntimeException(sprintf("Wrong type (%s instead of %s) in Resource factory.", get_class($resource), static::class));
        }
        return $resource;
    }
}