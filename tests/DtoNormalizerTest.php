<?php

namespace WhiteDigital\Tests;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use WhiteDigital\EntityDtoMapper\Mapper\ClassMapper;
use WhiteDigital\EntityDtoMapper\Serializer\DtoNormalizer;
use WhiteDigital\Tests\Fixtures\DtoClass;
use WhiteDigital\Tests\Fixtures\DtoClass2;
use WhiteDigital\Tests\Fixtures\EntityClass;
use WhiteDigital\Tests\Fixtures\EntityClass2;
use WhiteDigital\Tests\Fixtures\RepositoryClass;

class DtoNormalizerTest extends TestCase
{
    private DtoNormalizer $dtoNormalizer;

    public function setUp(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn(new RepositoryClass());

        $classMapper = new ClassMapper();
        $this->dtoNormalizer = new DtoNormalizer(
            $entityManager,
            $classMapper,
        );
        $classMapper->registerMapping(DtoClass::class, EntityClass::class);
        $classMapper->registerMapping(DtoClass2::class, EntityClass2::class);
    }

    /** @covers \WhiteDigital\EntityDtoMapper\Serializer\DtoNormalizer */
    public function testDtoNormalizer(): void
    {
        $dtoObject = new DtoClass();
        $dtoObject->text = 'testText1';
        $dtoObject->created = new \DateTimeImmutable();
        $dtoObject->number = 2;
        $ch1 = new DtoClass2();
        $ch1->id = null;
        $ch1->text = 'child1';
        $ch2 = new DtoClass2();
        $ch2->id = null;
        $ch2->text = 'child2';
        $dtoObject->children = [$ch1, $ch2];

        $dtoObject2 = new DtoClass2();
        $dtoObject2->id = 2;
        $dtoObject2->text = 'testText2';

        $dtoObject->dtoClass2 = $dtoObject2;

        $result = $this->dtoNormalizer->normalize($dtoObject);
        $this->assertCount(6, $result);
        $this->assertArrayHasKey('created', $result);
        $this->assertEquals('testText2', $result['dtoClass2']->text);
        $this->assertEquals(EntityClass2::class, get_class($result['dtoClass2']));
    }
}
