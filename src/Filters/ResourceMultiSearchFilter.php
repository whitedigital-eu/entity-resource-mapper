<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Filters;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\QueryBuilder;

class ResourceMultiSearchFilter extends AbstractFilter
{
    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getDescription(string $resourceClass): array
    {
        $properties = $this->getProperties();
        if (null === $properties) {
            throw new Exception(sprintf('Please explicitly mark properties for %s class', self::class));
        }

        $propertyDescriptor = [];
        foreach ($properties as $property => $value) {
            $propertyDescriptor[] = $property;
        }
        $propertyList = implode(',', $propertyDescriptor);

        return [
            'multisearch' => [
                'property' => $propertyList,
                'type' => 'string',
                'required' => false,
                'description' => "Search string value (with ILIKE) in multiple properties ({$propertyList}).",
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $context
     */
    protected function filterProperty(string $property, mixed $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string|Operation $operation = null, ?array $context = null): void
    {
        if ('multisearch' !== $property) {
            return;
        }
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $expressions = [];
        foreach ($this->properties as $field => $v) {
            if (str_contains($field, '.')) { // Handle nested relations
                [$joinTable, $joinField] = explode('.', $field);
                $queryBuilder->leftJoin("{$rootAlias}.{$joinTable}", $joinTable);
                $rootAlias = $joinTable;
                $field = $joinField;
            }
            $aliasedField = "{$rootAlias}.{$field}";
            $valueParameter = ':' . $queryNameGenerator->generateParameterName($field);
            $keyValueParameter = sprintf('%s_%s', $valueParameter, 0);
            $queryBuilder->setParameter($keyValueParameter, $value);
            $expressions[] = $queryBuilder->expr()->like(
                "LOWER({$aliasedField})",
                sprintf('LOWER(%s)', $queryBuilder->expr()->concat("'%'", $keyValueParameter, "'%'")),
            );
        }
        $queryBuilder->andWhere($queryBuilder->expr()->orX(...$expressions));
    }
}
