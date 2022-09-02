<?php

namespace WhiteDigital\Tests\Fixtures;

use WhiteDigital\EntityResourceMapper\Resource\BaseResource;

class SelfReferencingResource extends BaseResource
{
    public mixed $id = null;
    public ?int $number = null;
    public ?string $text = null;
    public ?self $parent = null;
    public ?array $children = null;
    public ?\DateTimeImmutable $createdAt = null;
    public ?\DateTimeImmutable $updatedAt = null;
}
