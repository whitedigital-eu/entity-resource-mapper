<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

trait Id
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER, unique: true)]
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
