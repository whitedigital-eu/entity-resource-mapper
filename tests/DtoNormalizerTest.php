<?php

namespace WhiteDigital\Tests;

use WhiteDigital\Tests\Fixtures\DtoClass;
use WhiteDigital\Tests\Fixtures\EntityClass;
use PHPUnit\Framework\TestCase;
use WhiteDigital\EntityDtoMapper\Mapper\ClassMapper;
use WhiteDigital\EntityDtoMapper\Serializer\DtoNormalizer;
use WhiteDigital\Tests\AppKernel;

class DtoNormalizerTest extends TestCase
{
    private DtoNormalizer $dtoNormalizer;

    public function setUp(): void
    {
        $kernel = new AppKernel('test',false);
        $kernel->boot();
        $container = $kernel->getContainer();
        $classMapper = $container->get(ClassMapper::class);
        $this->dtoNormalizer = $container->get(DtoNormalizer::class);
        $classMapper->registerMapping(DtoClass::class, EntityClass::class);
    }

    public function testDtoNormalizer()
    {
        $dtoObject = new DtoClass();
        $this->dtoNormalizer->normalize($dtoObject);
    }
}
