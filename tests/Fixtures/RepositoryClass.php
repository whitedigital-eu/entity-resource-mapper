<?php

namespace WhiteDigital\Tests\Fixtures;

use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;

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
        $entity->setNumber(2);
        $entity->setText('testText1');
        $entity->setDtoClass2($ec2);
        return $entity;
    }

    private function returnEntity2(): BaseEntity
    {
        $entity = new EntityClass2();
        $entity->id = 1;
        $entity->text = 'testText2';
        return $entity;
    }

    private function returnSelfReferencingEntity(): BaseEntity
    {
        $subChildEntity = new SelfReferencingEntityClass();
        $subChildEntity->setNumber(3);
        $subChildEntity->setText('testText3');
        $subChildEntity->setCreatedAt(new \DateTimeImmutable());

        $childEntity = new SelfReferencingEntityClass();
        $childEntity->setNumber(2);
        $childEntity->setText('testText2');
        $childEntity->setCreatedAt(new \DateTimeImmutable());
        $childEntity->addChildren($subChildEntity);

        $parentEntity = new SelfReferencingEntityClass();
        $parentEntity->setNumber(1);
        $parentEntity->setText('testText1');
        $parentEntity->setCreatedAt(new \DateTimeImmutable());
        $parentEntity->addChildren($childEntity);

        return $parentEntity;
    }
}
