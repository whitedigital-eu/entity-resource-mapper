<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Filters;

use ApiPlatform\Doctrine\Common\Filter\RangeFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use WhiteDigital\EntityResourceMapper\Mapper\AccessClassMapperTrait;

final class ResourceRangeFilter implements RangeFilterInterface, FilterInterface
{
    use AccessClassMapperTrait;
    
    /**
     * @param ManagerRegistry $managerRegistry
     * @param LoggerInterface|null $logger
     * @param array<string, mixed>|null $properties
     * @param NameConverterInterface|null $nameConverter
     */
    public function __construct(
        private readonly ManagerRegistry $managerRegistry, 
        private readonly ?LoggerInterface $logger = null, 
        private ?array $properties = null, 
        private readonly ? NameConverterInterface $nameConverter = null
    )
    {
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param QueryNameGeneratorInterface $queryNameGenerator
     * @param string $resourceClass
     * @param string|Operation|null $operation
     * @param array<string, mixed>|null $context
     * @return void
     */
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string|Operation $operation = null, ?array $context = null): void
    {
        foreach ($context['filters'] as $property => $filter) {
            if (!array_key_exists($property, $this->properties)) {
                unset($context['filters'][$property]);
            }
        }
        $resourceClass = $this->classMapper->byResource($resourceClass);
        $rangeFilter = new RangeFilter(
            $this->managerRegistry,
            $this->logger,
            $this->properties,
            $this->nameConverter,
            
        );
        $rangeFilter->apply($queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);
    }

    /**
     * @param string $resourceClass
     * @return array<string, mixed>
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        foreach ($this->properties as $property => $unused) {
            $description += $this->getFilterDescription($property, self::PARAMETER_BETWEEN);
            $description += $this->getFilterDescription($property, self::PARAMETER_GREATER_THAN);
            $description += $this->getFilterDescription($property, self::PARAMETER_GREATER_THAN_OR_EQUAL);
            $description += $this->getFilterDescription($property, self::PARAMETER_LESS_THAN);
            $description += $this->getFilterDescription($property, self::PARAMETER_LESS_THAN_OR_EQUAL);
        }
        return $description;
    }

    /**
     * @param string $fieldName
     * @param string $operator
     * @return array<string, mixed>
     */
    private function getFilterDescription(string $fieldName, string $operator): array
    {
        $propertyName = $this->normalizePropertyName($fieldName);

        return [
            sprintf('%s[%s]', $propertyName, $operator) => [
                'property' => $propertyName,
                'type' => 'string',
                'required' => false,
            ],
        ];
    }

    private function normalizePropertyName(string $property): string
    {
        if (!$this->nameConverter instanceof NameConverterInterface) {
            return $property;
        }

        return implode('.', array_map([$this->nameConverter, 'normalize'], explode('.', (string)$property)));
    }
}
