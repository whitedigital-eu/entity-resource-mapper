<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Filters;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\PropertyHelperTrait;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use BackedEnum;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use WhiteDigital\EntityResourceMapper\Filters\Traits\PropertyNameNormalizer;

class ValueInFilter extends AbstractFilter
{
    use PropertyHelperTrait;
    use PropertyNameNormalizer;

    private readonly PropertyAccessor $accessor;

    public function getDescription(string $resourceClass): array
    {
        return [];
    }

    /**
     * @throws ReflectionException
     */
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (
            !is_array($value)
            || !array_key_exists('in', $value)
            || !$this->isPropertyEnabled($property, $resourceClass)
        ) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $field = $property;

        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $queryNameGenerator, $resourceClass, Join::INNER_JOIN);
        }

        $values = explode(',', $value['in']);

        $reflection = new ReflectionClass($resourceClass);
        $instance = $reflection->newInstance();

        if (!$reflection->getProperty($field)->getType()->isBuiltin()) {
            $refType = new ReflectionClass($reflection->getProperty($field)->getType()->getName());

            if ($refType->implementsInterface(BackedEnum::class)) {
                $values = array_map(function (string $value) use ($instance, $field) {
                    /* @noinspection PhpUndefinedMethodInspection */
                    self::getEnumClassFromProperty($instance, $field)::from($value);

                    return $value;
                }, $values);
            }
        }

        $queryBuilder->andWhere($queryBuilder->expr()->in(sprintf('%s.%s', $alias, $field), $values));
    }

    private static function getEnumClassFromProperty(object $object, string $propertyName): ?string
    {
        try {
            $reflectionProperty = new ReflectionProperty($object, $propertyName);
            $type = $reflectionProperty->getType();
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                return null;
            }

            $typeName = $type->getName();
            if (!enum_exists($typeName)) {
                return null;
            }

            return $typeName;
        } catch (ReflectionException) {
            return null;
        }
    }
}
