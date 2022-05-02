<?php

namespace WhiteDigital\EntityResourceMapper\Entity;


use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\MappedSuperclass;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use WhiteDigital\EntityResourceMapper\Mapper\ResourceToEntityMapper;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;

#[ORM\HasLifecycleCallbacks]
#[MappedSuperclass]
abstract class BaseEntity
{
    #[ORM\Column(type: 'datetime')]
    protected ?\DateTimeInterface $created = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected ?\DateTimeInterface $updated = null;


    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
    }

    public function getUpdated(): ?\DateTimeInterface
    {
        return $this->updated;
    }
    

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTime('now');
        $this->created = $now;
        $this->updated = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updated = new \DateTime('now');
    }

    abstract public function getId(): ?int;

    private static ResourceToEntityMapper $resourceToEntityMapper;

    public static function setResourceToEntityMapper(ResourceToEntityMapper $mapper): void
    {
        self::$resourceToEntityMapper = $mapper;
    }

    /**
     * Factory to create Entity from Resource by using ResourceToEntityMapper
     * @param BaseResource $resource
     * @param array $context
     * @param BaseEntity|null $existingEntity
     * @return static
     * @throws ExceptionInterface
     * @throws \ReflectionException
     */
    public static function create(BaseResource $resource, array $context, BaseEntity $existingEntity = null): static
    {
        $context[ResourceToEntityMapper::CONDITION_CONTEXT] = static::class;
        $entity = self::$resourceToEntityMapper->map($resource, $context, $existingEntity);
        if (!$entity instanceof static) {
            throw new \RuntimeException(sprintf("Wrong type (%s instead of %s) in Entity factory", get_class($resource), static::class));
        }
        return $entity;
    }
}
