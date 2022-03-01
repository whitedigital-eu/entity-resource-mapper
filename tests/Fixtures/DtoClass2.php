<?php

namespace WhiteDigital\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use WhiteDigital\EntityDtoMapper\Dto\BaseDto;

class DtoClass2 extends BaseDto
{
    public ?int $id = null;
    public string $text;

    #[ORM\ManyToOne(targetEntity: DtoClass::class, inversedBy: 'children')]
    public ?DtoClass $parent;
}
