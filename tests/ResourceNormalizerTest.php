<?php declare(strict_types = 1);

namespace WhiteDigital\Tests;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use WhiteDigital\EntityResourceMapper\Mapper\ClassMapper;
use WhiteDigital\EntityResourceMapper\Mapper\ResourceToEntityMapper;
use WhiteDigital\Tests\Fixtures\EntityClass;
use WhiteDigital\Tests\Fixtures\EntityClass2;
use WhiteDigital\Tests\Fixtures\RepositoryClass;
use WhiteDigital\Tests\Fixtures\ResourceClass;
use WhiteDigital\Tests\Fixtures\ResourceClass2;

class ResourceNormalizerTest extends TestCase
{
    private ResourceToEntityMapper $resourceToEntityMapper;

    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn(new RepositoryClass());

        $classMapper = new ClassMapper();
        $this->resourceToEntityMapper = new ResourceToEntityMapper(
            $entityManager,
            $classMapper,
        );
        $classMapper->registerMapping(ResourceClass::class, EntityClass::class);
        $classMapper->registerMapping(ResourceClass2::class, EntityClass2::class);
    }

    /** @covers \WhiteDigital\EntityResourceMapper\Mapper\ResourceToEntityMapper */
    public function testResourceToEntityMapper(): void
    {
        $dtoObject = new ResourceClass();
        $dtoObject->text = 'testText1';
        $dtoObject->number = 2;
        $ch1 = new ResourceClass2();
        $ch1->id = null;
        $ch1->text = 'child1';
        $ch2 = new ResourceClass2();
        $ch2->id = null;
        $ch2->text = 'child2';
        $dtoObject->children = [$ch1, $ch2];

        $dtoObject2 = new ResourceClass2();
        $dtoObject2->id = 2;
        $dtoObject2->text = 'testText2';

        $dtoObject->dtoClass2 = $dtoObject2;

        $result = $this->resourceToEntityMapper->map($dtoObject, []);
        $this->assertEquals('testText1', $result->getText());
        $this->assertEquals('testText2', $result->getDtoClass2()->getText());
        $this->assertEquals(EntityClass2::class, get_class($result->getDtoClass2()));
        $this->assertEquals(EntityClass::class, $result::class);
    }
}
