<?php

namespace WhiteDigital\EntityDtoMapper;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class EntityDtoMapperBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
