<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Security\Interface;

use Doctrine\ORM\QueryBuilder;

interface LimitedResourceAccessResolverInterface
{
    public function isItemAccessAllowed(object $item): bool;

    public function limitCollectionQuery(QueryBuilder $queryBuilder): void;
}