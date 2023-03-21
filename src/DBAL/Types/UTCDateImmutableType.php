<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\DBAL\Types;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateImmutableType;
use Doctrine\DBAL\Types\Types;
use Exception;
use WhiteDigital\EntityResourceMapper\UTCDateTimeImmutable;

class UTCDateImmutableType extends DateImmutableType
{
    /**
     * @param T $value
     *
     * @return (T is null ? null : string)
     *
     * @template T
     *
     * {@inheritDoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof DateTimeImmutable) {
            $value = $value->setTimezone(UTCDateTimeImmutable::getUtcTimezone());
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    /**
     * @param T $value
     *
     * @return (T is null ? null : DateTimeImmutable)
     *
     * @template T
     *
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?DateTimeImmutable
    {
        if (null === $value || $value instanceof DateTimeImmutable) {
            return $value;
        }

        try {
            $converted = UTCDateTimeImmutable::createFromFormat(
                '!' . $platform->getDateTimeFormatString(),
                $value,
            ) ?: new UTCDateTimeImmutable($value);
        } catch (Exception $exception) {
            throw ConversionException::conversionFailedFormat($value, Types::DATETIME_IMMUTABLE, $platform->getDateTimeFormatString(), previous: $exception);
        }

        return $converted;
    }
}
