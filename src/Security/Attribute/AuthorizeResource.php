<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Security\Attribute;

/**
 * If AuthorizeResource attribute is set, each resource instance will be checked against current permission set during serialization.
 * If $ownerProperty parameter is set and GrantType::OWN calculated, each resource `ownerProperty` property will be checked against current user.
 * If $groupProperty parameter is set and GrantType::GROUP calculated, each resource `groupProperty` property will be checked against current user's group.
 * If no visibleProperties are set, only ID will be returned for non-allowed instances.
 * If $publicProperty is set, and if TRUE, resource will be granted GrantType::ALL for ITEM_GET operation.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AuthorizeResource
{
    /**
     * @param string|null $ownerProperty
     * @param string|null $groupProperty
     * @param string[] $visibleProperties
     * @param string|null $publicProperty
     */
    public function __construct(
        private readonly ?string $ownerProperty = null,
        private readonly ?string $groupProperty = null,
        private readonly array   $visibleProperties = [],
        private readonly ?string $publicProperty = null,
    )
    {
    }

    /**
     * @return string|null
     */
    public function getPublicProperty(): ?string
    {
        return $this->publicProperty;
    }

    /**
     * @return string|null
     */
    public function getGroupProperty(): ?string
    {
        return $this->groupProperty;
    }

    /**
     * @return string[]
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
