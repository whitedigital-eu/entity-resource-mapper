<?php

namespace WhiteDigital\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use Doctrine\ORM\Mapping as ORM;

class SelfReferencingEntityClass extends BaseEntity
{
    private ?int $id = null;
    private int $number;
    private string $text;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    private ?self $parent;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    private ?Collection $children;

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
     * @return Collection|null
     */
    public function getChildren(): ?Collection
    {
        return $this->children;
    }

    /**
     * @param self|null $children
     */
    public function addChildren(?self $children): void
    {
        $this->children[] = $children;
    }

    /**
     * @param self|null $parent
     */
    public function setParent(?self $parent): void
    {
        $this->parent = $parent;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }
}
