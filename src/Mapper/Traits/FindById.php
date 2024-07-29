<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Mapper\Traits;

use Symfony\Component\Uid\Uuid;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;

use function is_string;

trait FindById
{
    protected function findById(string $class, mixed $id): ?BaseEntity
    {
        // count will not load actual entity, only checks for id in database
        $count = $this->entityManager->getRepository($class)->count(['id' => $id]);
        if (0 === $count) {
            return null;
        }

        // UUID check must be done as reference expects actual data type of id
        if (is_string($id) && Uuid::isValid($id)) {
            $id = Uuid::fromString($id);
        }

        /*
         * Improves speed if find is only used to check if entity exists or to add it to other object / collection
         * Actual load of data will be triggered if any other field other than id is accessed
         */
        return $this->entityManager->getReference($class, $id);
    }
}
