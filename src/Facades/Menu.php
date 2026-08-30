<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Menu\MenuManager;
use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void register(string $menu, Closure $callback)
 * @method static array<int, string> menus()
 * @method static array<int, \Base\Tenant\Menu\MenuItemDefinition> build(string $menu)
 * @method static array{menus: int, items: int} sync()
 * @method static Collection<int, array<string, mixed>> tree(string $menu, ?Authorizable $user = null)
 * @method static void flush()
 * @method static void bootBadgeResolvers()
 *
 * @see MenuManager
 */
class Menu extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MenuManager::class;
    }
}
