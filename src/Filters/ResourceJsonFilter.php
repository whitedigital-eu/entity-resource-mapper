<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Filters;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use DateTimeInterface;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\QueryBuilder;
use ReflectionClass;
use ReflectionException;

/*
 * Api Platform does not support Json Filter yet,
 * follow: https://github.com/api-platform/core/issues/2274
 */
class ResourceJsonFilter extends AbstractFilter
{
    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     * @throws ReflectionException
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];
        $properties = $this->getProperties();
        if (null === $properties) {
            throw new Exception(sprintf('Please explicitly mark properties for %s class', self::class));
        }
        $reflection = new ReflectionClass($resourceClass);

        foreach ($properties as $property => $nullManagement) {
            if (!str_contains($property, '.')) {
                $property_reflection = $reflection->getProperty($property);
                $type = $property_reflection->getType();
                /* @phpstan-ignore-next-line */
                if (DateTimeInterface::class === $type->getName()) {
                    continue;
                }
            }

            $description += $this->getFilterDescription($property);
        }

        return $description;
    }

    /**
     * @param array<string, mixed>|null $context
     */
    protected function filterProperty(string $property, mixed $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string|Operation $operation = null, ?array $context = null): void
    {
        if (!array_key_exists($property, $this->properties)) {
            return;
        }
        $property = sprintf('%s.%s', $queryBuilder->getRootAliases()[0], $property);
        $searchSpecificKey = false;
        $keyName = '';
        if (is_array($value) && 1 === count($value)) {
            $searchSpecificKey = true;
            $keyName = key($value);
            $value = current($value);
        }
        $value = filter_var($value, FILTER_SANITIZE_ADD_SLASHES);

        if ($searchSpecificKey) {
            /*
             * Will convert to: WHERE table.field->>'key' = 'searchValue'
             */
            $queryBuilder->andWhere(sprintf("JSON_GET_TEXT(%s, '%s') = '%s' ", $property, $keyName, $value));
        } else {
            /*
             * Custom function registered at App\DBAL\Functions\JsonbPathExists
             * Query from https://stackoverflow.com/questions/45849494/how-do-i-search-for-a-specific-string-in-a-json-postgres-data-type-column
             * $.**                     find any value at any level (recursive processing)
             * ?                        where
             * @.type() == "string"     value is string
             * &&                       and
             * @ like_regex "authVar"   value contains 'authVar'
             * flag "i"                 case-insensitive flag
             */
            $queryBuilder->andWhere(sprintf('JSONB_PATH_EXISTS(%s, \'$.** ? (@.type() == "string" && @ like_regex "%s" flag "i")\') = TRUE', $property, $value));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getFilterDescription(string $property): array
    {
        $propertyName = $this->normalizePropertyName($property);

        return [
            sprintf('%s', $propertyName) => [
                'property' => $propertyName,
                'type' => 'string',
                'required' => false,
                'description' => 'JSON filter, will search in string values (not in keys). With field[key]=value can search for exact key value.',
            ],
        ];
    }
}
