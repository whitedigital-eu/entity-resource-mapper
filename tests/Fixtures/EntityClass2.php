<?php declare(strict_types = 1);

namespace WhiteDigital\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;

class EntityClass2 extends BaseEntity
{
    public ?int $id = null;
    public ?string $text = null;

    #[ORM\ManyToOne(targetEntity: EntityClass::class, inversedBy: 'children')]
    public ?EntityClass $parent = null;

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function setParent(?EntityClass $parent): void
    {
        $this->parent = $parent;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
