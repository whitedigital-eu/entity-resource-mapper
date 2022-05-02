<?php

namespace WhiteDigital\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;

class ResourceClass2 extends BaseResource
{
    public ?int $id = null;
    public string $text;

    #[ORM\ManyToOne(targetEntity: ResourceClass::class, inversedBy: 'children')]
    public ?ResourceClass $parent;
}
