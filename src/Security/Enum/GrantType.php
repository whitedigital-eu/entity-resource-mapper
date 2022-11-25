<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Security\Enum;

enum GrantType: string
{
    case ALL = 'ALL'; // Can access all records
    case OWN = 'OWN'; // Can access records that are related to current user
    case NONE = 'NONE'; // No access
}