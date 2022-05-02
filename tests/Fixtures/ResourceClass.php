<?php

namespace WhiteDigital\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;

class ResourceClass extends BaseResource
{
    public ?int $id = null;
    public int $number;
    public string $text;
    public ?\DateTimeImmutable $created = null;
    public ?ResourceClass2 $dtoClass2;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: ResourceClass2::class)]
    public ?array $children;
}
