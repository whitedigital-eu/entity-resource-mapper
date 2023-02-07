<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Attribute;

use Attribute;

/**
 * Add attribute to allow circular references in entity to resource mapping;
 * maxLevels = 0 means infinite number of levels is allowed (make sure that source query is finite).
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class SkipCircularReferenceCheck
{
    public function __construct(
        private readonly int $maxLevels = 0,
    ) {
    }

    public function getMaxLevels(): int
    {
        return $this->maxLevels;
    }
}
