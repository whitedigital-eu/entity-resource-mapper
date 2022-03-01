<?php

namespace WhiteDigital\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use WhiteDigital\EntityDtoMapper\Dto\BaseDto;

class DtoClass extends BaseDto
{
    public ?int $id = null;
    public int $number;
    public string $text;
    public ?\DateTimeImmutable $created = null;
    public ?DtoClass2 $dtoClass2;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: DtoClass2::class)]
    public ?array $children;
}
