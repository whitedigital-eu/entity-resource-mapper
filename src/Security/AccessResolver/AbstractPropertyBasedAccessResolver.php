<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Security\AccessResolver;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Security\Attribute\AccessResolverConfiguration;
use WhiteDigital\EntityResourceMapper\Security\Interface\AccessResolverInterface;

abstract class AbstractPropertyBasedAccessResolver implements AccessResolverInterface
{
    public function __construct(
        protected readonly Security $security,
        protected readonly PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    public function isObjectAccessGranted(AccessResolverConfiguration $accessResolverAttribute, object $object): bool
    {
        $propertyPath = $this->retrievePropertyPathFromConfig($accessResolverAttribute->getConfig());
        $topElement = $object;
        $isCollection = false;
        foreach (explode('.', $propertyPath) as $node) {
            if (str_ends_with($node, '[]')  // Collection as NON-LAST item in property chain
                && !str_ends_with($propertyPath, $node)) {
                throw new InvalidArgumentException('Collection is not supported as non-last element.');
            }

            if (str_ends_with($node, '[]')) {
                $node = substr($node, 0, -2);
                $isCollection = true;
            }

            $topElement = $this->propertyAccessor->getValue($topElement, $node);
        }

        if ($isCollection) {
            /** @var BaseEntity&UserInterface $user */
            $user = $this->security->getUser();
            if ($topElement instanceof Collection) {
                return $topElement->contains($user);
            }

            /** @var array $filtered */
            $filtered = array_filter(
                $topElement,
                static fn ($object) => $object->id === $user->getId(),
            );

            return [] !== $filtered;
        }

        $isObject = is_object($topElement); // handle scalar or object
        $authorizedValueId = $this->getAuthorizedValueId($topElement);

        return $isObject ? ($this->propertyAccessor->getValue($topElement, 'id') === $authorizedValueId)
            : ($topElement === $authorizedValueId);
    }

    public function limitCollectionQuery(AccessResolverConfiguration $accessResolverAttribute, QueryBuilder $queryBuilder): void
    {
        $propertyPath = $this->retrievePropertyPathFromConfig($accessResolverAttribute->getConfig());
        if ($this->isPropertyNested($propertyPath)) {
            $this->applyNestedPropertyConstraints($propertyPath, $queryBuilder);
        } else {
            $this->applyRegularPropertyConstraints($propertyPath, $queryBuilder);
        }

        $queryBuilder->setParameter('property', $this->security->getUser());
    }

    abstract protected function retrievePropertyPathFromConfig(?array $config);

    abstract protected function getAuthorizedValueId(mixed $topElement): int|object|null;

    protected function isPropertyNested(string $property): bool
    {
        return str_contains($property, '.');
    }

    protected function applyRegularPropertyConstraints(string $propertyPath, QueryBuilder $queryBuilder): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];
        if (str_ends_with($propertyPath, '[]')) { // property is to-many association
            $propertyPath = substr($propertyPath, 0, -2);
            $queryBuilder->join("$rootAlias.$propertyPath", $propertyPath);
            $queryBuilder->andWhere("$propertyPath = :property");
        } else {
            $queryBuilder->andWhere("$rootAlias.$propertyPath = :property");
        }
    }

    protected function applyNestedPropertyConstraints(string $propertyPath, QueryBuilder $queryBuilder): void
    {
        [$propertyName, $joins] = $this->validateAndCreateJoins($propertyPath);
        $tableAlias = $queryBuilder->getRootAliases()[0];
        foreach ($joins as $join) {
            // check if join already exists
            foreach ($queryBuilder->getDQLPart('join') as $joinPart) {
                if ($joinPart[0]->getJoin() === "$tableAlias.$join") {
                    continue 2;
                }
            }
            $queryBuilder->join("$tableAlias.$join", $join);
            $tableAlias = $join;
        }
        $propertyPath = str_ends_with($propertyPath, '[]') ? $tableAlias : "$tableAlias.$propertyName";
        $queryBuilder->andWhere("$propertyPath = :property");
    }

    protected function validateAndCreateJoins(string $propertyPath): array
    {
        $propertyName = null;
        $joins = explode('.', $propertyPath);
        if (!str_ends_with($propertyPath, '[]')) {
            $propertyName = array_pop($joins);
        }

        foreach ($joins as &$join) {
            if (str_ends_with($join, '[]')) {
                if (!str_ends_with($propertyPath, $join)) {
                    throw new InvalidArgumentException('Collection is not supported as non-last element.');
                }
                $join = substr($join, 0, -2);
            }
        }

        return [$propertyName, $joins];
    }
}
