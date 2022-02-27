<?php

namespace WhiteDigital\Tests\Fixtures;

use WhiteDigital\EntityDtoMapper\Entity\BaseEntity;

class EntityClass extends BaseEntity
{

    public ?int $id = null;
    public int $number;
    public string $text;
    public ?EntityClass2 $dtoClass2;

    /**
     * @param int|null $id
     * @param int $number
     * @param string $text
     * @param EntityClass2|null $dtoClass2
     */
    public function __construct(?int $id, int $number, string $text, ?EntityClass2 $dtoClass2)
    {
        $this->id = $id;
        $this->number = $number;
        $this->text = $text;
        $this->dtoClass2 = $dtoClass2;
    }
}
