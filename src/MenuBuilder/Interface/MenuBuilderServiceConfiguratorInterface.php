<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\MenuBuilder\Interface;

use WhiteDigital\EntityResourceMapper\MenuBuilder\Service\MenuBuilderService;

interface MenuBuilderServiceConfiguratorInterface
{
    public function __invoke(MenuBuilderService $menuBuilder): void;
}