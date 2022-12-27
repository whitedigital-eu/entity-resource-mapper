<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Security\Attribute;

use Attribute;

/**
 * If AuthorizeResource attribute is set, each resource instance will be checked against current permission set during serialization.
 * If no visibleProperties are set, only ID will be returned for non-allowed instances.
 * If $publicProperty is set, and if TRUE, resource will be granted GrantType::ALL for ITEM_GET operation.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AuthorizeResource
{
    /**
     * @param string[] $visibleProperties
     */
    public function __construct(
        private readonly LimitedResourceAccessResolverInterface $limitedResourceAccessResolver,
        private readonly array $visibleProperties = [],
        private readonly ?string $publicProperty = null,
    ) {
    }

    public function getPublicProperty(): ?string
    {
        return $this->publicProperty;
    }

    /**
     * @return string[]
     */
    public function getVisibleProperties(): array
    {
        return $this->visibleProperties;
    }
}
