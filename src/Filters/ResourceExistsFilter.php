<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Filters;

use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\ExistsFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use WhiteDigital\EntityResourceMapper\Mapper\AccessClassMapperTrait;

final class ResourceExistsFilter implements ExistsFilterInterface, FilterInterface
{

    use AccessClassMapperTrait;

    /**
     * @param ManagerRegistry $managerRegistry
     * @param LoggerInterface|null $logger
     * @param array<string, mixed>|null $properties
     * @param string $existsParameterName
     * @param NameConverterInterface|null $nameConverter
     */
    public function __construct(
        private readonly ManagerRegistry $managerRegistry, 
        private readonly ?LoggerInterface $logger = null, 
        private ?array $properties = null, 
        private readonly string $existsParameterName = self::QUERY_PARAMETER_KEY, 
        private readonly ?NameConverterInterface $nameConverter = null
    )
    {
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param QueryNameGeneratorInterface $queryNameGenerator
     * @param string $resourceClass
     * @param string|null $operationName
     * @param array<string, mixed>|null $context
     * @return void
     */
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = null): void
    {
        foreach ($context['filters'] as $property => $filter) {
            if (!array_key_exists($property, $this->properties)) {
                unset($context['filters'][$property]);
            }
        }
        $resourceClass = $this->classMapper->byResource($resourceClass);
        $existsFilter = new ExistsFilter(
            $this->managerRegistry,
            null,
            $this->logger,
            $this->properties,
            $this->existsParameterName,
            $this->nameConverter
        );
        $existsFilter->apply($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
    }

    /**
     * @param string $resourceClass
     * @return array<string, mixed>
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];


        foreach ($this->properties as $property => $unused) {
            $propertyName = $this->normalizePropertyName($property);
            $description[sprintf('%s[%s]', $this->existsParameterName, $propertyName)] = [
                'property' => $propertyName,
                'type' => 'bool',
                'required' => false,
            ];
        }

        return $description;
    }

    private function normalizePropertyName(string $property): string
    {
        if (!$this->nameConverter instanceof NameConverterInterface) {
            return $property;
        }

        return implode('.', array_map([$this->nameConverter, 'normalize'], explode('.', (string)$property)));
    }
}