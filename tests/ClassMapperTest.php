<?php declare(strict_types = 1);

namespace WhiteDigital\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WhiteDigital\EntityResourceMapper\Mapper\ClassMapper;
use WhiteDigital\Tests\Fixtures\EntityClass;
use WhiteDigital\Tests\Fixtures\EntityClass2;
use WhiteDigital\Tests\Fixtures\ResourceClass;
use WhiteDigital\Tests\Fixtures\ResourceClass2;
use WhiteDigital\Tests\Fixtures\ResourceClass3;

class ClassMapperTest extends TestCase
{
    public function testClassMapperWithoutConfig(): void
    {
        $this->expectException(RuntimeException::class);
        $classMapper = new ClassMapper();
        $classMapper->byResource('AnyClass');
    }

    public function testClassMapperByDto(): void
    {
        $classMapper = new ClassMapper();
        $classMapper->registerMapping(ResourceClass::class, EntityClass::class);
        $result = $classMapper->byResource(ResourceClass::class);
        $this->assertEquals(EntityClass::class, $result);
    }

    public function testClassMapperByEntity(): void
    {
        $classMapper = new ClassMapper();
        $classMapper->registerMapping(ResourceClass::class, EntityClass::class);
        $result = $classMapper->byEntity(EntityClass::class);
        $this->assertEquals(ResourceClass::class, $result);
    }

    public function testClassMapperByUnknownClass(): void
    {
        $classMapper = new ClassMapper();
        $classMapper->registerMapping(ResourceClass::class, EntityClass::class);
        $this->expectException(RuntimeException::class);
        $classMapper->byEntity('AnyClass');
    }

    public function testClassMapperCondition(): void
    {
        $classMapper = new ClassMapper();
        $classMapper->registerMapping(ResourceClass::class, EntityClass::class, ResourceClass2::class);
        $classMapper->registerMapping(ResourceClass::class, EntityClass2::class, ResourceClass3::class);

        $result = $classMapper->byResource(ResourceClass::class, ResourceClass3::class);
        $this->assertEquals(EntityClass2::class, $result);
    }
}
