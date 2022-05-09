<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Mapper;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ManyToMany;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\String\Inflector\EnglishInflector;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;

class ResourceToEntityMapper
{
    public const CONDITION_CONTEXT = 'condition_context';

    private \DateTimeZone $timeZone;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClassMapper $classMapper,
    )
    {
        BaseEntity::setResourceToEntityMapper($this);
        // We are using PHP configured timezone (Europe/Riga)
        $this->timeZone = new \DateTimeZone(date_default_timezone_get());
    }

    /**
     * Api Resource mapper to convert Api Resource to Entity (existing or new)
     * @throws \ReflectionException
     * @throws ExceptionInterface
     * @throws  \RuntimeException
     */
    public function map(BaseResource $object, array $context, BaseEntity $existingEntity = null): BaseEntity
    {
        $reflection = new \ReflectionClass($object);
        $targetEntityClass = $this->classMapper->byResource($reflection->getName(), $context[self::CONDITION_CONTEXT] ?? null);

        $properties = $reflection->getProperties();
        $output = $existingEntity ?? new $targetEntityClass();

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            if (!property_exists($output, $propertyName)) {
                continue; // Silently skip properties which do no exist on target class.
            }
            $propertyType = $property->getType()?->getName();
            try {
                $propertyValue = $property->getValue($object);
            } catch (\Error $e) {
                $propertyValue = null;
            }

            //  0. Set correct Timezone, as database does not store TZ info
            if ($propertyType === \DateTimeInterface::class
                && null !== $propertyValue) {
                /** @var \DateTime $propertyValue */
                $propertyValue->setTimezone($this->timeZone);
            }

            // For existing entities, lets not update anything, if value not changed
            if (null !== $existingEntity && $this->compareValues($this->callMethod($output, 'get', $propertyName), $propertyValue)) {
                continue; // No updated needed, skip it
            }

            // 1A. Normalize relations for Array<BaseResource> properties
            if ('array' === $propertyType && $this->isRelationProperty($property)) { //array of entities
                // First, remove all of existing collection values, then add new ones
                foreach ($this->callMethod($output, 'get', $propertyName) as $valueToRemove) {
                    $this->callMethod($output, 'remove', $propertyName, $valueToRemove);
                }
                if (null === $propertyValue || 0 === count($propertyValue)) {
                    continue;
                }
                $targetClass = $this->classMapper->byResource(get_class($propertyValue[0]), get_class($object)); // assume equal data types in array
                foreach ($propertyValue as $value) {
                    if (isset($value->id)) { //entity already exists, lets fetch it from DB
                        $repository = $this->entityManager->getRepository($targetClass);
                        $entity = $repository->find($value->id);
                        if (null === $entity) {
                            throw new \RuntimeException("{$targetClass} entity with id {$value->id} not found!");
                        }
//                        $output[$propertyName]->add($entity);
                        $this->callMethod($output, 'add', $propertyName, $entity);
                        continue;
                    }
                    $this->callMethod($output, 'add', $propertyName, $this->map($value, $context));
                }
                continue;
            }

            // 1B. Normalize relations for BaseResource properties
            /** @var BaseResource $propertyValue */
            if ($this->isPropertyBaseDto($propertyType)) {
                $target_class = $this->classMapper->byResource($propertyType);
                if (isset($propertyValue->id)) { //entity already exists, lets fetch it from DB
                    $repository = $this->entityManager->getRepository($target_class);
                    $entity = $repository->find($propertyValue->id);
                    if (null === $entity) {
                        throw new \RuntimeException("{$target_class} entity with id {$propertyValue->id} not found!");
                    }
                    $this->callMethod($output, 'set', $propertyName, $entity);
                    continue;
                }
                if (null !== $propertyValue) { // Null property will be set in step 2.
                    $this->callMethod($output, 'set', $propertyName, $this->map($propertyValue, $context));
                    continue;
                }
            }

            // 2. Finally, map output value to input
            if (null !== $existingEntity || $propertyValue !== null) { // for existing entities set any value, for new entities only non-null
                $this->callMethod($output, 'set', $propertyName, $propertyValue);
            }

        }
        return $output;
    }

    /**
     * Checks, if given object is inherited from BaseResource class.
     * @param string $class
     * @return bool
     */
    private function isPropertyBaseDto(string $class): bool
    {
        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException) {
            return false; // Property is not a (known) class
        }
        while ($reflection = $reflection->getParentClass()) {
            if ($reflection->getName() === BaseResource::class) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param \ReflectionProperty $property
     * @return bool
     */
    private function isRelationProperty(\ReflectionProperty $property): bool
    {
        $relationAttributes = [ManyToMany::class, ManyToOne::class, OneToMany::class];
        foreach ($property->getAttributes() as $attribute) {
            if (in_array($attribute->getName(), $relationAttributes, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Dynamically call entity->addProperty, removeProperty, setProperty, getProperty
     * @param BaseEntity $object
     * @param string $method
     * @param string $property
     * @param mixed $value
     * @return mixed
     */
    private function callMethod(BaseEntity $object, string $method, string $property, mixed $value = null): mixed
    {
        $property = ucfirst($property);
        $propertySingular = (new EnglishInflector())->singularize($property)[0];
        try {
            return $object->{"{$method}{$propertySingular}"}($value);
        } catch (\Error $e) { // Catch only one type of errors
            if (!str_contains($e->getMessage(), 'Call to undefined method')) {
                throw $e;
            }
            return $object->{"{$method}{$property}"}($value);
        }
    }

    /**
     * @param mixed $value1
     * @param mixed $value2
     * @return bool
     */
    private function compareValues(mixed $value1, mixed $value2): bool
    {
        // Scalar types
        if ($value1 === $value2) {
            return true;
        }
        // \DateTime or \DateTimeImmutable
        if ($value1 instanceof \DateTimeInterface && $value2 instanceof \DateTimeInterface) {
            //TODO Timezones are not equal ??
            return $value1->getTimestamp() === $value2->getTimestamp();
        }
        // Doctrine Collection and array, both empty
        if ($value1 instanceof \Countable && null === $value2) {
            $value2 = [];
        }
        if ($value1 instanceof \Countable && (0 === count($value1) && count($value1) === count($value2))) {
            return true;
        }
        // Doctrine collection and array, both identical
        if ($value1 instanceof PersistentCollection && is_array($value2) && count($value1) === count($value2)) {
            /** @var BaseEntity[] $firstSet */
            $firstSet = $value1->getValues();
            /** @var BaseEntity[] $secondSet */
            $secondSet = $value2;
            $equal = true;
            for ($i = 0, $iMax = count($firstSet); $i < $iMax; $i++) {
                $classesAreEqual = get_class($firstSet[$i]) === $this->classMapper->byResource(get_class($secondSet[$i]), get_class($firstSet[$i]));
                $idsAreEqual = $firstSet[$i]->getId() === $secondSet[$i]->id;
                $equal = $equal && $classesAreEqual && $idsAreEqual;
                if (!$equal) {
                    break;
                }
            }
            if ($equal) {
                return true;
            }
        }
        return false;
    }
}
