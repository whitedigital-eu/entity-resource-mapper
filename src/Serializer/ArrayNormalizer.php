<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Serializer;

use ApiPlatform\Exception\ResourceClassNotFoundException;
use ArrayObject;
use ReflectionException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Mapper\EntityToResourceMapper;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;
use WhiteDigital\EntityResourceMapper\UTCDateTimeImmutable;

/**
 * When custom select fields are added to QueryBuilder object, array is returned instead of pure BaseEntity object.
 */
#[Autoconfigure(tags: ['serializer.normalizer'])]
class ArrayNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'CUSTOM_ARRAY_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        private readonly EntityToResourceMapper $entityToResourceMapper,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return float|array<BaseResource>|ArrayObject<int, BaseResource>|bool|int|string|null
     *
     * @throws ReflectionException
     * @throws ResourceClassNotFoundException
     * @throws ExceptionInterface
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): float|array|ArrayObject|bool|int|string|null
    {
        $context[self::ALREADY_CALLED] = true;
        $apiResource = $this->entityToResourceMapper->map($object[0], $context);

        foreach ($object as $key => $value) {
            if ($value instanceof BaseEntity) {
                continue;
            }
            if (!property_exists($apiResource, $key)) {
                throw new RuntimeException("Custom SQL property $key does not exist on " . $apiResource::class);
            }
            $apiResource->{$key} = $value;
        }

        return $this->normalizer->normalize($apiResource, $format, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return is_array($data)
            && array_key_exists(0, $data)
            && $data[0] instanceof BaseEntity
            && $this->hasStringKeys($data);
    }

    /**
     * Checks if array has string keys, to detected custom queryBuilder fields.
     *
     * @param array<int|string, mixed> $array
     */
    private function hasStringKeys(array $array): bool
    {
        return count(array_filter(array_keys($array), 'is_string')) > 0;
    }

    /**
     * @return array<string, bool>
     */
    public function getSupportedTypes(?string $format = null): array
    {
        return ['array' => true];
    }
}
