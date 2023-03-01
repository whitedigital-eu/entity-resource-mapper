<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\DBAL\Types;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\Types;

class UTCDateTimeType extends DateTimeType
{
    public const UTC = 'UTC';

    public static function getUTCTimeZone(): DateTimeZone
    {
        return new DateTimeZone(self::UTC);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        if ($value instanceof DateTime) {
            $value = $value->setTimezone(self::getUTCTimeZone());
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?DateTimeInterface
    {
        if (null === $value || $value instanceof DateTime) {
            return $value;
        }

        $converted = DateTime::createFromFormat(
            '!' . $platform->getDateTimeFormatString(),
            $value,
            self::getUtcTimezone(),
        ) ?: date_create($value, self::getUtcTimezone());

        if (false === $converted) {
            throw ConversionException::conversionFailedFormat($value, Types::DATETIME_MUTABLE, $platform->getDateTimeFormatString());
        }

        return $converted;
    }
}
