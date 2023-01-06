<?php

declare(strict_types = 1);

namespace WhiteDigital\EntityResourceMapper\MenuBuilder\Service;

use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\Security\Core\User\UserInterface;
use WhiteDigital\EntityResourceMapper\Security\AuthorizationService;
use WhiteDigital\EntityResourceMapper\Security\Enum\GrantType;

/**
 * MenuBuilderService is responsible for building menu item structure dynamically based on authorization data of
 * current user
 * The full menu item structure should be configured by using a configurator class, that implements the Interface
 * provided in Autoconfigure attribute.
 */
#[Autoconfigure(configurator: '@WhiteDigital\EntityResourceMapper\MenuBuilder\Interface\MenuBuilderServiceConfiguratorInterface')]
class MenuBuilderService
{
    /** @var array<int, array<string, mixed>> }> */
    protected array $menuStructure = [];

    public function __construct(
        protected readonly AuthorizationService $authorizationService,
        protected readonly Security $security,
    ) {
    }

    /**
     * @param array<int, array{name: string, mainResource?: string, roles?: string[], children?: array<int, mixed> }> $menuStructure
     */
    public function setMenuStructure(array $menuStructure): void
    {
        $this->menuStructure = $menuStructure;
    }

    /**
     * @return array<int, array{name: string, children: array<int, mixed>}>
     */
    public function getMenuForCurrentUser(): array
    {
        return $this->getMenuLevelForCurrentUser();
    }

    /**
     * @param array<int, mixed>|null $menu
     *
     * @return array<int, array{name: string, children: array<int, mixed>}>
     */
    public function getMenuLevelForCurrentUser(?array $menu = null, int $parent = 0): array
    {
        if (empty($this->menuStructure)) {
            throw new RuntimeException(__CLASS__ . ' must be configured by class implementing MenuBuilderServiceConfiguratorInterface. Menu structure permissions not set.');
        }
        $i = 1;

        return array_values(array_filter(array_map(function ($menuItem) use ($parent, &$i) {
            $included = $this->isMenuItemAvailableByAuthorizationService($menuItem);
            if (!$included) {
                $included = $this->isMenuItemAvailableByUserRole($menuItem);
            }
            $childMenuItems = [];
            if (!empty($menuItem['children'])) {
                $childMenuItems = $this->getMenuLevelForCurrentUser($menuItem['children'], $i);
                $included = !empty($childMenuItems);
            }
            if (!$included) {
                return null;
            }

            return [
                'id' => $parent * 100 + $i++, 'name' => $menuItem['name'], 'children' => $childMenuItems,
            ];
        }, $menu ?? $this->menuStructure)));
    }

    protected function isMenuItemAvailableByAuthorizationService(array $menuItem): bool
    {
        if (array_key_exists('mainResource', $menuItem)) {
            $grantType = $this->authorizationService->calculateFinalGrantType($menuItem['mainResource'],
                AuthorizationService::COL_GET, );

            return GrantType::NONE !== $grantType;
        }

        return false;
    }

    protected function isMenuItemAvailableByUserRole(array $menuItem): bool
    {
        if (array_key_exists('roles', $menuItem)) {
            $user = $this->security->getUser();

            return 0 < count(array_intersect($user instanceof UserInterface ? $user->getRoles() : [], $menuItem['roles']));
        }

        return false;
    }
}
