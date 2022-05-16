<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Filters;

use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\OrderFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use WhiteDigital\EntityResourceMapper\Mapper\AccessClassMapperTrait;

class ResourceOrderFilter implements FilterInterface
{
    use AccessClassMapperTrait;

    /**
     * @param ManagerRegistry $managerRegistry
     * @param RequestStack|null $requestStack
     * @param string $orderParameterName
     * @param LoggerInterface|null $logger
     * @param array<string, mixed>|null $properties
     * @param NameConverterInterface|null $nameConverter
     */
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly ?RequestStack $requestStack = null,
        private readonly string $orderParameterName = 'order',
        private readonly ?LoggerInterface $logger = null,
        private ?array $properties = null,
        private readonly ?NameConverterInterface $nameConverter = null
    )
    {

    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param QueryNameGeneratorInterface $queryNameGenerator
     * @param string $resourceClass
     * @param string|null $operationName
     * @param array<string, mixed> $context
     * @return void
     */
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = []): void
    {
        if (!array_key_exists('order', $context['filters'])) {
            return;
        }
        $resourceClass = $this->classMapper->byResource($resourceClass);
        $property = key($context['filters']['order']);
        $direction = current($context['filters']['order']);
        if (str_contains($property, '->>')) { // Order by json field, for example data->>'created'
            [$field, $jsonAttribute] = explode('->>', $property);
            $alias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->addOrderBy(sprintf("JSON_GET_TEXT(%s.%s,%s)", $alias, $field, $jsonAttribute), $direction);
            return;
        }
        $orderFilter = new OrderFilter(
            $this->managerRegistry,
            $this->requestStack,
            $this->orderParameterName,
            $this->logger,
            $this->properties,
            $this->nameConverter
        );
        $orderFilter->apply($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
    }

    /**
     * @param string $resourceClass
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


    protected function normalizePropertyName(string $property): string
    {
        if (!$this->nameConverter instanceof NameConverterInterface) {
            return $property;
        }

        return implode('.', array_map([$this->nameConverter, 'normalize'], explode('.', (string)$property)));
    }
}