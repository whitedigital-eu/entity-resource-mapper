<?php

namespace WhiteDigital\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use WhiteDigital\EntityDtoMapper\Entity\BaseEntity;

class EntityClass2 extends BaseEntity
{
    public ?int $id = null;
    public string $text;

    /**
     * @param string $text
     */
    public function setText(string $text): void
    {
        $this->text = $text;
    }

    /**
     * @param EntityClass|null $parent
     */
    public function setParent(?EntityClass $parent): void
    {
        $this->parent = $parent;
    }

    #[ORM\ManyToOne(targetEntity: EntityClass::class, inversedBy: 'children')]
    public ?EntityClass $parent;
}
