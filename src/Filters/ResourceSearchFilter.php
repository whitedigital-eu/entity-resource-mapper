<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Filters;

use ApiPlatform\Core\Api\IdentifiersExtractorInterface;
use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use WhiteDigital\EntityResourceMapper\Mapper\AccessClassMapperTrait;

final class ResourceSearchFilter implements SearchFilterInterface, FilterInterface
{
    use AccessClassMapperTrait;

    private const CASE_INSENSITIVE_PREFIX = 'i';


    /**
     * @param ManagerRegistry $managerRegistry
     * @param IriConverterInterface $iriConverter
     * @param PropertyAccessorInterface|null $propertyAccessor
     * @param LoggerInterface|null $logger
     * @param array<string, mixed>|null $properties
     * @param IdentifiersExtractorInterface|null $identifiersExtractor
     * @param NameConverterInterface|null $nameConverter
     */
    public function __construct(
        private readonly ManagerRegistry                $managerRegistry,
        private readonly IriConverterInterface          $iriConverter,
        private readonly ?PropertyAccessorInterface     $propertyAccessor = null,
        private readonly ?LoggerInterface               $logger = null,
        private ?array                                  $properties = null,
        private readonly ?IdentifiersExtractorInterface $identifiersExtractor = null,
        private readonly ?NameConverterInterface        $nameConverter = null
    )
    {
    }


    /**
     * @param string $resourceClass
     * @return array<string, mixed>
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];
        if (null === $this->properties) {
            throw new \RuntimeException(sprintf('Please explicitly mark properties for %s class', self::class));
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
     * @param string $property
     * @param string $strategy
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


    protected function normalizePropertyName(string $property): string
    {
        if (!$this->nameConverter instanceof NameConverterInterface) {
            return $property;
        }

        return implode('.', array_map([$this->nameConverter, 'normalize'], explode('.', (string)$property)));
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
                continue;
            }
            $this->properties[$property] = key($filter) ?? self::STRATEGY_PARTIAL;
            $context['filters'][$property] = current($filter);
        }
        $resourceClass = $this->classMapper->byResource($resourceClass);
        
        $searchFilter = new SearchFilter(
            $this->managerRegistry,
            null,
            $this->iriConverter,
            $this->propertyAccessor,
            $this->logger,
            $this->properties,
            $this->identifiersExtractor,
            $this->nameConverter);
        $searchFilter->apply($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
    }
}