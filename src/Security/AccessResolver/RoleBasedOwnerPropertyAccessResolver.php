<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Security\AccessResolver;

use InvalidArgumentException;

class RoleBasedOwnerPropertyAccessResolver extends AbstractAccessResolver
{
    protected function retrievePropertyPathFromConfig(?array $config): string
    {
        if (!$config) {
            throw new InvalidArgumentException(sprintf('Access resolver configuration for "%s" does not exist', self::class));
        }

        $roles = $this->security->getUser()?->getRoles();
        if (!$roles) {
            throw new InvalidArgumentException(sprintf('Current user does not have roles to check for access resolver "%s"', self::class));
        }

        $propertyPath = null;
        foreach ($roles as $role) {
            if (isset($config[$role])) {
                $propertyPath = $config[$role];
                break;
            }
        }

        if (!$propertyPath) {
            throw new InvalidArgumentException(sprintf('None of user roles ("%s") have been configured for access resolver "%s"', implode(', ', $roles), self::class));
        }

        return $propertyPath;
    }

    protected function getAuthorizedValueId(mixed $topElement): ?int
    {
        return $this->propertyAccessor->getValue($this->security->getUser(), 'id');
    }
}
