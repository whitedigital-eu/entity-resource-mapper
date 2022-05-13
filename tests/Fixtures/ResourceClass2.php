<?php

namespace WhiteDigital\Tests\Fixtures;

use WhiteDigital\EntityResourceMapper\Resource\BaseResource;

class ResourceClass2 extends BaseResource
{
    public ?int $id = null;
    public string $text;

    public ?ResourceClass $parent;
}
