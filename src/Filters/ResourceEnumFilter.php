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
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use WhiteDigital\EntityResourceMapper\Mapper\AccessClassMapperTrait;

final class ResourceEnumFilter implements SearchFilterInterface, FilterInterface
{

    use AccessClassMapperTrait;

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
            $this->properties[$property] = self::STRATEGY_EXACT;
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

    /**
     * @param string $resourceClass
     * @return array<mixed, string>
     */
    public function getDescription(string $resourceClass): array
    {
        if (!$this->properties) {
            return [];
        }

        $description = [];
        foreach ($this->properties as $property => $enumValues) {
            $description[$property] = [
                'property' => $property,
                'type' => Type::BUILTIN_TYPE_STRING,
                'required' => false,
                'description' => 'Filter by enum value',
                'schema' => [
                    'name' => 'Enum Filter',
                    'type' => 'string',
                    'enum' => $enumValues,
                ],
            ];
        }

        return $description;
    }
}