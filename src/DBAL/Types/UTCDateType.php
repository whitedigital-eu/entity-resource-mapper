<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\DBAL\Types;

use DateTime;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateType;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class UTCDateType extends DateType
{
    public function convertToPHPValue($value, AbstractPlatform $platform): ?DateTime
    {
        throw new InvalidConfigurationException(sprintf('%s is deprectaed, use %s instead', __CLASS__, UTCDateImmutableType::class));
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof DateTime) {
            throw new InvalidConfigurationException(sprintf('%s is deprectaed, use %s instead', __CLASS__, UTCDateImmutableType::class));
        }

        return parent::convertToDatabaseValue($value, $platform);
    }
}
