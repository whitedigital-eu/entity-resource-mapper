<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Mapper;

use Symfony\Contracts\Service\Attribute\Required;


trait AccessClassMapperTrait
{
    private ClassMapper $classMapper;

    #[Required]
    public function setClassMapper(ClassMapper $classMapper): void
    {
        $this->classMapper = $classMapper;
    }
}