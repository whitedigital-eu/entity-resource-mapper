<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Filters;

use ApiPlatform\Api\IdentifiersExtractorInterface;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use WhiteDigital\EntityResourceMapper\Mapper\AccessClassMapperTrait;

final class ResourceSearchFilter implements SearchFilterInterface, FilterInterface
{
    use AccessClassMapperTrait;
    use Traits\PropertyNameNormalizer;

    private const CASE_INSENSITIVE_PREFIX = 'i';

    /**
     * @param array<string, mixed>|null $properties
     */
    public function __construct(
        private readonly ManagerRegistry $managerRegistry,
        private readonly IriConverterInterface $iriConverter,
        private readonly ?PropertyAccessorInterface $propertyAccessor = null,
        private readonly ?LoggerInterface $logger = null,
        private ?array $properties = null,
        private readonly ?IdentifiersExtractorInterface $identifiersExtractor = null,
        private readonly ?NameConverterInterface $nameConverter = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];
        if (null === $this->properties) {
            throw new RuntimeException(sprintf('Please explicitly mark properties for %s class', self::class));
        }

        foreach ($this->properties as $property => $options) {
            if (isset($options['display_name'])) {
                $property = $options['display_name'];
            }

            $description += $this->getFilterDescription($property, self::STRATEGY_EXACT);
            $description += $this->getFilterDescription($property, self::CASE_INSENSITIVE_PREFIX . self::STRATEGY_PARTIAL);
        }

        return $description;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string|Operation|null $operation = null, array $context = []): void
    {
        foreach ($context['filters'] as $property => $filter) {
            if (!array_key_exists($property, $this->properties)) {
                unset($context['filters'][$property]);
                continue;
            }
            $this->properties[$property] = key($filter) ?? self::STRATEGY_PARTIAL;
            $context['filters'][$property] = current($filter);
        }

        if ([] === $context['filters'] ?? []) {
            return;
        }

        $entityClass = $this->classMapper->byResource($resourceClass, $resourceClass, context: $context);

        $searchFilter = new SearchFilter(
            $this->managerRegistry,
            $this->iriConverter,
            $this->propertyAccessor,
            $this->logger,
            $this->properties,
            $this->identifiersExtractor,
            $this->nameConverter, );
        $searchFilter->apply($queryBuilder, $queryNameGenerator, $entityClass, $operation, $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function getFilterDescription(string $property, string $strategy): array
    {
        $propertyName = $this->normalizePropertyName($property);

        return [
            sprintf('%s[%s]', $propertyName, $strategy) => [
                'property' => $propertyName,
                'type' => 'string',
                'required' => false,
            ],
        ];
    }
}
