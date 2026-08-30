<?php

declare(strict_types=1);

namespace Base\Tenant\Menu;

use Base\Tenant\Facades\Feature;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Menu;
use Base\Tenant\Models\MenuItem;
use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Navigation stored in the database and declared from code.
 *
 * Modules register their entries at boot; `sync()` writes them as the product
 * menu, and each account may override ordering, labels and visibility without
 * a deployment. Rendering filters by permission and feature flag.
 */
class MenuManager
{
    protected const VERSION_KEY = 'base-tenant:menu:version';

    /** @var array<string, array<int, Closure>> */
    protected array $definitions = [];

    /** @var array<string, Closure> */
    protected array $badgeResolvers = [];

    /**
     * Declare entries for a menu. Called from a service provider's boot.
     *
     *     Menu::register('main', function (MenuBuilder $menu): void {
     *         $menu->item('invoices')
     *             ->label('app.navigation.invoices')
     *             ->route('invoices.index')
     *             ->permission('invoices.view')
     *             ->badge(fn () => Invoice::pending()->count());
     *     });
     */
    public function register(string $menu, Closure $callback): void
    {
        $this->definitions[$menu][] = $callback;
    }

    /** @return array<int, string> */
    public function menus(): array
    {
        return array_keys($this->definitions);
    }

    /**
     * Run the registered callbacks and return the declared entries, ordered.
     *
     * @return array<int, MenuItemDefinition>
     */
    public function build(string $menu): array
    {
        $builder = new MenuBuilder;

        foreach ($this->definitions[$menu] ?? [] as $callback) {
            $callback($builder);
        }

        $items = $builder->all();

        usort($items, fn (MenuItemDefinition $a, MenuItemDefinition $b): int => $a->position <=> $b->position);

        return $items;
    }

    /**
     * Persist the code-declared menus as the product menu, removing entries
     * that are no longer declared.
     *
     * @return array{menus: int, items: int}
     */
    public function sync(): array
    {
        $menuCount = 0;
        $itemCount = 0;

        foreach ($this->menus() as $key) {
            $menu = Menu::firstOrCreate(['account_id' => null, 'key' => $key], ['name' => $key]);
            $menuCount++;

            $synced = $this->syncItems($menu, $this->build($key), null);
            $itemCount += count($synced);

            MenuItem::query()
                ->where('menu_id', $menu->id)
                ->whereNull('account_id')
                ->where('is_system', true)
                ->whereNotIn('key', $synced)
                ->delete();

            $this->registerBadgeResolvers($this->build($key));
        }

        $this->flush();

        return ['menus' => $menuCount, 'items' => $itemCount];
    }

    /**
     * The menu as this request should see it: account overrides applied,
     * entries the user cannot reach removed, badges resolved.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function tree(string $menu, ?Authorizable $user = null): Collection
    {
        $user ??= auth()->user();

        return collect($this->resolveStructure($menu))
            ->pipe(fn (Collection $items): Collection => $this->present($items, $user));
    }

    /**
     * Invalidate every cached tree, for every account, by bumping the version
     * stamp the cache keys are built from.
     */
    /**
     * El rastro hasta la entrada activa: sección, padres y entrada.
     *
     * Sale del mismo árbol que pinta la barra lateral, así que no hay una
     * segunda lista que mantener sincronizada — y una entrada que se oculte por
     * permiso o por feature desaparece también de las migas, sin hacer nada.
     *
     * @return array<int, array{title: string, href: string|null}>
     */
    public function trail(?Authorizable $user = null): array
    {
        foreach ($this->menus() as $menu) {
            $rama = $this->activeBranch($this->tree($menu, $user));

            if ($rama !== []) {
                return [
                    ['title' => __('base-tenant::menus.names.'.$menu), 'href' => null],
                    ...$rama,
                ];
            }
        }

        return [];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, array{title: string, href: string|null}>
     */
    protected function activeBranch(Collection $items): array
    {
        foreach ($items as $item) {
            if (! ($item['active'] ?? false)) {
                continue;
            }

            $hijos = $this->activeBranch(collect($item['children'] ?? []));

            return [
                ['title' => $item['title'], 'href' => $item['href']],
                ...$hijos,
            ];
        }

        return [];
    }

    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, $this->version() + 1);
    }

    /**
     * Merged structure for the account in context, before permission and
     * feature filtering. This is the part worth caching.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function resolveStructure(string $menu): array
    {
        if (! config('base-tenant.menu.cache.enabled', true)) {
            return $this->mergeStructure($menu);
        }

        return Cache::remember(
            $this->cacheKey($menu),
            (int) config('base-tenant.menu.cache.ttl', 3600),
            fn (): array => $this->mergeStructure($menu)
        );
    }

    /** @return array<int, array<string, mixed>> */
    protected function mergeStructure(string $menu): array
    {
        $model = Menu::query()->whereNull('account_id')->where('key', $menu)->first();

        if (! $model) {
            return [];
        }

        $accountId = Tenant::currentId();

        $rows = MenuItem::query()
            ->where('menu_id', $model->id)
            ->where(function ($query) use ($accountId): void {
                $query->whereNull('account_id');

                if ($accountId !== null) {
                    $query->orWhere('account_id', $accountId);
                }
            })
            ->orderBy('position')
            ->get();

        $keyById = $rows->pluck('key', 'id')->all();

        $effective = [];

        foreach ($rows->whereNull('account_id') as $row) {
            $effective[$row->key] = $this->rowToArray($row, $keyById);
        }

        foreach ($rows->whereNotNull('account_id') as $row) {
            $override = $this->rowToArray($row, $keyById);

            $effective[$row->key] = isset($effective[$row->key])
                ? [...$effective[$row->key], ...array_filter($override, static fn ($value): bool => $value !== null)]
                : $override;
        }

        return $this->buildTree($effective);
    }

    /**
     * @param  array<string, string>  $keyById
     * @return array<string, mixed>
     */
    protected function rowToArray(MenuItem $row, array $keyById): array
    {
        return [
            'key' => $row->key,
            'parent_key' => $row->parent_id ? ($keyById[$row->parent_id] ?? null) : null,
            'label' => $row->label,
            'icon' => $row->icon,
            'route' => $row->route,
            'route_params' => $row->route_params ?? [],
            'url' => $row->url,
            'target' => $row->target,
            'permission' => $row->permission,
            'feature' => $row->feature,
            'badge' => $row->badge,
            'position' => $row->position,
            'is_active' => $row->is_active,
            'meta' => $row->meta ?? [],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function buildTree(array $items): array
    {
        $byParent = [];

        foreach ($items as $item) {
            $byParent[$item['parent_key'] ?? ''][] = $item;
        }

        foreach ($byParent as &$group) {
            usort($group, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);
        }

        unset($group);

        $attach = function (array $item) use (&$attach, $byParent): array {
            $item['children'] = array_map($attach, $byParent[$item['key']] ?? []);

            return $item;
        };

        return array_map($attach, $byParent[''] ?? []);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    protected function present(Collection $items, ?Authorizable $user): Collection
    {
        return $items
            ->filter(fn (array $item): bool => $this->isVisible($item, $user))
            ->map(function (array $item) use ($user): array {
                $item['children'] = $this->present(collect($item['children'] ?? []), $user)->values()->all();
                $item['href'] = $this->resolveHref($item);
                $item['badge'] = $this->resolveBadge($item);
                $item['active'] = $this->isCurrent($item);
                $item['title'] = $this->resolveLabel($item);

                return $item;
            })
            ->reject(fn (array $item): bool => $item['href'] === null && $item['children'] === [])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function isVisible(array $item, ?Authorizable $user): bool
    {
        if (! ($item['is_active'] ?? true)) {
            return false;
        }

        if ($item['permission'] && (! $user || ! $user->can($item['permission']))) {
            return false;
        }

        if ($item['feature'] && ! Feature::active($item['feature'])) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveHref(array $item): ?string
    {
        if ($item['url']) {
            return $item['url'];
        }

        if (! $item['route'] || ! app('router')->has($item['route'])) {
            return null;
        }

        return route($item['route'], $item['route_params'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveLabel(array $item): string
    {
        return __($item['label'] ?: $item['key']);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resolveBadge(array $item): ?string
    {
        $resolver = $this->badgeResolvers[$item['key']] ?? null;

        if ($resolver) {
            $value = $resolver();

            return $value === null || $value === 0 ? null : (string) $value;
        }

        return $item['badge'] ?: null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function isCurrent(array $item): bool
    {
        return $item['route'] !== null && request()->routeIs($item['route']);
    }

    /**
     * @param  array<int, MenuItemDefinition>  $definitions
     * @return array<int, string>
     */
    protected function syncItems(Menu $menu, array $definitions, ?string $parentId): array
    {
        $keys = [];

        foreach ($definitions as $definition) {
            $item = MenuItem::updateOrCreate(
                ['menu_id' => $menu->id, 'account_id' => null, 'key' => $definition->key],
                [
                    'parent_id' => $parentId,
                    'label' => $definition->label,
                    'icon' => $definition->icon,
                    'route' => $definition->route,
                    'route_params' => $definition->routeParams ?: null,
                    'url' => $definition->url,
                    'target' => $definition->target,
                    'permission' => $definition->permission,
                    'feature' => $definition->feature,
                    'badge' => $definition->badge,
                    'position' => $definition->position,
                    'is_system' => true,
                    'meta' => $definition->meta ?: null,
                ]
            );

            $keys[] = $definition->key;

            if ($definition->children !== []) {
                $keys = [...$keys, ...$this->syncItems($menu, $definition->children, $item->id)];
            }
        }

        return $keys;
    }

    /**
     * @param  array<int, MenuItemDefinition>  $definitions
     */
    protected function registerBadgeResolvers(array $definitions): void
    {
        foreach ($definitions as $definition) {
            if ($definition->badgeResolver) {
                $this->badgeResolvers[$definition->key] = $definition->badgeResolver;
            }

            $this->registerBadgeResolvers($definition->children);
        }
    }

    protected function cacheKey(string $menu): string
    {
        return Tenant::cacheKey('base-tenant:menu:v'.$this->version().":{$menu}");
    }

    protected function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    /**
     * Make badge resolvers available without a full sync, so rendering after a
     * cold boot still shows counters.
     */
    public function bootBadgeResolvers(): void
    {
        foreach ($this->menus() as $menu) {
            $this->registerBadgeResolvers($this->build($menu));
        }
    }
}
