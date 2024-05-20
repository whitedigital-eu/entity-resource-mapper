<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Filters;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use WhiteDigital\EntityResourceMapper\Mapper\AccessClassMapperTrait;

class ResourceValueInFilter implements FilterInterface
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
        private readonly ?NameConverterInterface $nameConverter = null,
    ) {
    }

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        foreach ($context['filters'] as $property => $filter) {
            if (!array_key_exists($property, $this->properties)) {
                unset($context['filters'][$property]);
            }
        }
        $entityClass = $this->classMapper->byResource($resourceClass, context: $context);

        $valueInFilter = new ValueInFilter(
            $this->managerRegistry,
            $this->logger,
            $this->properties,
            $this->nameConverter,
        );

        $valueInFilter->apply($queryBuilder, $queryNameGenerator, $entityClass, $operation, $context);
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
}
