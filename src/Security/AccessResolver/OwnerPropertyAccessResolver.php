<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Security\AccessResolver;

use InvalidArgumentException;

class OwnerPropertyAccessResolver extends AbstractPropertyBasedAccessResolver
{
    protected function retrievePropertyPathFromConfig(?array $config)
    {
        if (!$config || !isset($config['ownerPropertyPath'])) {
            throw new InvalidArgumentException(sprintf('Access resolver configuration for "%s" does not contain required "ownerPropertyPath" entry', self::class));
        }

        return $config['ownerPropertyPath'];
    }

    protected function getAuthorizedValueId(mixed $topElement): ?int
    {
        return $this->propertyAccessor->getValue($this->security->getUser(), 'id');
    }
}
