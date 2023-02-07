<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Security\Attribute;

use Attribute;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS)]
final class AuthorizeResource
{
    /**
     * @param AccessResolverConfiguration[] $accessResolvers
     */
    public function __construct(private readonly array $accessResolvers)
    {
        foreach ($this->accessResolvers as $accessResolver) {
            if (!$accessResolver instanceof AccessResolverConfiguration) {
                throw new InvalidArgumentException(sprintf('AuthorizeResource attribute accessResolvers can only be instances of "%s".', AccessResolverConfiguration::class));
            }
        }
    }

    /**
     * @return AccessResolverConfiguration[]
     */
    public function getAccessResolvers(): array
    {
        return $this->accessResolvers;
    }
}
