<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Filters\Traits;

use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

use function array_map;
use function explode;
use function implode;

trait PropertyNameNormalizer
{
    protected function normalizePropertyName(string $property): string
    {
        if (!$this->nameConverter instanceof NameConverterInterface) {
            return $property;
        }

        return implode('.', array_map([$this->nameConverter, 'normalize'], explode('.', $property)));
    }
}
