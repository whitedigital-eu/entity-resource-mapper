<?php

namespace WhiteDigital\Tests;

use WhiteDigital\Tests\Fixtures\DtoClass;
use WhiteDigital\Tests\Fixtures\DtoClass2;
use WhiteDigital\Tests\Fixtures\DtoClass3;
use WhiteDigital\Tests\Fixtures\EntityClass;
use WhiteDigital\Tests\Fixtures\EntityClass2;
use PHPUnit\Framework\TestCase;
use WhiteDigital\EntityDtoMapper\Mapper\ClassMapper;

class ClassMapperTest extends TestCase
{
    public function testClassMapperWithoutConfig(): void
    {
        $this->expectException(\RuntimeException::class);
        $classMapper = new ClassMapper();
        $classMapper->byDto('AnyClass');
    }

    public function testClassMapperByDto(): void
    {
        $classMapper = new ClassMapper();
        $classMapper->registerMapping(DtoClass::class, EntityClass::class);
        $result = $classMapper->byDto(DtoClass::class);
        $this->assertEquals(EntityClass::class, $result);
    }

    public function testClassMapperByEntity(): void
    {
        $classMapper = new ClassMapper();
        $classMapper->registerMapping(DtoClass::class, EntityClass::class);
        $result = $classMapper->byEntity(EntityClass::class);
        $this->assertEquals(DtoClass::class, $result);
    }

    public function testClassMapperByUnknownClass(): void
    {
        $classMapper = new ClassMapper();
        $classMapper->registerMapping(DtoClass::class, EntityClass::class);
        $this->expectException(\RuntimeException::class);
        $classMapper->byEntity('AnyClass');
    }

    public function testClassMapperCondition(): void
    {
        $classMapper = new ClassMapper();
        $classMapper->registerMapping(DtoClass::class, EntityClass::class, DtoClass2::class);
        $classMapper->registerMapping(DtoClass::class, EntityClass2::class, DtoClass3::class);

        $result = $classMapper->byDto(DtoClass::class, DtoClass3::class);
        $this->assertEquals(EntityClass2::class, $result);
    }
}
