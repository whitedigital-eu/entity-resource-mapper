<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Security;

use Closure;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\Proxy;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Mapper\ClassMapper;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;
use WhiteDigital\EntityResourceMapper\Security\Attribute\AuthorizeResource;
use WhiteDigital\EntityResourceMapper\Security\Enum\GrantType;

/**
 * Data used in following places:
 * - 1. Menu structure generator - outputs menu items available by current role
 * - 2. Data provider - limit collection get output
 * - 3. Data provider - authorize item get output
 * - 4. data persister - authorize collection POST
 * - 5. data persister - authorize item PUT/PATCH
 * - 6. data persister - authorize item DELETE
 * - 7. Check individual resources in EntityToResourceMapper
 */
#[Autoconfigure(configurator: '@WhiteDigital\EntityResourceMapper\Security\AuthorizationServiceConfiguratorInterface')]
final class AuthorizationService
{
    public const COL_GET = 'collection-get';
    public const COL_POST = 'collection-post';
    public const ITEM_GET = 'item-get';
    public const ITEM_PATCH = 'item-patch'; // Includes PUT
    public const ITEM_DELETE = 'item-delete';
    public const ALL = 'all'; // Includes all of the above

    public const ACCESS_DENIED_MESSAGE = 'Access Denied.';

    /** @var array<class-string, array<string, mixed>> */
    private array $resources = [];

    /**
     * If closure returns true, authorization system will be disabled (return GrantType::ALL).
     */
    private ?Closure $authorizationOverride = null;

    public function __construct(
        private readonly Security            $security,
        private readonly ClassMapper         $classMapper,
        private readonly TranslatorInterface $translator,
    )
    {
    }

    /**
     * @param array<class-string, array{ all: array<string, GrantType>, collection-get: array<string, GrantType>, collection-post: array<string, GrantType>, item-get: array<string, GrantType>, item-patch: array<string, GrantType>, item-delete: array<string, GrantType>}> $resources
     * @return void
     */
    public function setResources(array $resources): void
    {
        $this->resources = $resources;
    }

    /**
     * If closure returns true, authorization system will be disabled (returning GrantType::ALL).
     */
    public function setAuthorizationOverride(Closure $closure): void
    {
        $this->authorizationOverride = $closure;
    }

    /**
     * @param BaseEntity|BaseResource $object
     * @param string $operation
     * @param bool $throwException
     * @param GrantType|null $forcedGrantType
     * @return bool
     */
    public function authorizeSingleObject(
        BaseEntity|BaseResource $object,
        string                  $operation,
        bool                    $throwException = true,
        GrantType               $forcedGrantType = null
    ): bool
    {
        if (null !== $this->authorizationOverride && ($this->authorizationOverride)()) {
            return true;
        }
        $accessDecision = false;
        $resourceClass = $this->getAuthorizableObjectResourceClassname($object);
        $highestGrantType = $forcedGrantType ?? $this->calculateFinalGrantType($resourceClass, $operation);
        if (GrantType::ALL === $highestGrantType) {
            return true;
        }
        if (GrantType::OWN === $highestGrantType) {
            if (self::COL_POST === $operation) {
                return true; // no need to check owner if it is POST, as no owner property exists
            }
            $accessDecision = $this->isResourceAuthorizedForUser($resourceClass, $object);
        }
        if ($throwException && !$accessDecision) {
            throw new AccessDeniedException($this->translator->trans(self::ACCESS_DENIED_MESSAGE));
        }
        return $accessDecision;
    }

    /**
     * @param BaseResource|BaseEntity $object
     * @return string
     */
    private function getAuthorizableObjectResourceClassname(BaseResource|BaseEntity $object): string
    {
        if ($object instanceof BaseEntity) {
            $reflection = new ReflectionClass($object);
            if ($object instanceof Proxy) { //get real object behind Doctrine proxy object
                $reflection = $reflection->getParentClass();
            }
            return $this->classMapper->byEntity($reflection->getName());
        }
        return $object::class;
    }

    /**
     * @param string $resourceClass
     * @param BaseResource|BaseEntity $object
     * @return bool
     */
    private function isResourceAuthorizedForUser(
        string                  $resourceClass,
        BaseResource|BaseEntity $object
    ): bool
    {
        $ownerProperty = $this->getAuthorizeAttributeOwnerProperty($resourceClass);
        if (!$ownerProperty) {
            $ownerCallback = $this->getAuthorizeOwnerCallbackProperty($resourceClass);
            if (is_callable([$object, $ownerCallback])) {
                return $object->$ownerCallback($this->security);
            }
            throw new RuntimeException('GrantType::OWN but neither $ownerProperty nor $ownerCallback not set at ' . __CLASS__);
        }
        [$topElement, $isCollection] = $this->parseOwnerProperty($object, $ownerProperty);
        if ($isCollection) {
            /** @var  Collection<int, BaseEntity> $topElement */
            return $topElement->contains($this->security->getUser());
        }
        $authorizedValueId = $this->accessValue($this->security->getUser(), 'id');
        return is_object($topElement) ? ($this->accessValue($topElement, 'id') === $authorizedValueId)
            : ($topElement === $authorizedValueId);
    }

    /**
     * @param BaseResource|BaseEntity $object
     * @param string $property
     * @return array{0: mixed, 1: boolean}
     */
    private function parseOwnerProperty(BaseResource|BaseEntity $object, string $property): array
    {
        $topElement = $object;
        $isCollection = false;
        foreach (explode('.', $property) as $node) {
            if (str_ends_with($node, '[]')  // Collection as NON-LAST item in property chain
                && !str_ends_with($property, $node)) {
                throw new RuntimeException('Collection is not supported as non-last element.');
            }
            if (str_ends_with($node, '[]')) {
                $node = substr($node, 0, -2);
                $isCollection = true;
            }
            $topElement = $this->accessValue($topElement, $node);
        }
        return [$topElement, $isCollection];
    }

    /**
     * @param class-string $resourceClass
     * @param QueryBuilder $queryBuilder
     * @param GrantType|null $forceGrantType
     * @return void
     */
    public function limitGetCollection(
        string       $resourceClass,
        QueryBuilder $queryBuilder,
        ?GrantType   $forceGrantType = null
    ): void
    {
        if (null !== $this->authorizationOverride && ($this->authorizationOverride)()) {
            return;
        }
        $highestGrantType = $forceGrantType ?? $this->calculateFinalGrantType($resourceClass, self::COL_GET);
        if (GrantType::ALL === $highestGrantType) {
            return;
        }
        if (GrantType::NONE === $highestGrantType) {
            throw new AccessDeniedException($this->translator->trans(self::ACCESS_DENIED_MESSAGE));
        }
        if (GrantType::OWN === $highestGrantType) {
            $this->applyConstraintsToQueryBuilder($resourceClass, $queryBuilder);
        }
    }


    /**
     * Return allowed (All, Own, None) operations per resource.
     * @param string[]|null $forcedRoles
     * @return array<int, array<string, array<string, GrantType>>>.
     * @throws ReflectionException
     */
    public function currentResourceRoles(?array $forcedRoles = null): array
    {
        $output = [];
        foreach ($this->resources as $resource => $resourceOperations) {
            $output[] = [
                $this->extractClassName($resource) => [
                    self::COL_GET => $this->calculateFinalGrantType($resource, self::COL_GET, $forcedRoles),
                    self::ITEM_GET => $this->calculateFinalGrantType($resource, self::ITEM_GET, $forcedRoles),
                    self::COL_POST => $this->calculateFinalGrantType($resource, self::COL_POST, $forcedRoles),
                    self::ITEM_PATCH => $this->calculateFinalGrantType($resource, self::ITEM_PATCH, $forcedRoles),
                    self::ITEM_DELETE => $this->calculateFinalGrantType($resource, self::ITEM_DELETE, $forcedRoles),
                ],
            ];
        }
        return $output;
    }

    /**
     * Calculate the highest grant level based on Resource permissions for specific operation merged with ALL.
     * @param class-string $resourceClass
     * @param string $operation
     * @param string[]|null $forceRoles
     * @return GrantType
     */
    public function calculateFinalGrantType(
        string $resourceClass,
        string $operation,
        ?array $forceRoles = null
    ): GrantType
    {
        if (empty($this->resources)) {
            return GrantType::ALL;
        }

        if (!array_key_exists($resourceClass, $this->resources)) {
            throw new RuntimeException("Resource $resourceClass not configured in AuthorizationService.");
        }

        $user = $this->security->getUser();
        if (null === $user) {
            throw new AccessDeniedException(self::ACCESS_DENIED_MESSAGE);
        }
        $availableRoles = $forceRoles ?: $user->getRoles();

        $allowedRoles = array_merge($this->resources[$resourceClass][$operation],
            $this->resources[$resourceClass][self::ALL]);

        //IF OPERATION DOESN'T EXIST OR ROLE DOESN'T EXIST IN RESOURCE return NONE
        $highestGrantType = GrantType::NONE;
        foreach ($allowedRoles as $role => $grantType) {
            if (in_array($role, $availableRoles, true)) {
                $highestGrantType = $this->elevateGrantType($highestGrantType, $grantType);
            }
        }
        return $highestGrantType;
    }

    /**
     * Only change GrantType in elevated order: NONE -> OWN -> ALL
     * @param GrantType $currentGrantType
     * @param GrantType $expectedGrantType
     * @return GrantType
     */
    private function elevateGrantType(GrantType $currentGrantType, GrantType $expectedGrantType): GrantType
    {
        $order = [
            GrantType::NONE->value => 10,
            GrantType::OWN->value => 20,
            GrantType::ALL->value => 30,
        ];
        if ($order[$expectedGrantType->value] > $order[$currentGrantType->value]) {
            return $expectedGrantType;
        }
        return $currentGrantType;
    }

    /**
     * Extract class name from FQCN
     * @param string $FQCN
     * @return string
     * @throws ReflectionException
     */
    private function extractClassName(string $FQCN): string
    {
        return (new ReflectionClass($FQCN))->getShortName();
    }

    /**
     * @param BaseEntity|BaseResource $object
     * @param string $property
     * @return mixed
     */
    private function accessValue(BaseEntity|BaseResource $object, string $property): mixed
    {
        if ($object instanceof BaseEntity) {
            $getter = $this->makeGetter($property);
            return $object->{$getter}();
        }
        return $object->{$property};
    }

    /**
     * @param string $property
     * @return string
     */
    private function makeGetter(string $property): string
    {
        return 'get' . ucfirst($property);
    }

    /**
     * Extract data from Resource class attribute AuthorizeResource
     * @param class-string<BaseResource> $resourceClass
     * @return string|null
     */
    private function getAuthorizeAttributeOwnerProperty(string $resourceClass): string|null
    {
        try {
            $reflection = new ReflectionClass($resourceClass);
            /** @phpstan-ignore-next-line */
        } catch (ReflectionException $e) {
            return null;
        }
        $authorizeAttribute = $reflection->getAttributes(AuthorizeResource::class)[0] ?? null;
        return $authorizeAttribute?->getArguments()['ownerProperty'] ?? null;
    }

    /**
     * Extract data from Resource class attribute AuthorizeResource
     * @param class-string<BaseResource> $resourceClass
     * @return string|null
     */
    private function getAuthorizeOwnerCallbackProperty(string $resourceClass): string|null
    {
        try {
            $reflection = new ReflectionClass($resourceClass);
            /** @phpstan-ignore-next-line */
        } catch (ReflectionException $e) {
            return null;
        }
        $authorizeAttribute = $reflection->getAttributes(AuthorizeResource::class)[0] ?? null;
        return $authorizeAttribute?->getArguments()['ownerCallback'] ?? null;
    }

    private function applyConstraintsToQueryBuilder(string $resourceClass, QueryBuilder $queryBuilder): void
    {
        $ownerProperty = $this->getAuthorizeAttributeOwnerProperty($resourceClass);
        if (!$ownerProperty) {
            throw new RuntimeException('GrantType::OWN but $ownerProperty not set at ' . __CLASS__);
        }
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
            throw new RuntimeException('More than two nested properties are currently not supported: ' . $ownerProperty);
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

    private function getAuthorizeOwnerCallback(BaseResource|BaseEntity $object): mixed
    {

    }
}
