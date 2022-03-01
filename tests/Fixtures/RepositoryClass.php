<?php

namespace WhiteDigital\Tests\Fixtures;

use WhiteDigital\EntityDtoMapper\Entity\BaseEntity;

class RepositoryClass
{
    public function find($id): BaseEntity
    {
        return match ($id) {
            1 => $this->returnEntity1(),
            2 => $this->returnEntity2(),
        };
    }

    private function returnEntity1(): BaseEntity
    {
        $ec2 = new EntityClass2();
        $ec2->id = 1;
        $ec2->text = 'testText2';

        $entity = new EntityClass();
        $entity->id = null;
        $entity->number = 2;
        $entity->text = 'testText1';
        $entity->dtoClass2 = $ec2;
        return $entity;
    }

    private function returnEntity2(): BaseEntity
    {
        $entity = new EntityClass2();
        $entity->id = 1;
        $entity->text = 'testText2';
        return $entity;
    }

}
