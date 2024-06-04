<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Filters;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use BackedEnum;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use WhiteDigital\EntityResourceMapper\Mapper\AccessClassMapperTrait;

class ResourceValueInFilter extends AbstractFilter
{
    use AccessClassMapperTrait;
    use Traits\PropertyNameNormalizer;

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        foreach ($context['filters'] as $property => $filter) {
            if (!array_key_exists($property, $this->properties)) {
                unset($context['filters'][$property]);
            }
        }
        $entityClass = $this->classMapper->byResource($resourceClass, context: $context);

        parent::apply($queryBuilder, $queryNameGenerator, $entityClass, $operation, $context);
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->properties as $property => $unused) {
            $description[
            sprintf('%s[in]', $property)] = [
                'property' => $this->normalizePropertyName($property),
                'type' => 'mixed',
                'required' => false,
                'description' => 'Filter by multiple values -> Comma separated values, no spaces between values.',
                'openapi' => [
                    'example' => 'status[in]=DRAFT,RECEIVED, id[in]=1,2,3',
                ],
            ];
        }

        return $description;
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

        if (!$reflection->getProperty($field)->getType()?->isBuiltin()) {
            $refType = new ReflectionClass($reflection->getProperty($field)->getType()?->getName());

            if ($refType->implementsInterface(BackedEnum::class)) {
                $values = array_map(static function (string $value) use ($instance, $field) {
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
            $type = (new ReflectionProperty($object, $propertyName))->getType();
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
