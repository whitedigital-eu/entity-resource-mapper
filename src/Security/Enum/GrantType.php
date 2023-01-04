<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Security\Enum;

enum GrantType: string
{
    case ALL = 'ALL'; // Can access all records
    case LIMITED = 'LIMITED'; // Can access limited set of records (access resolver is required)
    case NONE = 'NONE'; // No access
}
