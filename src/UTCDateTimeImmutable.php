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

    /**
     * @throws Exception
     */
    public static function createFromInterface(DateTimeInterface $object): static
    {
        $utcTime = $object->setTimezone(self::getUTCTimeZone());

        return new static($utcTime->format('Y-m-d H:i:s.u'));
    }

    /**
     * @throws Exception
     */
    public static function createFromFormat(string $format, string $datetime, ?DateTimeZone $timezone = null): static|false
    {
        $object = parent::createFromFormat(format: $format, datetime: $datetime, timezone: $timezone ?? self::getUTCTimeZone());

        if (false !== $object) {
            return self::createFromInterface($object);
        }

        return false;
    }
}
