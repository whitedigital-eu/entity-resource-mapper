<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Filters;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;
use WhiteDigital\EntityResourceMapper\Mapper\AccessClassMapperTrait;

use function array_key_exists;
use function is_array;
use function sprintf;

class ResourceUuidFilter extends AbstractFilter
{
    use AccessClassMapperTrait;
    use Traits\PropertyNameNormalizer;

    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->properties as $property => $unused) {
            $description[$property] = [
                'property' => $this->normalizePropertyName($property),
                'type' => 'string',
                'required' => false,
                'description' => 'Filter by UUID',
                'openapi' => [
                    'example' => 'id=550e8400-e29b-41d4-a716-446655440000',
                ],
            ];
        }

        return $description;
    }

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

    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (
            is_array($value)
            || !$this->isPropertyEnabled($property, $resourceClass)
            || !Uuid::isValid($value)
        ) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $field = $property;

        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $queryNameGenerator, $resourceClass, Join::INNER_JOIN);
        }

        $queryBuilder->andWhere(sprintf('%s.%s = :uuid', $alias, $field))
            ->setParameter('uuid', Uuid::fromString($value));
    }
}
