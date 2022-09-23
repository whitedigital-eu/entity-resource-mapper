<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Filters;

use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;

/**
 * Order filter which will order by custom SELECT fields, which are not included in root alias nor joins.
 */
class ResourceOrderCustomFilter extends AbstractFilter
{
    private string $orderParameterName = 'order';

    /**
     * @param string $property
     * @param mixed $value
     * @param QueryBuilder $queryBuilder
     * @param QueryNameGeneratorInterface $queryNameGenerator
     * @param string $resourceClass
     * @param string|Operation|null $operation
     * @param array|null $context
     * @return void
     */
    protected function filterProperty(string $property, mixed $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string|Operation $operation = null, array $context = null): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $propertyName => $direction) {
            if (array_key_exists($propertyName, $this->properties)) {
                $queryBuilder->addOrderBy($propertyName, $direction);
            }
        }
    }

    /**
     * @param string $resourceClass
     * @return array<string, mixed>
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->properties as $property => $options) {
            $propertyName = $this->normalizePropertyName($property);
            $description[sprintf('%s[%s]', $this->orderParameterName, $propertyName)] = [
                'property' => $propertyName,
                'type' => 'string',
                'description' => $options['description'] ?? null,
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'enum' => [
                        strtolower(OrderFilterInterface::DIRECTION_ASC),
                        strtolower(OrderFilterInterface::DIRECTION_DESC),
                    ],
                ],
            ];
        }

        return $description;
    }

}
