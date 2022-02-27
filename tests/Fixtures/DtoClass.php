<?php

namespace WhiteDigital\Tests\Fixtures;

use WhiteDigital\EntityDtoMapper\Dto\BaseDto;

class DtoClass extends BaseDto
{
    public ?int $id = null;
    public int $number;
    public string $text;
    public ?\DateTimeImmutable $created = null;
    public ?DtoClass2 $dtoClass2;
}
