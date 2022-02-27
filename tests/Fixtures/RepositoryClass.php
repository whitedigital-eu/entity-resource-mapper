<?php

namespace WhiteDigital\Tests\Fixtures;

use WhiteDigital\EntityDtoMapper\Entity\BaseEntity;

class RepositoryClass
{
    public function find($id): BaseEntity
    {
        return match ($id) {
            1 => new EntityClass(null,2,'testText1',new EntityClass2(1,'testText2')),
            2 => new EntityClass2(1,'testText2'),
        };
    }
}
