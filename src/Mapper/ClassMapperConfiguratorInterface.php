<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Mapper;

interface ClassMapperConfiguratorInterface
{
    public function __invoke(ClassMapper $classMapper): void;
}
