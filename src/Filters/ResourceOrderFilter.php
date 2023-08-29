<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Filters;

use ApiPlatform\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use WhiteDigital\EntityResourceMapper\Mapper\AccessClassMapperTrait;

class ResourceOrderFilter implements FilterInterface
{
    use AccessClassMapperTrait;
    use Traits\PropertyNameNormalizer;

    /**
     * @param array<string, mixed>|null $properties
     */
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly string $orderParameterName = 'order',
        private readonly ?LoggerInterface $logger = null,
        private readonly ?array $properties = null,
        private readonly ?NameConverterInterface $nameConverter = null,
        private readonly ?string $orderNullsComparison = null,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string|Operation|null $operation = null, array $context = []): void
    {
        if (!array_key_exists('order', $context['filters'])) {
            return;
        }
        $entityClass = $this->classMapper->byResource($resourceClass, context: $context);
        $property = key($context['filters']['order']);
        $direction = current($context['filters']['order']);
        if (str_contains($property, '->>')) { // Order by json field, for example data->>'created'
            [$field, $jsonAttribute] = explode('->>', $property);
            $alias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->addOrderBy(sprintf('JSON_GET_TEXT(%s.%s,%s)', $alias, $field, $jsonAttribute), $direction);

            return;
        }
        $orderFilter = new OrderFilter(
            $this->managerRegistry,
            $this->orderParameterName,
            $this->logger,
            $this->properties,
            $this->nameConverter,
            $this->orderNullsComparison,
        );
        $orderFilter->apply($queryBuilder, $queryNameGenerator, $entityClass, $operation, $context);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->properties as $property => $options) {
            if (isset($options['display_name'])) {
                $property = $options['display_name'];
            }
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
