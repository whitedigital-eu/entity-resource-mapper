<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Serializer;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Mapper\EntityToResourceMapper;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;

#[Autoconfigure(tags: ['serializer.normalizer'])]
class EntityNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'CUSTOM_ENTITY_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        private readonly EntityToResourceMapper $entityToResourceMapper,
    )
    {
    }

    /**
     * @param mixed $data
     * @param string|null $format
     * @param string[] $context
     * @return bool
     */
    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }
        return $data instanceof BaseEntity;
    }

    /**
     * @param mixed $object
     * @param string|null $format
     * @param array<string> $context
     * @return float|array<BaseResource>|\ArrayObject<int, BaseResource>|bool|int|string|null
     * @throws ResourceClassNotFoundException
     * @throws ExceptionInterface
     */
    public function normalize(mixed $object, string $format = null, array $context = []): float|array|\ArrayObject|bool|int|string|null
    {
        $apiResource = $this->entityToResourceMapper->map($object, $context);
        $context[self::ALREADY_CALLED] = true;
        return $this->normalizer->normalize($apiResource, $format, $context);
    }
}
