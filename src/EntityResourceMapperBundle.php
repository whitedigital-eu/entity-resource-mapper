<?php

namespace WhiteDigital\EntityResourceMapper;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class EntityResourceMapperBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
