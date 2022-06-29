<?php

declare(strict_types=1);

namespace WhiteDigital\EntityResourceMapper\Security\Enum;

enum GrantType: string
{
    case ALL = 'ALL'; // Can access all records
    case OWN = 'OWN'; // Can access only self owned records
    case GROUP = 'GROUP'; // Can access only records in the same department
    case NONE = 'NONE'; // No access
}