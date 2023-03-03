<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Security\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class VisibleProperty
{
    public function __construct(private readonly string $ownerProperty, private readonly array $properties = [])
    {
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getOwnerProperty(): ?string
    {
        return $this->ownerProperty;
    }
}
