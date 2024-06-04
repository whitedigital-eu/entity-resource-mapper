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
use BackedEnum;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use WhiteDigital\EntityResourceMapper\Mapper\AccessClassMapperTrait;

final class ResourceEnumFilter implements SearchFilterInterface, FilterInterface
{
    use AccessClassMapperTrait;

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
     * @param array<string, mixed>|null $context
     */
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string|Operation|null $operation = null, array $context = []): void
    {
        foreach ($context['filters'] as $property => $filter) {
            if (!array_key_exists($property, $this->properties)) {
                unset($context['filters'][$property]);
                continue;
            }
            $this->properties[$property] = self::STRATEGY_EXACT;
        }

        if ([] === ($context['filters'] ?? [])) {
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
            $this->nameConverter,
        );
        $searchFilter->apply($queryBuilder, $queryNameGenerator, $entityClass, $operation, $context);
    }

    /**
     * @return array<mixed, string>
     */
    public function getDescription(string $resourceClass): array
    {
        if (!$this->properties) {
            return [];
        }

        $description = [];
        foreach ($this->properties as $property => $enumValues) {
            if (!is_array($enumValues)) {
                if (null === $enumValues) {
                    try {
                        $enumValues = (new ReflectionClass($resourceClass))->getProperty($property)->getType()?->getName();
                    } catch (ReflectionException) {
                        $enumValues = null;
                    }
                }
                if (null !== $enumValues && class_exists($enumValues)) {
                    $implements = class_implements($enumValues);
                } else {
                    $implements = [];
                }
                if (is_array($implements) && in_array(BackedEnum::class, $implements, true)) {
                    $enumValues = array_column($enumValues::cases(), 'value');
                }
            }
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
