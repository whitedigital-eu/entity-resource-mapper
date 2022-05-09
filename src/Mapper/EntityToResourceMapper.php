<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Mapper;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\Proxy;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;

class EntityToResourceMapper
{
    public const PARENT_CLASSES = 'parent_classes';

    public function __construct(
        private readonly ClassMapper $classMapper,
        private readonly ResourceMetadataFactoryInterface $resourceMetadataFactory,
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
     * @param array $context
     * @return BaseResource
     * @throws ExceptionInterface
     * @throws ResourceClassNotFoundException
     */
    public function map(BaseEntity $object, array $context = []): BaseResource
    {
        $reflection = $this->loadReflection($object);

        $dtoClassCurrent = $this->classMapper->byEntity($reflection->getName());
        $this->addElementIfNotExists($context[self::PARENT_CLASSES], $dtoClassCurrent);
        $currentNormalizationGroups = $this->getNormalizationGroups($dtoClassCurrent);

        $properties = $reflection->getProperties();
        $output = new $dtoClassCurrent();
        foreach ($properties as $property) {
            $propertyName = $property->getName();
            $propertyType = $property->getType()?->getName();
            try {
                $propertyValue = $property->getValue($object);
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
                $targetNormalizationGroups = $this->getNormalizationGroups($targetClass);
                if (array_key_exists('groups', $context)
                    && !$this->haveCommonElements($currentNormalizationGroups, $context['groups'])
                    && !$this->haveCommonElements($targetNormalizationGroups, $context['groups'])
                ) {
                    continue;
                }
                if (in_array($targetClass, $context[self::PARENT_CLASSES], true)) {
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
                $targetNormalizationGroups = $this->getNormalizationGroups($targetClass);
                if (array_key_exists('groups', $context)
                    && !$this->haveCommonElements($currentNormalizationGroups, $context['groups'])
                    && !$this->haveCommonElements($targetNormalizationGroups, $context['groups'])
                ) {
                    continue;
                }
                if (in_array($targetClass, $context[self::PARENT_CLASSES], true)) {
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
     * @return \ReflectionClass
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
     * @param array|null $array
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
     * @throws ResourceClassNotFoundException
     */
    private function getNormalizationGroups(string $dtoClass): array
    {
        $resourceMetadata = $this->resourceMetadataFactory->create($dtoClass);
        $normalizationContext = $resourceMetadata->getAttribute('normalization_context');
        if (null === $normalizationContext) {
            return [];
        }
        return $normalizationContext['groups'];
    }

    /**
     * Check if two arrays have common elements
     * @param array $array1
     * @param array $array2
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

}
