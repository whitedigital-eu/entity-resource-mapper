<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Normalizer;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use WhiteDigital\EntityResourceMapper\UTCDateTimeImmutable;

class UTCDateTimeNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     *
     * @throws \Exception
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ?UTCDateTimeImmutable
    {
        return new UTCDateTimeImmutable($data);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return UTCDateTimeImmutable::class === $type;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): string
    {
        return $object->format($context['datetime_format'] ?? \DateTimeInterface::RFC3339);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof UTCDateTimeImmutable;
    }

    /**
     * @return array<string, bool>
     */
    public function getSupportedTypes(?string $format = null): array
    {
        return [UTCDateTimeImmutable::class => true];
    }
}
