<?php

namespace WhiteDigital\Tests\Fixtures;

use Doctrine\Common\Collections\Collection;
use WhiteDigital\EntityDtoMapper\Entity\BaseEntity;
use Doctrine\ORM\Mapping as ORM;

class EntityClass extends BaseEntity
{
    /**
     * @return int
     */
    public function getNumber(): int
    {
        return $this->number;
    }

    /**
     * @param int $number
     */
    public function setNumber(int $number): void
    {
        $this->number = $number;
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @param string $text
     */
    public function setText(string $text): void
    {
        $this->text = $text;
    }

    /**
     * @return EntityClass2|null
     */
    public function getDtoClass2(): ?EntityClass2
    {
        return $this->dtoClass2;
    }

    /**
     * @param EntityClass2|null $dtoClass2
     */
    public function setDtoClass2(?EntityClass2 $dtoClass2): void
    {
        $this->dtoClass2 = $dtoClass2;
    }

    /**
     * @return Collection|null
     */
    public function getChildren(): ?Collection
    {
        return $this->children;
    }

    /**
     * @param Collection|null $children
     */
    public function addChildren(?Collection $children): void
    {
        $this->children = $children;
    }

    public ?int $id = null;
    public int $number;
    public string $text;
    public ?EntityClass2 $dtoClass2;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: EntityClass2::class)]
    public ?Collection $children;
}
