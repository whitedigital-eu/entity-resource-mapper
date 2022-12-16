<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper;

use Symfony\Component\HttpKernel\Bundle\Bundle;

use function dirname;

class EntityResourceMapperBundle extends Bundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
