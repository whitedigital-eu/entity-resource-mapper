<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Security\Attribute;

/**
 * If AuthorizeResource attribute is set, each resource instance will be checked against current permission set during serialization.
 * If $ownerProperty parameter is set and GrantType::OWN calculated, each resource `ownerProperty` property will be checked against current user.
 * If no visibleProperties are set, only ID will be returned for non-own instances.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AuthorizeResource
{
    public function __construct(
        private readonly ?string $ownerProperty = null,
        private readonly array   $visibleProperties = [],
    )
    {
    }

    /**
     * @return array
     */
    public function getVisibleProperties(): array
    {
        return $this->visibleProperties;
    }

    /**
     * @return ?string
     */
    public function getOwnerProperty(): ?string
    {
        return $this->ownerProperty;
    }
}
