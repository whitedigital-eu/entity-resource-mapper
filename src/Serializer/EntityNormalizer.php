<?php

namespace WhiteDigital\EntityDtoMapper\Serializer;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\PersistentCollection;
use Doctrine\Persistence\Proxy;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use WhiteDigital\EntityDtoMapper\Dto\BaseDto;
use WhiteDigital\EntityDtoMapper\Entity\BaseEntity;
use WhiteDigital\EntityDtoMapper\Mapper\ClassMapper;

class EntityNormalizer
{
    public const PARENT_CLASSES = 'parent_classes';

    private DateTimeNormalizer $dateTimeNormalizer;

    public function __construct(
        private readonly ClassMapper $classMapper,
        private readonly ResourceMetadataFactoryInterface $resourceMetadataFactory,
    )
    {
        //TODO is this correct approach?
        $this->dateTimeNormalizer = new DateTimeNormalizer();
    }

    /**
     * Custom Entity normalizer to convert Entity to array for creating BaseDto class via createFromEntity
     * 1) uses $context[self::MAPPED_CLASSES] to identify respective DTO class
     * 2) automatically handles circular references by skipping elements if they are already listed in parent classes:
     * (in_array($targetClass, $context[self::PARENT_CLASSES], true))
     * 3) Loads child elements only if required by normalization_groups
     *
     * @param BaseEntity $object
     * @param array $context
     * @return array
     * @throws ExceptionInterface
     * @throws ResourceClassNotFoundException
     * @throws \Exception
     */
    public function normalize(BaseEntity $object, array $context = []): array
    {
        $reflection = $this->loadReflection($object);

        $dtoClassCurrent = $this->classMapper->byEntity($reflection->getName());
        $this->addElementIfNotExists($context[self::PARENT_CLASSES], $dtoClassCurrent);
        $currentNormalizationGroups = $this->getNormalizationGroups($dtoClassCurrent);

        $properties = $reflection->getProperties();
        $output = [];
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

            // 2. Normalize Datetime
            if ($propertyType === \DateTimeInterface::class) {
                $output[$propertyName] = $this->dateTimeNormalizer->normalize($propertyValue);
                continue;
            }

            // 3A. Normalize relations for Collection<Entity> property
            if ($propertyType === Collection::class) {
                $output[$propertyName] = [];
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
                foreach ($propertyValue->getValues() as $value) {
                    /** @var BaseDto $targetClass */
                    $normalized = $this->normalize($value, $context);
                    $output[$propertyName][] = $targetClass::createFromNormalizedEntity($normalized);
                }
                continue;
            }

            // 3B. Normalize relations for Entity property
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
                /** @var BaseDto $targetClass */
                $normalized = $this->normalize($propertyValue, $context);
                $output[$propertyName] = $targetClass::createFromNormalizedEntity($normalized);
                continue;
            }

            // 4. Finally, map output value to input
            $output[$propertyName] = $propertyValue;

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
     * @param $array2
     * @return bool
     */
    private function haveCommonElements(array $array1, $array2): bool
    {
        return count(array_intersect($array1, $array2)) > 0;
    }

}
