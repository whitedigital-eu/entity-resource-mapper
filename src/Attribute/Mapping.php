<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Mapping
{
    public function __construct(private readonly string $mappedClass, private readonly ?string $condition = null)
    {
    }

    public function getMappedClass(): string
    {
        return $this->mappedClass;
    }

    public function getCondition(): ?string
    {
        return $this->condition;
    }
}
