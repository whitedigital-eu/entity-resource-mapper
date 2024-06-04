<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\EventListener;

use DateTime;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use WhiteDigital\EntityResourceMapper\UTCDateTimeImmutable;

#[AsDoctrineListener(Events::prePersist, connection: 'default')]
final class OverrideEventListener
{
    public function __construct(private readonly ParameterBagInterface $bag)
    {
    }

    public function __invoke(PrePersistEventArgs $args): void
    {
        if (!in_array($args->getObject()::class, $this->bag->get('whitedigital.entity_resource_mapper.allow_datetime'), true)) {
            foreach ((new ReflectionClass($args->getObject()))->getProperties() as $property) {
                $type = $property->getType();
                if ($type && DateTime::class === $type->getName()) {
                    throw new InvalidConfigurationException(sprintf('%s is deprecataed, use %s instead in %s::%s', $property->getType()?->getName(), UTCDateTimeImmutable::class, $args->getObject()::class, $property->getName()));
                }
            }
        }
    }
}
