<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Security;

use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\Proxy;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Mapper\ClassMapper;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;
use WhiteDigital\EntityResourceMapper\Security\Enum\GrantType;

/**
 * Data used in following places:
 * - 1. Menu structure generator - outputs menu items available by current role
 * - 2. Data provider - limit collection get output
 * - 3. Data provider - authorize item get output
 * - 4. data persister - authorize collection POST
 * - 5. data persister - authorize PUT/PATCH + DELETE
 * - 6. Check individual resources in EntityToResourceMapper
 */
#[Autoconfigure(configurator: '@App\Service\Configurator\AuthorizationServiceConfigurator')]
final class AuthorizationService
{
    public const COL_GET = 'collection-get';
    public const COL_POST = 'collection-post';
    public const ITEM_GET = 'item-get';
    public const ITEM_WRITE = 'item-write'; // Includes PATCH, PUT, DELETE
    public const ALL = 'all'; // Includes all of the above

    public const ACCESS_DENIED_MESSAGE = 'Pieeja liegta. Lūdzu sazinieties ar sistēmas administratoru.';

    /** @var array<class-string, array<string, mixed>> */
    private array $resources = [];

    /** @var array<int, array<string, mixed>> }> */
    private array $menuStructure = [];

    public function __construct(
        private readonly Security    $security,
        private readonly ClassMapper $classMapper,
    )
    {
    }


    /**
     * @param array<class-string, array{ all: array<string, GrantType>, collection-get: array<string, GrantType>, collection-post: array<string, GrantType>, item-get: array<string, GrantType>, item-write: array<string, GrantType>}> $resources
     * @return void
     */
    public function setResources(array $resources): void
    {
        $this->resources = $resources;
    }

    /**
     * @param array<int, array{name: string, mainResource?: string, roles?: string[], children?: array<int, mixed> }> $menuStructure
     * @return void
     */
    public function setMenuStructure(array $menuStructure): void
    {
        $this->menuStructure = $menuStructure;
    }


    /**
     * @param BaseResource $resource
     * @param string|null $ownerProperty
     * @param array<string, mixed> $context
     * @param bool $throwException
     * @return bool
     */
    public function authorizeSingleResource(BaseResource $resource, array $context, ?string $ownerProperty = null, bool $throwException = true): bool
    {
        $accessDecision = false;

        $operation = $this->getOperationfromContext($context);
        if (null === $operation) {
            throw new AccessDeniedException('Operation type not provided.');
        }

        $finalGrant = $this->calculateFinalGrantType(get_class($resource), $operation);
        if (GrantType::ALL === $finalGrant) {
            $accessDecision = true;
        }
        if (GrantType::OWN === $finalGrant) {
            if (!$ownerProperty) {
                throw new \RuntimeException('GrantType::OWN but $ownerProperty not set at ' . __CLASS__);
            }
            $user = $this->security->getUser();
            $accessDecision = $resource->{$ownerProperty}->getId() === $user->getId();
        }
        if ($throwException && !$accessDecision) {
            throw new AccessDeniedException(self::ACCESS_DENIED_MESSAGE);
        }
        return $accessDecision;
    }


    /**
     * @param BaseEntity $entity
     * @param array<string, mixed> $context
     * @param string|null $ownerProperty
     * @param bool $throwException
     * @return bool
     */
    public function authorizeSingleEntity(BaseEntity $entity, array $context, ?string $ownerProperty, bool $throwException = true): bool
    {
        $accessDecision = false;

        $operation = $this->getOperationfromContext($context);
        if (null === $operation) {
            throw new AccessDeniedException('Operation type not provided.');
        }

        $reflection = new \ReflectionClass($entity);
        if ($entity instanceof Proxy) { //get real object behind Doctrine proxy object
            $reflection = $reflection->getParentClass();
        }
        $finalGrant = $this->calculateFinalGrantType($this->classMapper->byEntity($reflection->getName()), $operation);
        if (GrantType::ALL === $finalGrant) {
            $accessDecision = true;
        }
        if (GrantType::OWN === $finalGrant) {
            if (!$ownerProperty) {
                throw new \RuntimeException('GrantType::OWN but $ownerProperty not set at ' . __CLASS__);
            }
            $user = $this->security->getUser();
            $getterMethod = $this->makeGetter($ownerProperty);
            $accessDecision = $entity->{$getterMethod}()->getId() === $user->getId();
        }
        if ($throwException && !$accessDecision) {
            throw new AccessDeniedException(self::ACCESS_DENIED_MESSAGE);
        }
        return $accessDecision;
    }


    /**
     * @param class-string $resourceClass
     * @param QueryBuilder $queryBuilder
     * @param string|null $ownerProperty
     * @return void
     */
    public function limitGetCollection(string $resourceClass, QueryBuilder $queryBuilder, ?string $ownerProperty = null): void
    {

        $highestGrantType = $this->calculateFinalGrantType($resourceClass, self::COL_GET);

        if (GrantType::NONE === $highestGrantType) {
            throw new AccessDeniedException(self::ACCESS_DENIED_MESSAGE);
        }

        if (GrantType::OWN === $highestGrantType) {
            if (!$ownerProperty) {
                throw new \RuntimeException('GrantType::OWN but $ownerProperty not set at ' . __CLASS__);
            }
            $user = $this->security->getUser();
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->andWhere(sprintf('%s.%s = :current_user', $rootAlias, $ownerProperty))
                ->setParameter('current_user', $user->getId());
        }
    }


    /**
     * Return allowed (All, Own, None) operations per resource.
     * @param ?string[] $forcedRoles
     * @return array<int, array<string, array<string, GrantType>>>.
     * @throws \ReflectionException
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
                    self::ITEM_WRITE => $this->calculateFinalGrantType($resource, self::ITEM_WRITE, $forcedRoles),
                ]
            ];
        }
        return $output;
    }

    /**
     * @param array<int, mixed>|null $menu
     * @return array<int, array{name: string, children: array<int, mixed>}>
     */
    public function limitMenuStructure(?array $menu = null, int $parent = 0): array
    {
        if (empty($this->menuStructure)) {
            throw new \RuntimeException(__CLASS__ . " must be configured by AuthorizationServiceConfigurator. Menu structure permissions not set.");
        }
        $i = 1;
        return array_values(array_filter(array_map(function ($menuItem) use ($parent, &$i) {
            $included = false; // Decision if menuItem will be included in final output

            if (array_key_exists('mainResource', $menuItem)) {
                $grantType = $this->calculateFinalGrantType($menuItem['mainResource'], self::COL_GET);
                $included = (in_array($grantType, [GrantType::OWN, GrantType::ALL], true));
            }
            if (!$included && array_key_exists('roles', $menuItem)) {
                $user = $this->security->getUser();
                $included = (0 < count(array_intersect($user?->getRoles(), $menuItem['roles'])));
            }

            $childrenMenu = [];
            if (!empty($menuItem['children'])) {
                $childrenMenu = $this->limitMenuStructure($menuItem['children'], $i);
                $included = !empty($childrenMenu);
            }

            return $included ? [
                'id' => $parent * 100 + $i++,
                'name' => $menuItem['name'],
                'children' => $childrenMenu,
            ] : null;
        }, $menu ?? $this->menuStructure)));
    }


    /**
     * Find an operation type (col-get, col-post, item-get, item-write (item-put, item-delete, item-patch)) from Api Platform context variable.
     * @param array<string, mixed> $context
     * @return string|null
     */
    private function getOperationfromContext(array $context): ?string
    {
        $operationType = array_key_exists('item_operation_name', $context) ? 'item' : null;
        $operationType = array_key_exists('collection_operation_name', $context) ? 'collection' : $operationType;

        if ('item' === $operationType) {
            $operation = match ($context['item_operation_name']) {
                'get' => self::ITEM_GET,
                'delete', 'patch', 'put' => self::ITEM_WRITE,
                default => null,
            };
        } else if ('collection' === $operationType) {
            $operation = match ($context['collection_operation_name']) {
                'get' => self::COL_GET,
                'post' => self::COL_POST,
                default => null,
            };
        } else {
            return null;
        }
        return $operation;
    }

    /**
     * Calculate the highest grant level based on Resource permissions for specific operation merged with ALL.
     * @param class-string $resourceClass
     * @param string $operation
     * @param string[]|null $forceRoles
     * @return GrantType
     */
    private function calculateFinalGrantType(string $resourceClass, string $operation, ?array $forceRoles = null): GrantType
    {
        if (empty($this->resources)) {
            throw new \RuntimeException(__CLASS__ . " must be configured by AuthorizationServiceConfigurator. Resource permissions not set.");
        }
        $user = $this->security->getUser();
        if (null === $user) {
            throw new AccessDeniedException(self::ACCESS_DENIED_MESSAGE);
        }
        $availableRoles = $forceRoles ?: $user->getRoles();

        $allowedRoles = array_merge($this->resources[$resourceClass][$operation], $this->resources[$resourceClass][self::ALL]);
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
     * @throws \ReflectionException
     */
    private function extractClassName(string $FQCN): string
    {
        return (new \ReflectionClass($FQCN))->getShortName();
    }

    /**
     * @param string $property
     * @return string
     */
    private function makeGetter(string $property): string
    {
        return 'get' . ucfirst($property);
    }
}
