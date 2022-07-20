<?php

namespace WhiteDigital\Tests\Fixtures;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use WhiteDigital\EntityResourceMapper\Filters\ResourceSearchFilter;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;

#[ApiResource(
)]
#[ApiFilter(ResourceSearchFilter::class, properties: ['number'])]
class ResourceClass extends BaseResource
{
    public mixed $id = null;
    public int $number;
    public string $text;
    public ?\DateTimeImmutable $created = null;
    public ?ResourceClass2 $dtoClass2;

    /** @var ResourceClass2[] */
    public array $children;
}
