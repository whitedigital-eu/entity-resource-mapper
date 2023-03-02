<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\DBAL\Types;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\Types;

class UTCDateTimeImmutableType extends DateTimeImmutableType
{
    public const UTC = 'UTC';

    public static function getUTCTimeZone(): DateTimeZone
    {
        return new DateTimeZone(self::UTC);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        if ($value instanceof DateTimeImmutable) {
            $value = $value->setTimezone(self::getUtcTimezone());
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?DateTimeImmutable
    {
        if (null === $value || $value instanceof DateTimeImmutable) {
            return $value;
        }

        $converted = DateTimeImmutable::createFromFormat(
            '!' . $platform->getDateTimeFormatString(),
            $value,
            self::getUTCTimeZone(),
        ) ?: date_create_immutable($value, self::getUtcTimezone());

        if (false === $converted) {
            throw ConversionException::conversionFailedFormat($value, Types::DATETIME_IMMUTABLE, $platform->getDateTimeFormatString());
        }

        return $converted;
    }
}
