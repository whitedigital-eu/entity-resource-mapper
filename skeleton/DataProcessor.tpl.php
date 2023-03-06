<?php echo "<?php declare(strict_types = 1);\n"; ?>

namespace <?php echo $namespace; ?>;

use ApiPlatform\Exception\ResourceClassNotFoundException;
use <?php echo $resource->getFullName() . ";\n"; ?>
use <?php echo $entity->getFullName() . ";\n"; ?>
use ReflectionException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use WhiteDigital\EntityResourceMapper\DataProcessor\AbstractDataProcessor;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;

final class <?php echo $class_name; ?> extends AbstractDataProcessor
{
    public function getEntityClass(): string
    {
        return <?php echo $entity->getShortName(); ?>::class;
    }

    protected function createEntity(BaseResource $resource, array $context, ?BaseEntity $existingEntity = null): <?php echo $entity->getShortName() . "\n"; ?>
    {
        return <?php echo $entity->getShortName(); ?>::create($resource, $context, $existingEntity);
    }

    /**
     * @throws ExceptionInterface
     * @throws ReflectionException
     * @throws ResourceClassNotFoundException
     */
    protected function createResource(BaseEntity $entity, array $context): <?php echo $resource->getShortName() . "\n"; ?>
    {
        return <?php echo $resource->getShortName(); ?>::create($entity, $context);
    }
}
