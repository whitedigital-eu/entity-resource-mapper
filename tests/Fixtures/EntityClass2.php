<?php

namespace WhiteDigital\Tests\Fixtures;

use WhiteDigital\EntityDtoMapper\Entity\BaseEntity;

class EntityClass2 extends BaseEntity
{
    public ?int $id = null;
    public string $text;

    /**
     * @param int|null $id
     * @param string $text
     */
    public function __construct(?int $id, string $text)
    {
        $this->id = $id;
        $this->text = $text;
    }
}
