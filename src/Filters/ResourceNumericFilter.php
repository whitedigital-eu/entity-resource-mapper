<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Filters;


use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\NumericFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use WhiteDigital\EntityResourceMapper\Mapper\AccessClassMapperTrait;

final class ResourceNumericFilter implements FilterInterface
{
    use AccessClassMapperTrait;

    /**
     * @param ManagerRegistry $managerRegistry
     * @param LoggerInterface|null $logger
     * @param array<string, mixed>|null $properties
     * @param NameConverterInterface|null $nameConverter
     */
    public function __construct(
        private readonly ManagerRegistry         $managerRegistry,
        private readonly ?LoggerInterface        $logger = null,
        private ?array                           $properties = null,
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
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string|\ApiPlatform\Metadata\Operation $operation = null, ?array $context = null): void
    {
        foreach ($context['filters'] as $property => $value) {
            if (!array_key_exists($property, $this->properties)) {
                unset($context['filters'][$property]);
                continue;
            }
            if (!is_array($value) && !is_numeric($value)) {
                throw new \RuntimeException("Non numeric value ($value) for numeric filter on property $property");
            }

            if (is_array($value)) {
                foreach ($value as $element) {
                    if (!is_numeric($element)) {
                        throw new \RuntimeException("Non numeric value ($element) for numeric filter on property $property");
                    }
                }
            }
        }
        $resourceClass = $this->classMapper->byResource($resourceClass);
        $numericFilter = new NumericFilter(
            $this->managerRegistry,
            $this->logger,
            $this->properties,
            $this->nameConverter
        );
        $numericFilter->apply($queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);
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
            $filterParameterNames = [$propertyName, $propertyName . '[]'];
            foreach ($filterParameterNames as $filterParameterName) {
                $description[$filterParameterName] = [
                    'property' => $propertyName,
                    'type' => 'int', //TODO can be float?
                    'required' => false,
                    'is_collection' => str_ends_with((string)$filterParameterName, '[]'),
                ];
            }
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
