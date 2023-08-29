<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Filters;

use ApiPlatform\Doctrine\Common\Filter\ExistsFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use WhiteDigital\EntityResourceMapper\Mapper\AccessClassMapperTrait;

final class ResourceExistsFilter implements ExistsFilterInterface, FilterInterface
{
    use AccessClassMapperTrait;
    use Traits\PropertyNameNormalizer;

    /**
     * @param array<string, mixed>|null $properties
     */
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?array $properties = null,
        private readonly string $existsParameterName = self::QUERY_PARAMETER_KEY,
        private readonly ?NameConverterInterface $nameConverter = null,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string|Operation|null $operation = null, array $context = []): void
    {
        if (!array_key_exists('exists', $context['filters'])) {
            return;
        }
        foreach ($context['filters']['exists'] as $property => $filter) {
            if (!array_key_exists($property, $this->properties)) {
                unset($context['filters']['exists'][$property]);
            }
        }
        $entityClass = $this->classMapper->byResource($resourceClass, context: $context);
        $existsFilter = new ExistsFilter(
            $this->managerRegistry,
            $this->logger,
            $this->properties,
            $this->existsParameterName,
            $this->nameConverter,
        );
        $existsFilter->apply($queryBuilder, $queryNameGenerator, $entityClass, $operation, $context);
    }

    /**
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
}
