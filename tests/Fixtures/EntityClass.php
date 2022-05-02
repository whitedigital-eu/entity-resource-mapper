<?php

namespace WhiteDigital\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use Doctrine\ORM\Mapping as ORM;

class EntityClass extends BaseEntity
{

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

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
     * @param EntityClass2|null $children
     */
    public function addChildren(?EntityClass2 $children): void
    {
        $this->children[] = $children;
    }

    protected ?int $id = null;
    protected int $number;
    protected string $text;
    protected ?EntityClass2 $dtoClass2;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: EntityClass2::class)]
    public ?Collection $children;

    public function getId(): ?int
    {
        return $this->id;
    }
}
