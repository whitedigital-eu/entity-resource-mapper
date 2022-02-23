<?php

use Fixtures\DtoClass;
use Fixtures\EntityClass;
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
}
