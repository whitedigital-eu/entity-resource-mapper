<?php

namespace WhiteDigital\EntityDtoMapper\Entity;


use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\MappedSuperclass;
use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\String\Inflector\EnglishInflector;

#[ORM\HasLifecycleCallbacks]
#[MappedSuperclass]
class BaseEntity
{
    #[ORM\Column(type: 'datetime')]
    protected ?\DateTimeInterface $created = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected ?\DateTimeInterface $updated = null;


    /**
     * @throws \ReflectionException
     * @throws ExceptionInterface
     * @throws \Exception
     */


    public static function createFromNormalizedDto(array $normalized, BaseEntity $existingEntity = null): static
    {
        $newEntity = $existingEntity ?? new static();

        foreach ($normalized as $argName => $argValue) {
            if (!property_exists(static::class, $argName)) {
                throw new \RuntimeException(sprintf('Property %s does not exist in class %s', $argName, static::class));
            }
            if (null !== $existingEntity && $newEntity->compareValues($newEntity->callMethod('get', $argName), $argValue)) {
                continue; // No updated needed, skip it
            }
            if ($argValue instanceof ArrayCollection) {
                if (null !== $existingEntity) { //PATCH/PUT instead of POST, remove all existing relations
                    foreach ($newEntity->callMethod('get', $argName) as $customer) {
                        $newEntity->callMethod('remove', $argName, $customer);
                    }
                }
                foreach ($argValue as $value) {
                    $newEntity->callMethod('add', $argName, $value);
                }
                continue;
            }
            if (null !== $existingEntity || $argValue !== null) { // for existing entities set any value, for new entities only non-null
                $newEntity->callMethod('set', $argName, $argValue);
            }
        }
        return $newEntity;
    }

    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
    }

    public function getUpdated(): ?\DateTimeInterface
    {
        return $this->updated;
    }


    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTime('now');
        $this->created = $now;
        $this->updated = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updated = new \DateTime('now');
    }

    /**
     * Dynamically call entity->addProperty, removeProperty, setProperty, getProperty
     * @param string $property
     * @param mixed $value
     * @param string $method
     * @return mixed
     */
    private function callMethod(string $method, string $property, mixed $value = null): mixed
    {
        $property = ucfirst($property);
        $propertySingular = (new EnglishInflector())->singularize($property)[0];
        try {
            return $this->{"{$method}{$propertySingular}"}($value);
        } catch (\Error $e) { // Catch only one type of errors
            if (!str_contains($e->getMessage(), 'Call to undefined method')) {
                throw $e;
            }
            return $this->{"{$method}{$property}"}($value);
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
        if ($value1 instanceof \Countable && (0 === count($value1) && count($value1) === count($value2))) {
            return true;
        }
        // Doctrine collection and array, both identical
        if ($value1 instanceof  PersistentCollection && $value2 instanceof ArrayCollection && count($value1) === count($value2)) {
            /** @var BaseEntity[] $firstSet */
            $firstSet = $value1->getValues();
            /** @var BaseEntity[] $secondSet */
            $secondSet = $value2->getValues();
            $equal = true;
            for($i = 0; $i < count($firstSet); $i++) {
                $classesAreEqual = get_class($firstSet[$i]) === get_class($secondSet[$i]);
                $idsAreEqual = $firstSet[$i]->getId() === $secondSet[$i]->getId();
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
