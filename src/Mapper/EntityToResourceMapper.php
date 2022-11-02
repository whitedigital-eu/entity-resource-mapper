<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Mapper;

use ApiPlatform\Exception\ResourceClassNotFoundException;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\Proxy;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use WhiteDigital\EntityResourceMapper\Attribute\SkipCircularReferenceCheck;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;
use WhiteDigital\EntityResourceMapper\Security\Attribute\AuthorizeResource;
use WhiteDigital\EntityResourceMapper\Security\AuthorizationService;

class EntityToResourceMapper
{
    public const PARENT_CLASSES = 'parent_classes';
    public const ROOT_ENTITY_RECEIVED = 'ROOT_ENTITY_RECEIVED';

    public function __construct(
        private readonly ClassMapper                                $classMapper,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly AuthorizationService                       $authorizationService,
    )
    {
        BaseResource::setEntityToResourceMapper($this);
    }

    /**
     * Entity to ApiResource mapper
     * 1) uses $context[self::MAPPED_CLASSES] to identify respective DTO class
     * 2) automatically handles circular references by skipping elements if they are already listed in parent classes:
     * (in_array($targetClass, $context[self::PARENT_CLASSES], true))
     * 3) Loads child elements only if required by normalization_groups
     *
     * @param BaseEntity $object
     * @param array<string, mixed> $context
     * @return BaseResource
     * @throws ExceptionInterface
     * @throws ResourceClassNotFoundException|\ReflectionException
     */
    public function map(BaseEntity $object, array $context = []): BaseResource
    {
        $isRootEntity = $this->checkIfRootEntityReceived($context);

        $reflection = $this->loadReflection($object);

        $targetResourceClass = $this->classMapper->byEntity($reflection->getName());

        $this->addElementIfNotExists($context[self::PARENT_CLASSES], $targetResourceClass);

        $properties = $reflection->getProperties();
        $output = new $targetResourceClass();

        // Skip normalization if user has no permissions on current entity
        $resourceReflection = new \ReflectionClass($targetResourceClass);
        $visibleProperties = [];
        $normalizeForAuthorization = [];
        if (!empty($authorize = $resourceReflection->getAttributes(AuthorizeResource::class))) {
            if (!$this->authorizationService->authorizeSingleObject($object, AuthorizationService::ITEM_GET, false)) {
                $visibleProperties = $authorize[0]->getArguments()['visibleProperties'] ?? [];
                $this->setResourceProperty($output, 'id', $object->getId());
                $this->setResourceProperty($output, 'isRestricted', true);
                if (empty($visibleProperties)) {
                    return $output;
                }
            }
            // if AuthorizeResource includes nested properties (like email.document.owner), they need to be normalized for later use in authorizeSingleObject()
            $this->splitNestedProperties($authorize[0]->getArguments()['ownerProperty'] ?? null, $normalizeForAuthorization);
            $this->splitNestedProperties($authorize[0]->getArguments()['groupProperty'] ?? null, $normalizeForAuthorization);
        }

        foreach ($properties as $property) {
            $propertyName = $property->getName();

            // If authorization service limited access to the resource but some properties must remain visible
            if (!empty($visibleProperties) && !in_array($propertyName, $visibleProperties, true)) {
                continue;
            }

            /** @phpstan-ignore-next-line */
            $propertyType = $property->getType()?->getName();
            if (null === $propertyType) {
                throw new \RuntimeException("Type for property $propertyName on class {$reflection->getName()} cannot be detected. Forgot to add it?");
            }
            try {
                // Use getter instead of reflection
                $getterName = 'get' . ucfirst($propertyName);
                $propertyValue = $object->{$getterName}();
            } catch (\Error $e) {
                $propertyValue = null;
            }
            if (null === $propertyValue) {
                continue;
            }

            // 1. Ignore Entity property, if it has #[Ignore] attribute
            if (!empty($property->getAttributes(Ignore::class))) {
                continue;
            }

            // 2A. Normalize relations for Collection<Entity> property
            if ($propertyType === Collection::class) {
                $this->setResourceProperty($output, $propertyName, []);
                // Do not initialize lazy relation (with $propertyValue->getValues()) if not needed
                /** @var  PersistentCollection $propertyValue */
                $collectionElementType = $propertyValue->getTypeClass()->getName();
                $targetClass = $this->classMapper->byEntity($collectionElementType);
                $targetNormalizationGroups = $this->getNormalizationGroups($targetClass, []);
                $propertyNormalizationGroup = $this->getResourcePropertyNormalizationGroups($resourceReflection, $propertyName);
                if (array_key_exists('groups', $context)
                    && !$this->haveCommonElements($propertyNormalizationGroup, $context['groups'])
                    && !$this->haveCommonElements($targetNormalizationGroups, $context['groups'])
                    && !in_array($propertyName, $normalizeForAuthorization, true)
                ) {
                    continue;
                }
                if ($this->isCircularReference($isRootEntity, $targetClass, $context, $resourceReflection, $propertyName)) {
                    continue;
                }
                foreach ($propertyValue->getValues() as $value) { // Doctrine lazy loading happens here
                    $this->setResourceProperty($output, $propertyName, $this->map($value, $context), true);
                }
                continue;
            }

            // 2B. Normalize relations for Entity property
            if ($this->isPropertyBaseEntity($propertyType)) {
                $targetClass = $this->classMapper->byEntity($propertyType);
                $targetNormalizationGroups = $this->getNormalizationGroups($targetClass, []);
                $propertyNormalizationGroup = $this->getResourcePropertyNormalizationGroups($resourceReflection, $propertyName);
                if (array_key_exists('groups', $context)
                    && !$this->haveCommonElements($propertyNormalizationGroup, $context['groups'])
                    && !$this->haveCommonElements($targetNormalizationGroups, $context['groups'])
                    && !in_array($propertyName, $normalizeForAuthorization, true)
                ) {
                    continue;
                }
                if ($this->isCircularReference($isRootEntity, $targetClass, $context, $resourceReflection, $propertyName)) {
                    continue;
                }
                $this->setResourceProperty($output, $propertyName, $this->map($propertyValue, $context));
                continue;
            }

            // 3. Finally, map output value to input
            $this->setResourceProperty($output, $propertyName, $propertyValue);

        }
        return $output;
    }

    /**
     * Instantiate PHP reflection and initialize lazy relations behind Doctrine Proxy objects
     * @param object $object
     * @return \ReflectionClass<BaseEntity>
     */
    private function loadReflection(object $object): \ReflectionClass
    {
        $reflection = new \ReflectionClass($object);
        if ($object instanceof Proxy) { //get real object behind Doctrine proxy object
            $object->__load(); //try to initialize LAZY relations
            if (!$object->__isInitialized()) {
                throw new \RuntimeException('Un-initialized proxy object received for EntityNormalizer.');
            }
            $reflection = $reflection->getParentClass();
        }
        return $reflection;
    }

    /**
     * Checks, if given object is inherited from BaseEntity class.
     * @param string $class
     * @return bool
     */
    private function isPropertyBaseEntity(string $class): bool
    {
        // TODO Can we use is_subclass_of instead?
        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException) {
            return false; // Property is not a (known) class
        }
        while ($reflection = $reflection->getParentClass()) {
            if ($reflection->getName() === BaseEntity::class) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add element to array by reference, if value doesn't exist
     * @param array<string, mixed>|null $array
     * @param mixed $element
     * @return void
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
     * @param class-string $resourceClass
     * @param array<string, mixed> $context
     * @return array<string>
     * @throws ResourceClassNotFoundException
     */
    private function getNormalizationGroups(string $resourceClass, array $context): array
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
     * Check if two arrays have common elements
     * @param array<string, mixed> $array1
     * @param array<string, mixed> $array2
     * @return bool
     */
    private function haveCommonElements(array $array1, array $array2): bool
    {
        return count(array_intersect($array1, $array2)) > 0;
    }

    /**
     * @param BaseResource $resource
     * @param string $propertyName
     * @param mixed $propertyValue
     * @param bool $appendArray
     * @return void
     */
    private function setResourceProperty(BaseResource $resource, string $propertyName, mixed $propertyValue, bool $appendArray = false): void
    {
        if (!property_exists($resource::class, $propertyName)) {
            throw new \RuntimeException(sprintf('Property %s does not exist in class %s', $propertyName, $resource::class));
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
     * Parse AuthorizeResource attributes and return all nested properties.
     * For example, document.owner will return ['document', 'owner']
     * @param string|null $attributeValue
     * @param string[] $output
     * @return void
     */
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

    /**
     * Check if root entity has been received, required for circular reference management
     * @param array<string, mixed> $context
     */
    private function checkIfRootEntityReceived(array &$context): bool
    {
        if (isset($context[self::ROOT_ENTITY_RECEIVED])) {
            return false;
        }
        $this->addElementIfNotExists($context[self::ROOT_ENTITY_RECEIVED], true);
        return true;
    }

    /**
     * Checks of reference to another api resource is circular
     *
     * @param class-string $targetClass
     * @param array<string, mixed> $context
     * @param \ReflectionClass<BaseResource> $resourceReflection
     *
     * @throws \ReflectionException
     */
    private function isCircularReference(
        bool             $isRootEntity,
        string           $targetClass,
        array            $context,
        \ReflectionClass $resourceReflection,
        string           $propertyName
    ): bool
    {
        return in_array($targetClass, $context[self::PARENT_CLASSES], true)
            && (!$isRootEntity || empty($resourceReflection->getProperty($propertyName)->getAttributes(SkipCircularReferenceCheck::class)));
    }

    /**
     * @param \ReflectionClass<BaseResource> $reflection
     * @param string $propertyName
     * @return string[]
     * @throws \ReflectionException
     */
    private function getResourcePropertyNormalizationGroups(\ReflectionClass $reflection, string $propertyName): array
    {
        $property = $reflection->getProperty($propertyName);
        $groupAttributes = $property->getAttributes(Groups::class);
        if (1 === count($groupAttributes)) {
            return $groupAttributes[0]->getArguments()[0];
        }
        return [];
    }
}
