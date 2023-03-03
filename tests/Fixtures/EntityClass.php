<?php declare(strict_types = 1);

namespace WhiteDigital\Tests\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;

class EntityClass extends BaseEntity
{
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: EntityClass2::class)]
    public ?Collection $children;

    protected ?int $id = null;
    protected ?int $number = null;
    protected ?string $text = null;
    protected ?EntityClass2 $dtoClass2 = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(int $number): void
    {
        $this->number = $number;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function getDtoClass2(): ?EntityClass2
    {
        return $this->dtoClass2;
    }

    public function setDtoClass2(?EntityClass2 $dtoClass2): void
    {
        $this->dtoClass2 = $dtoClass2;
    }

    public function getChildren(): ?Collection
    {
        return $this->children;
    }

    public function addChildren(?EntityClass2 $children): void
    {
        $this->children[] = $children;
    }

    public function removeChildren(?EntityClass2 $children): void
    {
        $this->children = new ArrayCollection(array_diff($this->children->toArray(), [$children]));
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
