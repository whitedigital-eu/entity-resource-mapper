<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Security;

use Closure;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\Proxy;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Mapper\ClassMapper;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;
use WhiteDigital\EntityResourceMapper\Security\Attribute\AccessResolverConfiguration;
use WhiteDigital\EntityResourceMapper\Security\Attribute\AuthorizeResource;
use WhiteDigital\EntityResourceMapper\Security\Attribute\VisibleProperty;
use WhiteDigital\EntityResourceMapper\Security\Enum\GrantType;
use WhiteDigital\EntityResourceMapper\Security\Interface\AccessResolverInterface;

/**
 * Data used in following places:
 * - 1. Menu structure generator - outputs menu items available by current role
 * - 2. Data provider - limit collection get output
 * - 3. Data provider - authorize item get output
 * - 4. data persister - authorize collection POST
 * - 5. data persister - authorize item PUT/PATCH
 * - 6. data persister - authorize item DELETE
 * - 7. Check individual resources in EntityToResourceMapper.
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

    public const OPERATIONS = [
        self::COL_GET,
        self::COL_POST,
        self::ITEM_GET,
        self::ITEM_PATCH,
        self::ITEM_DELETE,
    ];

    public const ACCESS_DENIED_MESSAGE = 'access_denied';

    /** @var array<class-string, array<string, mixed>> */
    private array $resources = [];

    /**
     * If closure returns true, authorization system will be disabled (return GrantType::ALL).
     */
    private ?Closure $authorizationOverride = null;

    private array $requiredRoles;

    public function __construct(
        private readonly Security $security,
        private readonly ClassMapper $classMapper,
        private readonly TranslatorInterface $translator,
        #[TaggedLocator(tag: 'authorization.access_resolver')]
        private readonly ServiceLocator $accessResolverRepository,
        ParameterBagInterface $bag,
    ) {
        $this->requiredRoles = $bag->get('whitedigital.entity_resource_mapper.roles');
    }

    /**
     * @param array<class-string, array{ all: array<string, GrantType>, collection-get: array<string, GrantType>, collection-post: array<string, GrantType>, item-get: array<string, GrantType>, item-patch: array<string, GrantType>, item-delete: array<string, GrantType>}> $resources
     */
    public function setResources(array $resources): void
    {
        if ([] !== $this->requiredRoles) {
            foreach ($resources as $class => $resource) {
                foreach (self::OPERATIONS as $operation) {
                    $this->validateAllRolesSet(array_keys(array_merge($resource[$operation] ?? [], $resource[self::ALL])), $class, $operation);
                }
            }
        }

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
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function authorizeSingleObject(
        BaseEntity|BaseResource $object,
        string $operation,
        bool $throwException = true,
        ?GrantType $forcedGrantType = null,
        array $context = [],
    ): bool {
        if (null !== $this->authorizationOverride && ($this->authorizationOverride)($object)) {
            return true;
        }
        $accessDecision = false;
        $resourceClass = $this->getAuthorizableObjectResourceClassname($object, $context);
        $highestGrantType = $forcedGrantType ?? $this->calculateFinalGrantType($resourceClass, $operation);
        if (GrantType::ALL === $highestGrantType) {
            return true;
        }
        if (GrantType::LIMITED === $highestGrantType) {
            if (self::COL_POST === $operation) {
                return true;
            }
            $accessDecision = $this->isObjectAuthorizedForUser($resourceClass, $object);
        }
        if ($throwException && !$accessDecision) {
            throw new AccessDeniedException($this->translator->trans(id: self::ACCESS_DENIED_MESSAGE, domain: 'EntityResourceMapper'));
        }

        return $accessDecision;
    }

    /**
     * @param class-string $resourceClass
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function limitGetCollection(
        string $resourceClass,
        QueryBuilder $queryBuilder,
        ?GrantType $forceGrantType = null,
    ): QueryBuilder {
        if (null !== $this->authorizationOverride && ($this->authorizationOverride)()) {
            return $queryBuilder;
        }
        $highestGrantType = $forceGrantType ?? $this->calculateFinalGrantType($resourceClass, self::COL_GET);
        if (GrantType::ALL === $highestGrantType) {
            return $queryBuilder;
        }
        if (GrantType::NONE === $highestGrantType) {
            throw new AccessDeniedException($this->translator->trans(id: self::ACCESS_DENIED_MESSAGE, domain: 'EntityResourceMapper'));
        }
        if (GrantType::LIMITED === $highestGrantType) {
            $accessResolverConfigList = $this->retrieveAccessResolverConfigList($resourceClass);
            $accessResolverApplied = false;
            if ($accessResolverConfigList) {
                foreach ($accessResolverConfigList as $accessResolverConfig) {
                    $accessResolver = $this->accessResolverRepository->get($accessResolverConfig->getClassName());
                    if ($accessResolver instanceof AccessResolverInterface) {
                        $accessResolver->limitCollectionQuery($accessResolverConfig, $queryBuilder);
                        $accessResolverApplied = true;
                    }
                }
            }
            if (!$accessResolverApplied) {
                $this->throwIfNoVisibilityAttributeSet($resourceClass);
            }
        }

        return $queryBuilder;
    }

    /**
     * Return allowed (All, Own, None) operations per resource.
     *
     * @param string[]|null $forcedRoles
     *
     * @return array<int, array<string, array<string, GrantType>>>
     *
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
     *
     * @param class-string  $resourceClass
     * @param string[]|null $forceRoles
     */
    public function calculateFinalGrantType(
        string $resourceClass,
        string $operation,
        ?array $forceRoles = null,
    ): GrantType {
        if (empty($this->resources)) {
            return GrantType::ALL;
        }

        if (!array_key_exists($resourceClass, $this->resources)) {
            throw new RuntimeException("Resource $resourceClass not configured in AuthorizationService.");
        }

        $user = $this->security->getUser();
        if (null === $user) {
            throw new AccessDeniedException($this->translator->trans(id: self::ACCESS_DENIED_MESSAGE, domain: 'EntityResourceMapper'));
        }
        $availableRoles = $forceRoles ?: $user->getRoles();

        $allowedRoles = array_merge($this->resources[$resourceClass][$operation] ?? [],
            $this->resources[$resourceClass][self::ALL]);

        // IF OPERATION DOESN'T EXIST OR ROLE DOESN'T EXIST IN RESOURCE return NONE
        $highestGrantType = GrantType::NONE;
        foreach ($allowedRoles as $role => $grantType) {
            if (in_array($role, $availableRoles, true)) {
                $highestGrantType = $this->elevateGrantType($highestGrantType, $grantType);
            }
        }

        return $highestGrantType;
    }

    private function validateAllRolesSet(array $allowedRoles, string $class, string $operation): void
    {
        if ([] !== ($missing = array_diff($this->requiredRoles, $allowedRoles))) {
            throw new InvalidConfigurationException($this->translator->trans('not_all_roles_configured', ['CLASS' => $class, 'OPERATION' => $operation, 'GIVEN' => implode(', ', $allowedRoles), 'MISSING' => implode(', ', $missing)], 'EntityResourceMapper'));
        }
    }

    private function getAuthorizableObjectResourceClassname(BaseResource|BaseEntity $object, array $context = []): string
    {
        if ($object instanceof BaseEntity) {
            $reflection = new ReflectionClass($object);
            if ($object instanceof Proxy) { // get real object behind Doctrine proxy object
                $reflection = $reflection->getParentClass();
            }

            return $this->classMapper->byEntity($reflection->getName(), context: $context);
        }

        return $object::class;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function isObjectAuthorizedForUser(string $resourceClass, object $object): bool
    {
        $accessResolverConfigList = $this->retrieveAccessResolverConfigList($resourceClass);
        if ($accessResolverConfigList) {
            foreach ($accessResolverConfigList as $accessResolverConfig) {
                $accessResolver = $this->accessResolverRepository->get($accessResolverConfig->getClassName());
                if (!$accessResolver instanceof AccessResolverInterface) {
                    throw new InvalidArgumentException('Tagged access resolvers must implement ' . AccessResolverInterface::class);
                }
                if (!$accessResolver->isObjectAccessGranted($accessResolverConfig, $object)) {
                    return false;
                }
            }

            return true;
        }

        $this->throwIfNoVisibilityAttributeSet($resourceClass);

        return false;
    }

    private function throwIfNoVisibilityAttributeSet(string $resourceClass): void
    {
        try {
            $reflection = new ReflectionClass($resourceClass);
            if ([] === $reflection->getAttributes(VisibleProperty::class)) {
                throw new InvalidConfigurationException(sprintf('For GrantType::%s, at least one %s or %s attribute must be set', GrantType::LIMITED->value, AuthorizeResource::class, VisibleProperty::class));
            }
        } catch (ReflectionException) {
        }
    }

    private function elevateGrantType(GrantType $currentGrantType, GrantType $expectedGrantType): GrantType
    {
        $order = [
            GrantType::NONE->value => 10,
            GrantType::LIMITED->value => 20,
            GrantType::ALL->value => 30,
        ];
        if ($order[$expectedGrantType->value] > $order[$currentGrantType->value]) {
            return $expectedGrantType;
        }

        return $currentGrantType;
    }

    /**
     * Extract class name from FQCN.
     *
     * @throws ReflectionException
     */
    private function extractClassName(string $FQCN): string
    {
        return (new ReflectionClass($FQCN))->getShortName();
    }

    /**
     * @return AccessResolverConfiguration[]|null
     */
    private function retrieveAccessResolverConfigList(string $resourceClass): ?array
    {
        try {
            $reflection = new ReflectionClass($resourceClass);
        } catch (ReflectionException) {
            return null;
        }
        $resourceAuthAttribute = $reflection->getAttributes(AuthorizeResource::class)[0] ?? null;
        if ($resourceAuthAttribute) {
            $attributeInstance = $resourceAuthAttribute->newInstance();
            if ($attributeInstance instanceof AuthorizeResource) {
                return $attributeInstance->getAccessResolvers();
            }
        }

        return null;
    }
}
