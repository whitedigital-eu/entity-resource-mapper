<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Security\Interface;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use WhiteDigital\EntityResourceMapper\Security\Attribute\AccessResolverConfiguration;

#[AutoconfigureTag('authorization.access_resolver')]
interface AccessResolverInterface
{
    public function isObjectAccessGranted(AccessResolverConfiguration $accessResolverAttribute, object $object): bool;

    public function limitCollectionQuery(AccessResolverConfiguration $accessResolverAttribute, QueryBuilder $queryBuilder): void;
}
