<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Security\AccessResolver;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Security;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Security\Attribute\AccessResolverConfiguration;
use WhiteDigital\EntityResourceMapper\Security\Attribute\AuthorizeResource;
use WhiteDigital\EntityResourceMapper\Security\Interface\AccessResolverInterface;

class OwnerPropertyAccessResolver implements AccessResolverInterface
{
    public function __construct(
        private readonly Security                  $security,
        private readonly PropertyAccessorInterface $propertyAccessor,
    )
    {
    }

    public function isObjectAccessGranted(AccessResolverConfiguration $accessResolverAttribute, object $object): bool
    {
        $config = $accessResolverAttribute->getConfig();
        if (null === $config || !isset($config['ownerPropertyPath'])) {
            throw new InvalidArgumentException(sprintf('Access resolver configuration for "%s" does not contain required "ownerPropertyPath" entry',
                self::class));
        }
        $topElement = $object;
        $isCollection = false;
        $property = $config['ownerPropertyPath'];
        foreach (explode('.', $property) as $node) {
            if (str_ends_with($node, '[]')  // Collection as NON-LAST item in property chain
                && !str_ends_with($property, $node)) {
                throw new InvalidArgumentException('Collection is not supported as non-last element.');
            }
            if (str_ends_with($node, '[]')) {
                $node = substr($node, 0, -2);
                $isCollection = true;
            }
            $topElement = $this->propertyAccessor->getValue($topElement, $node);
        }
        if ($isCollection) {
            /** @var Collection<int, BaseEntity> $topElement */
            return $topElement->contains($this->security->getUser());
            // TODO WHat if top element is BaseResource?
        }
        $isObject = is_object($topElement); // handle scalar or object
        $authorizedValueId = $this->propertyAccessor->getValue($this->security->getUser(), 'id');
        return $isObject ? ($this->propertyAccessor->getValue($topElement, 'id') === $authorizedValueId)
            : ($topElement === $authorizedValueId);
    }

    public function limitCollectionQuery(AccessResolverConfiguration $accessResolverAttribute, QueryBuilder $queryBuilder): void
    {
        $config = $accessResolverAttribute->getConfig();
        if (null === $config || !isset($config['ownerPropertyPath'])) {
            throw new InvalidArgumentException(sprintf('Access resolver configuration for "%s" does not contain required "ownerPropertyPath" entry',
                self::class));
        }
        $ownerProperty = $config['ownerPropertyPath'];
        if ($this->isOwnerPropertyNested($ownerProperty)) {
            $this->applyNestedPropertyConstraints($ownerProperty, $queryBuilder);
        } else if ($this->isOwnerPropertyToManyRelation($ownerProperty)) {
            $this->applyToManyPropertyConstraints($ownerProperty, $queryBuilder);
        } else {
            $this->applyRegularPropertyConstraints($ownerProperty, $queryBuilder);
        }
        $queryBuilder->setParameter('ownerValue', $this->security->getUser());
    }

    private function isOwnerPropertyNested(string $property): bool
    {
        return str_contains($property, '.');
    }

    private function isOwnerPropertyToManyRelation(string $ownerProperty): bool
    {
        return str_ends_with($ownerProperty, '[]');
    }

    /**
     * Because owner property value is a nested property, we need to add joins to query builder
     */
    private function applyNestedPropertyConstraints(string $ownerProperty, QueryBuilder $queryBuilder): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $joins = explode('.', $ownerProperty);
        if (count($joins) > 2) {
            throw new InvalidArgumentException('More than two nested properties are currently not supported: ' . $ownerProperty);
        }
        foreach ($joins as $join) {
            // check if join already exists
            foreach ($queryBuilder->getDQLPart('join') as $joinPart) {
                if ($joinPart[0]->getJoin() === "$rootAlias.$join") {
                    continue 2;
                }
            }
            $queryBuilder->join("$rootAlias.$join", $join);
        }
        $queryBuilder->andWhere("$ownerProperty = :ownerValue");
    }

    /**
     * Because owner property value is a to-many association property, we need to add join to query builder
     */
    private function applyToManyPropertyConstraints(string $ownerProperty, QueryBuilder $queryBuilder): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $collectionProperty = substr($ownerProperty, 0, -2);
        $queryBuilder->join("$rootAlias.$collectionProperty", $collectionProperty);
        $queryBuilder->andWhere("$collectionProperty = :ownerValue");
    }

    private function applyRegularPropertyConstraints(string $ownerProperty, QueryBuilder $queryBuilder): void
    {
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere("$rootAlias.$ownerProperty = :ownerValue");
    }


    private function retrieveAuthResourceAttributeArgumentByName(string $resourceClassname, string $argumentName)
    {
        try {
            $reflection = new ReflectionClass($resourceClassname);
        } catch (ReflectionException $e) {
            return null;
        }
        $resourceAuthAttribute = $reflection->getAttributes(AuthorizeResource::class)[0] ?? null;
        if ($resourceAuthAttribute) {
            return $resourceAuthAttribute->getArguments()[$argumentName] ?? null;
        }
        return null;
    }
}