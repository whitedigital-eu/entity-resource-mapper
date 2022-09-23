<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Entity;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\MappedSuperclass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use WhiteDigital\EntityResourceMapper\Mapper\ResourceToEntityMapper;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;

#[ORM\HasLifecycleCallbacks]
#[MappedSuperclass]
abstract class BaseEntity
{
    #[ORM\Column(type: 'datetime_immutable')]
    protected ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    protected ?DateTimeImmutable $updatedAt = null;

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Can be used for data migrations where original created date is in the past.
     */
    public function setCreatedAt(?DateTimeInterface $date): self
    {
        $this->createdAt = null === $date ? null : DateTimeImmutable::createFromInterface($date)->setTimezone(new DateTimeZone('UTC'));
        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new DateTimeImmutable(timezone: new DateTimeZone('UTC'));
        if (null === $this->createdAt) {
            $this->createdAt = $now;
        }
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable(timezone: new DateTimeZone('UTC'));
    }

    abstract public function getId(): mixed;

    private static ResourceToEntityMapper $resourceToEntityMapper;

    public static function setResourceToEntityMapper(ResourceToEntityMapper $mapper): void
    {
        self::$resourceToEntityMapper = $mapper;
    }

    /**
     * Factory to create Entity from Resource by using ResourceToEntityMapper
     * @param BaseResource $resource
     * @param array<string, mixed> $context
     * @param BaseEntity|null $existingEntity
     * @return static
     * @throws ExceptionInterface
     * @throws ReflectionException
     */
    public static function create(BaseResource $resource, array $context, BaseEntity $existingEntity = null): static
    {
        $context[ResourceToEntityMapper::CONDITION_CONTEXT] = static::class;
        $entity = self::$resourceToEntityMapper->map($resource, $context, $existingEntity);
        if (!$entity instanceof static) {
            throw new RuntimeException(sprintf("Wrong type (%s instead of %s) in Entity factory", get_class($resource), static::class));
        }
        return $entity;
    }
}
