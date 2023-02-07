<?php declare(strict_types = 1);

namespace WhiteDigital\Tests\Fixtures;

use WhiteDigital\EntityResourceMapper\Resource\BaseResource;

class ResourceClass2 extends BaseResource
{
    public mixed $id = null;
    public string $text;

    public ?ResourceClass $parent;
}
