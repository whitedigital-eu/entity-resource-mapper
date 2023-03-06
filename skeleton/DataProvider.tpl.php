<?php echo "<?php declare(strict_types = 1);\n"; ?>

namespace <?php echo $namespace; ?>;

use ApiPlatform\Exception\ResourceClassNotFoundException;
use <?php echo $resource->getFullName() . ";\n"; ?>
use ReflectionException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use WhiteDigital\EntityResourceMapper\DataProvider\AbstractDataProvider;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;

final class <?php echo $class_name; ?> extends AbstractDataProvider
{
    /**
     * @throws ExceptionInterface
     * @throws ResourceClassNotFoundException
     * @throws ReflectionException
     */
    protected function createResource(BaseEntity $entity, array $context): <?php echo $resource->getShortName() . "\n"; ?>
    {
        return <?php echo $resource->getShortName(); ?>::create($entity, $context);
    }
}
