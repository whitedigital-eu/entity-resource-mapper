<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

class UTCDateTimeImmutable extends DateTimeImmutable
{
    public const UTC = 'UTC';

    /**
     * @throws Exception
     */
    public function __construct(string $datetime = 'now')
    {
        parent::__construct(datetime: $datetime, timezone: self::getUTCTimeZone());
    }

    public static function getUTCTimeZone(): DateTimeZone
    {
        return new DateTimeZone(timezone: self::UTC);
    }

    public static function createFromInterface(DateTimeInterface $object): DateTimeImmutable
    {
        return parent::createFromInterface(object: $object)->setTimezone(timezone: self::getUTCTimeZone());
    }

    public static function createFromFormat(string $format, string $datetime, ?DateTimeZone $timezone = null): DateTimeImmutable|false
    {
        return parent::createFromFormat(format: $format, datetime: $datetime, timezone: $timezone ?? self::getUTCTimeZone());
    }
}
