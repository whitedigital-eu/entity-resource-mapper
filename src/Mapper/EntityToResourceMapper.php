<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Mapper;

use ApiPlatform\Exception\ResourceClassNotFoundException;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\Proxy;
use Error;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use WhiteDigital\EntityResourceMapper\Attribute\SkipCircularReferenceCheck;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;
use WhiteDigital\EntityResourceMapper\Security\Attribute\VisibleProperty;
use WhiteDigital\EntityResourceMapper\Security\AuthorizationService;

use function array_merge;
use function class_exists;

class EntityToResourceMapper
{
    public const PARENT_CLASSES = 'parent_classes';
    public const LEVEL_CURRENT = 'LEVEL_CURRENT'; // used in circular refernce checks

    private ?bool $isOwner = null;

    public function __construct(
        private readonly ClassMapper $classMapper,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly AuthorizationService $authorizationService,
        private readonly Security $security,
    ) {
        BaseResource::setEntityToResourceMapper($this);
    }

    /**
     * Entity to ApiResource mapper
     * 1) uses $context[self::MAPPED_CLASSES] to identify respective DTO class
     * 2) automatically handles circular references by skipping elements if they are already listed in parent classes:
     * (in_array($targetClass, $context[self::PARENT_CLASSES], true))
     * 3) Loads child elements only if required by normalization_groups.
     *
     * @param array<string, mixed> $context
     *
     * @throws ExceptionInterface
     * @throws ReflectionException
     * @throws ResourceClassNotFoundException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function map(BaseEntity $object, array $context = []): BaseResource
    {
        if (!array_key_exists(self::LEVEL_CURRENT, $context)) {
            $context[self::LEVEL_CURRENT] = 0;
        } else {
            $context[self::LEVEL_CURRENT]++;
        }
        $reflection = $this->loadReflection($object);

        $targetResourceClass = $this->classMapper->byEntity($reflection->getName(), context: $context);

        $this->addElementIfNotExists($context[self::PARENT_CLASSES], $targetResourceClass);

        $properties = $reflection->getProperties();
        $output = new $targetResourceClass();

        $resourceReflection = new ReflectionClass($targetResourceClass);

        $visibleProperties = [];
        $normalizeForAuthorization = [];
        if (!empty($authorize = $resourceReflection->getAttributes(VisibleProperty::class))) {
            $ownerProperty = $authorize[0]->getArguments()['ownerProperty'];
            if (!$this->isOwner($object, $ownerProperty)) {
                if (!$this->authorizationService->authorizeSingleObject($object, AuthorizationService::ITEM_GET, false, context: $context)) {
                    $visibleProperties = $authorize[0]->getArguments()['properties'] ?? [];
                    $this->setResourceProperty($output, 'id', $object->getId());
                    $this->setResourceProperty($output, 'isRestricted', true);
                    if (empty($visibleProperties)) {
                        return $output;
                    }
                }
                // if AuthorizeResource includes nested properties (like email.document.owner), they need to be normalized for later use in authorizeSingleObject()
                $this->splitNestedProperties($ownerProperty ?? null, $normalizeForAuthorization);
            }
        }

        foreach ($properties as $property) {
            $propertyName = $property->getName();

            if (!empty($visibleProperties) && false === $this->isOwner && !in_array($propertyName, $visibleProperties, true)) {
                continue;
            }

            /** @phpstan-ignore-next-line */
            $propertyType = $property->getType()?->getName();
            if (null === $propertyType) {
                throw new RuntimeException("Type for property $propertyName on class {$reflection->getName()} cannot be detected. Forgot to add it?");
            }
            try {
                // Use getter instead of reflection
                $getterName = 'get' . ucfirst($propertyName);
                $propertyValue = $object->{$getterName}();
            } catch (Error) {
                $propertyValue = null;
            }
            if (null === $propertyValue) {
                continue;
            }

            $ignores = [];
            if (class_exists(Serializer\Attribute\Ignore::class)) {
                $ignores[] = $property->getAttributes(Serializer\Attribute\Ignore::class);
            }
            if (class_exists(Serializer\Annotation\Ignore::class)) {
                $ignores[] = $property->getAttributes(Serializer\Annotation\Ignore::class);
            }

            $ignores = array_merge(...$ignores);

            // 1. Ignore Entity property, if it has #[Ignore] attribute
            if ([] !== $ignores) {
                continue;
            }

            // 2A. Normalize relations for Collection<Entity> property
            if (Collection::class === $propertyType) {
                $this->setResourceProperty($output, $propertyName, []);
                // Do not initialize lazy relation (with $propertyValue->getValues()) if not needed
                /** @var PersistentCollection $propertyValue */
                $collectionElementType = $propertyValue->getTypeClass()->getName();
                $targetClass = $this->classMapper->byEntity($collectionElementType, context: $context);
                $targetNormalizationGroups = $this->getNormalizationGroups($targetClass);
                $propertyNormalizationGroup = $this->getResourcePropertyNormalizationGroups($resourceReflection, $propertyName);
                if (array_key_exists('groups', $context)
                    && !$this->haveCommonElements($propertyNormalizationGroup, $context['groups'])
                    && !$this->haveCommonElements($targetNormalizationGroups, $context['groups'])
                    && !in_array($propertyName, $normalizeForAuthorization, true)
                ) {
                    continue;
                }
                if ($this->isCircularReference($targetClass, $context, $resourceReflection, $propertyName)) {
                    continue;
                }
                foreach ($propertyValue->getValues() as $value) { // Doctrine lazy loading happens here
                    $contextWithClearedGroups = $this->unsetNormalizationGroups($context, $targetNormalizationGroups);
                    $this->setResourceProperty($output, $propertyName, $this->map($value, $contextWithClearedGroups), true);
                }
                continue;
            }

            // 2B. Normalize relations for Entity property
            if ($this->isPropertyBaseEntity($propertyType)) {
                $targetClass = $this->classMapper->byEntity($propertyType, context: $context);
                $targetNormalizationGroups = $this->getNormalizationGroups($targetClass);
                $propertyNormalizationGroup = $this->getResourcePropertyNormalizationGroups($resourceReflection, $propertyName);
                if (array_key_exists('groups', $context)
                    && !$this->haveCommonElements($propertyNormalizationGroup, $context['groups'])
                    && !$this->haveCommonElements($targetNormalizationGroups, $context['groups'])
                    && !in_array($propertyName, $normalizeForAuthorization, true)
                ) {
                    continue;
                }
                if ($this->isCircularReference($targetClass, $context, $resourceReflection, $propertyName)) {
                    continue;
                }
                $contextWithClearedGroups = $this->unsetNormalizationGroups($context, $targetNormalizationGroups);
                $this->setResourceProperty($output, $propertyName, $this->map($propertyValue, $contextWithClearedGroups));
                continue;
            }

            // 3. Finally, map output value to input
            $this->setResourceProperty($output, $propertyName, $propertyValue);
        }

        return $output;
    }

    protected function isPropertyNested(string $property): bool
    {
        return str_contains($property, '.');
    }

    private function isOwner(BaseEntity $object, string $ownerProperty): bool
    {
        $user = $this->security->getUser();
        if ($this->isPropertyNested($ownerProperty)) {
            [$ownerObject, $ownerProperty,] = explode('.', $ownerProperty, 2);

            return $this->isOwner = $object->{$this->getter($ownerObject)}()->{$this->getter($ownerProperty)}() === $user?->{$this->getter($ownerProperty)}();
        }

        $property = $object->{$this->getter($ownerProperty)}();
        if (!$property instanceof UserInterface) {
            return $this->isOwner = $user?->{$this->getter($ownerProperty)}() === $property;
        }

        return $this->isOwner = $property === $user;
    }

    /**
     * Instantiate PHP reflection and initialize lazy relations behind Doctrine Proxy objects.
     */
    private function loadReflection(object $object): ReflectionClass
    {
        $reflection = new ReflectionClass($object);
        if ($object instanceof Proxy) { // get real object behind Doctrine proxy object
            $object->__load(); // try to initialize LAZY relations
            if (!$object->__isInitialized()) {
                throw new RuntimeException('Un-initialized proxy object received for EntityNormalizer.');
            }
            $reflection = $reflection->getParentClass();
        }

        return $reflection;
    }

    /**
     * Checks, if given object is inherited from BaseEntity class.
     */
    private function isPropertyBaseEntity(string $class): bool
    {
        return is_subclass_of($class, BaseEntity::class);
    }

    /**
     * Add element to array by reference, if value doesn't exist.
     *
     * @param array<string, mixed>|null $array
     */
    private function addElementIfNotExists(?array &$array, mixed $element): void
    {
        if (null === $array) {
            $array = [];
        }
        if (!in_array($element, $array, true)) {
            $array[] = $element;
        }
    }

    /**
     * @param class-string         $resourceClass
     * @param array<string, mixed> $context
     *
     * @return array<string>
     *
     * @throws ResourceClassNotFoundException
     */
    private function getNormalizationGroups(string $resourceClass, array $context = []): array
    {
        $output = [];

        if (array_key_exists('groups', $context)) {
            $output = $context['groups'];
        }

        if (empty($output)) {
            $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);
            if (null !== $normalizationContext = $resourceMetadata->getOperation()->getNormalizationContext()) {
                $output = array_merge($output, $normalizationContext['groups']);
            }
        }

        return $output;
    }

    /**
     * Check if two arrays have common elements.
     *
     * @param array<string, mixed> $array1
     * @param array<string, mixed> $array2
     */
    private function haveCommonElements(array $array1, array $array2): bool
    {
        return count(array_intersect($array1, $array2)) > 0;
    }

    private function setResourceProperty(BaseResource $resource, string $propertyName, mixed $propertyValue, bool $appendArray = false): void
    {
        if (!property_exists($resource::class, $propertyName)) {
            throw new RuntimeException(sprintf('Property %s does not exist in class %s', $propertyName, $resource::class));
        }
        if (null === $propertyValue) {
            return;
        }
        if ($appendArray) {
            $resource->{$propertyName}[] = $propertyValue;
        } else {
            $resource->{$propertyName} = $propertyValue;
        }
    }

    /**
     * Checks of reference to another api resource is circular.
     *
     * @param class-string         $targetClass
     * @param array<string, mixed> $context
     *
     * @throws ReflectionException
     */
    private function isCircularReference(
        string $targetClass,
        array $context,
        ReflectionClass $resourceReflection,
        string $propertyName,
    ): bool {
        $attributes = $resourceReflection->getProperty($propertyName)->getAttributes(SkipCircularReferenceCheck::class);
        $maxLevels = 0;
        if (!empty($attributes)) {
            $maxLevels = $attributes[0]->newInstance()->getMaxLevels();
        }

        return in_array($targetClass, $context[self::PARENT_CLASSES], true)
            && (($maxLevels > 0 && $context[self::LEVEL_CURRENT] >= $maxLevels) || empty($attributes));
    }

    /**
     * @return string[]
     *
     * @throws ReflectionException
     */
    private function getResourcePropertyNormalizationGroups(ReflectionClass $reflection, string $propertyName): array
    {
        $property = $reflection->getProperty($propertyName);
        $groupAttributes = [];

        if (class_exists(Serializer\Annotation\Groups::class)) {
            $groupAttributes = array_merge($groupAttributes, $property->getAttributes(Serializer\Annotation\Groups::class));
        }

        if (class_exists(Serializer\Attribute\Groups::class)) {
            $groupAttributes = array_merge($groupAttributes, $property->getAttributes(Serializer\Attribute\Groups::class));
        }

        if (1 === count($groupAttributes)) {
            return $groupAttributes[0]->getArguments()[0];
        }

        return [];
    }

    private function splitNestedProperties(?string $attributeValue, array &$output): void
    {
        if (null === $attributeValue) {
            return;
        }
        foreach (explode('.', $attributeValue) as $node) {
            if (!in_array($node, $output, true)) {
                $output[] = $node;
            }
        }
    }

    private function getter(string $variable): string
    {
        return sprintf('get%s', ucfirst($variable));
    }

    /**
     * Remove groups from context, if they are not present in target normalization groups.
     * So that mapper does not do unnecessary work.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $targetNormalizationGroups
     *
     * @return array<string, mixed>
     */
    private function unsetNormalizationGroups(array $context, array $targetNormalizationGroups): array
    {
        if (!array_key_exists('groups', $context)) {
            $context['groups'] = [];

            return $context;
        }
        $targetGroups = [];
        foreach ($context['groups'] as $group) {
            if (in_array($group, $targetNormalizationGroups, true)) {
                $targetGroups[] = $group;
            }
        }
        $context['groups'] = $targetGroups;

        return $context;
    }
}
