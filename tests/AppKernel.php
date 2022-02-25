<?php

namespace WhiteDigital\Tests;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;
use WhiteDigital\EntityDtoMapper\EntityDtoMapperBundle;

class AppKernel extends Kernel
{

    public function registerBundles(): iterable
    {
        return [
            new EntityDtoMapperBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
    }
}
