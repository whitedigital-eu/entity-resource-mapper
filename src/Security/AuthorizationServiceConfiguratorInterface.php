<?php declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\Security;

interface AuthorizationServiceConfiguratorInterface
{
    public function __invoke(AuthorizationService $service): void;
}