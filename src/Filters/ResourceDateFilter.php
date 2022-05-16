<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Filters;

use ApiPlatform\Core\Bridge\Doctrine\Common\Filter\DateFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use WhiteDigital\EntityResourceMapper\Mapper\AccessClassMapperTrait;

class ResourceDateFilter implements FilterInterface, DateFilterInterface
{
    use AccessClassMapperTrait;

    /**
     * @param ManagerRegistry $managerRegistry
     * @param RequestStack|null $requestStack
     * @param LoggerInterface|null $logger
     * @param array<string, mixed>|null $properties
     * @param NameConverterInterface|null $nameConverter
     */
    public function __construct(
        private readonly ManagerRegistry         $managerRegistry,
        private readonly ?RequestStack           $requestStack = null,
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
     * @throws \Exception
     */
    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, ?array $context = null): void
    {
        foreach ($context['filters'] as $property => $filter) {
            if (!array_key_exists($property, $this->properties)) {
                unset($context['filters'][$property]);
                continue;
            }
            $value = current($filter);
            // Try to instantiate DateTime object to validate filter value, otherwise it gets ignored later
            $validDateTime = new \DateTimeImmutable($value);

        }
        $dateFilter = new DateFilter(
            $this->managerRegistry,
            $this->requestStack,
            $this->logger,
            $this->properties,
            $this->nameConverter,
        );
        $resourceClass = $this->classMapper->byResource($resourceClass);

        $dateFilter->apply($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
    }

    /**
     * @param string $resourceClass
     * @return array<string, mixed>
     * @throws Exception
     * @throws \ReflectionException
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];
        if (null === $this->properties)
            throw new Exception(sprintf('Please explicitly mark properties for %s class', self::class));
        $reflection = new \ReflectionClass($resourceClass);

        foreach ($this->properties as $property => $nullManagement) {
            $property_reflection = $reflection->getProperty($property);
            $type = $property_reflection->getType();
            /** @phpstan-ignore-next-line */
            if ($type->getName() !== \DateTimeInterface::class)
                continue;

            $description += $this->getFilterDescription($property, self::PARAMETER_BEFORE);
            $description += $this->getFilterDescription($property, self::PARAMETER_STRICTLY_BEFORE);
            $description += $this->getFilterDescription($property, self::PARAMETER_AFTER);
            $description += $this->getFilterDescription($property, self::PARAMETER_STRICTLY_AFTER);
        }
        return $description;
    }

    /**
     * @param string $property
     * @param string $period
     * @return array<string, mixed>
     */
    protected function getFilterDescription(string $property, string $period): array
    {
        $propertyName = $this->normalizePropertyName($property);

        return [
            sprintf('%s[%s]', $propertyName, $period) => [
                'property' => $propertyName,
                'type' => \DateTimeInterface::class,
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
}