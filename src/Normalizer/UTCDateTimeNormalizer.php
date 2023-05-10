<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Normalizer;

use DateTimeInterface;
use Exception;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use WhiteDigital\EntityResourceMapper\UTCDateTimeImmutable;

class UTCDateTimeNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{
    public function hasCacheableSupportsMethod(): bool
    {
        return __CLASS__ === static::class;
    }

    /**
     * @throws Exception
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ?UTCDateTimeImmutable
    {
        return new UTCDateTimeImmutable($data);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return UTCDateTimeImmutable::class === $type;
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): string
    {
        return $object->format($context['datetime_format'] ?? DateTimeInterface::RFC3339);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof UTCDateTimeImmutable;
    }
}
