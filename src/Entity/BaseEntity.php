<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Entity;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\MappedSuperclass;
use Exception;
use RuntimeException;
use WhiteDigital\EntityResourceMapper\Mapper\ResourceToEntityMapper;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;
use WhiteDigital\EntityResourceMapper\UTCDateTimeImmutable;

#[ORM\HasLifecycleCallbacks]
#[MappedSuperclass]
abstract class BaseEntity
{
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected ?UTCDateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?UTCDateTimeImmutable $updatedAt = null;

    private static ResourceToEntityMapper $resourceToEntityMapper;

    abstract public function getId();

    public function getCreatedAt(): ?UTCDateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Can be used for data migrations where original created date is in the past.
     */
    public function setCreatedAt(?DateTimeInterface $date): static
    {
        $this->createdAt = null === $date ? null : UTCDateTimeImmutable::createFromInterface($date);

        return $this;
    }

    public function getUpdatedAt(): ?UTCDateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @throws Exception
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new UTCDateTimeImmutable();
        if (null === $this->createdAt) {
            $this->createdAt = $now;
        }
        $this->updatedAt = $now;
    }

    /**
     * @throws Exception
     */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new UTCDateTimeImmutable();
    }

    public static function setResourceToEntityMapper(ResourceToEntityMapper $mapper): void
    {
        self::$resourceToEntityMapper = $mapper;
    }

    /**
     * Factory to create Entity from Resource by using ResourceToEntityMapper.
     *
     * @param array<string, mixed> $context
     */
    public static function create(BaseResource $resource, array $context, ?self $existingEntity = null): static
    {
        $context[ResourceToEntityMapper::CONDITION_CONTEXT] = static::class;
        $entity = self::$resourceToEntityMapper->map($resource, $context, $existingEntity);
        if (!is_subclass_of($entity, self::class)) {
            throw new RuntimeException(sprintf('Wrong type (%s instead of %s) in Entity factory', $resource::class, static::class));
        }

        return $entity;
    }
}
