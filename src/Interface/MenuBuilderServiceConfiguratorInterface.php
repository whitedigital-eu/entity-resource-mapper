<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Interface;

use WhiteDigital\EntityResourceMapper\Service\MenuBuilderService;

interface MenuBuilderServiceConfiguratorInterface
{
    public function __invoke(MenuBuilderService $menuBuilder): void;
}