<?php

namespace WhiteDigital\Tests\Fixtures;

use WhiteDigital\EntityResourceMapper\Resource\BaseResource;

class ResourceClass extends BaseResource
{
    public ?int $id = null;
    public int $number;
    public string $text;
    public ?\DateTimeImmutable $created = null;
    public ?ResourceClass2 $dtoClass2;

    /** @var ResourceClass2[] */
    public array $children;
}
