<?php

namespace WhiteDigital\EntityDtoMapper\Serializer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use WhiteDigital\EntityDtoMapper\Dto\BaseDto;
use WhiteDigital\EntityDtoMapper\Entity\BaseEntity;
use WhiteDigital\EntityDtoMapper\Mapper\ClassMapper;

class DtoNormalizer
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClassMapper            $classMapper,
    )
    {
    }

    /**
     * Custom Dto normalizer to convert Dto to array for Doctrine entity creation by createFromDto
     * @throws \ReflectionException
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function normalize(BaseDto $object, array $context = []): array
    {
        $reflection = new \ReflectionClass($object);
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

            // 1A. Normalize relations for Array<BaseDto> properties
            //TODO How to distinguish between Dto relation (array) and regular array value? Currently we will use isset($propertyValue[0])
            $nonRelationArray = ('array' === $propertyType) && (null !== $propertyValue) && count($propertyValue) > 0 && !array_key_exists(0, $propertyValue);
            if ('array' === $propertyType && !$nonRelationArray) { //array of entities
                $output[$propertyName] = new ArrayCollection();
                if (null === $propertyValue || 0 === count($propertyValue)) {
                    continue;
                }
                $target_class = $this->classMapper->byDto(get_class($propertyValue[0]), get_class($object)); // assume equal data types in array
                foreach ($propertyValue as $value) {
                    if (isset($value->id)) { //entity already exists, lets fetch it from DB
                        $repository = $this->entityManager->getRepository($target_class);
                        $output[$propertyName]->add($repository->find($value->id));
                        continue;
                    }
                    /** @var BaseEntity $target_class */
                    $output[$propertyName]->add($target_class::createFromNormalizedDto($value));
                }
                continue;
            }

            // 1B. Normalize relations for BaseDto properties
            if ($this->isPropertyBaseDto($propertyType)) {
                $target_class = $this->classMapper->byDto($propertyType);
                if (isset($propertyValue->id)) { //entity already exists, lets fetch it from DB
                    $repository = $this->entityManager->getRepository($target_class);
                    $output[$propertyName] = $repository->find($propertyValue->id);
                    continue;
                }
                if (null !== $propertyValue) { // Null property will be set in step 2.
                    /** @var BaseEntity $target_class */
                    $output[$propertyName] = $target_class::createFromNormalizedDto($propertyValue);
                    continue;
                }
            }

            // 2. Finally, map output value to input
            $output[$propertyName] = $propertyValue;

        }
        return $output;
    }

    /**
     * Checks, if given object is inherited from BaseDto class.
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
            if ($reflection->getName() === BaseDto::class) {
                return true;
            }
        }
        return false;
    }
}
