<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Facades\Menu as MenuFacade;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Menu;
use Base\Tenant\Models\MenuItem;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Lets an account reorder, rename and hide navigation entries without
 * touching the product menu other tenants see.
 */
#[Layout('base-tenant::layouts.app')]
class NavigationManager extends Component
{
    public string $menuKey = 'main';

    public bool $showRestoreModal = false;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasPermission('menus.update'), 403);

        $this->menuKey = collect(config('base-tenant.menu.default_menus', ['main']))->first() ?? 'main';
    }

    public function render(): View
    {
        return view('base-tenant::livewire.navigation-manager', [
            'menus' => Menu::query()->whereNull('account_id')->orderBy('key')->get(),
            'items' => $this->items(),
        ]);
    }

    public function selectMenu(string $key): void
    {
        $this->menuKey = $key;
    }

    public function toggle(string $itemKey): void
    {
        $override = $this->overrideFor($itemKey);

        $override->update(['is_active' => ! $override->is_active]);

        MenuFacade::flush();

        $this->toast(__('base-tenant::menus.visibility_updated'));
    }

    public function move(string $itemKey, string $direction): void
    {
        $items = $this->items();
        $index = $items->search(fn (array $item): bool => $item['key'] === $itemKey);

        if ($index === false) {
            return;
        }

        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swapWith < 0 || $swapWith >= $items->count()) {
            return;
        }

        $current = $this->overrideFor($itemKey);
        $other = $this->overrideFor($items[$swapWith]['key']);

        $currentPosition = $current->position;

        $current->update(['position' => $other->position]);
        $other->update(['position' => $currentPosition]);

        MenuFacade::flush();

        $this->toast(__('base-tenant::menus.order_updated'));
    }

    public function rename(string $itemKey, string $label): void
    {
        $override = $this->overrideFor($itemKey);

        $override->update(['label' => $label ?: null]);

        MenuFacade::flush();

        $this->toast(__('base-tenant::menus.label_updated'));
    }

    public function confirmRestoreDefaults(): void
    {
        $this->showRestoreModal = true;
    }

    public function restoreDefaults(): void
    {
        $this->showRestoreModal = false;

        $account = Tenant::current();

        if (! $account) {
            return;
        }

        MenuItem::query()
            ->whereIn('menu_id', Menu::query()->whereNull('account_id')->pluck('id'))
            ->where('account_id', $account->getKey())
            ->where('is_system', true)
            ->delete();

        MenuFacade::flush();

        $this->toast(__('base-tenant::menus.defaults_restored'));
    }

    /**
     * The entries as this account currently sees them, unfiltered by
     * permission so an administrator can manage what others cannot see.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function items(): Collection
    {
        $menu = Menu::query()->whereNull('account_id')->where('key', $this->menuKey)->first();

        if (! $menu) {
            return new Collection;
        }

        $accountId = Tenant::currentId();

        $global = MenuItem::query()
            ->where('menu_id', $menu->id)
            ->whereNull('account_id')
            ->whereNull('parent_id')
            ->orderBy('position')
            ->get();

        $overrides = MenuItem::query()
            ->where('menu_id', $menu->id)
            ->where('account_id', $accountId)
            ->get()
            ->keyBy('key');

        return $global
            ->map(function (MenuItem $item) use ($overrides): array {
                $override = $overrides->get($item->key);

                return [
                    'key' => $item->key,
                    'label' => $override?->label ?? $item->label,
                    'default_label' => $item->label,
                    'route' => $item->route,
                    'permission' => $item->permission,
                    'is_active' => $override?->is_active ?? $item->is_active,
                    'position' => $override?->position ?? $item->position,
                    'customised' => $override !== null,
                ];
            })
            ->sortBy('position')
            ->values();
    }

    /**
     * Find or create this account's override row for an entry, copying the
     * product defaults so an edit never mutates the shared menu.
     */
    protected function overrideFor(string $itemKey): MenuItem
    {
        $account = Tenant::current();

        abort_unless($account !== null, 403);

        $menu = Menu::query()->whereNull('account_id')->where('key', $this->menuKey)->firstOrFail();

        $global = MenuItem::query()
            ->where('menu_id', $menu->id)
            ->whereNull('account_id')
            ->where('key', $itemKey)
            ->firstOrFail();

        return MenuItem::firstOrCreate(
            [
                'menu_id' => $menu->id,
                'account_id' => $account->getKey(),
                'key' => $itemKey,
            ],
            [
                'label' => $global->label,
                'icon' => $global->icon,
                'route' => $global->route,
                'route_params' => $global->route_params,
                'url' => $global->url,
                'permission' => $global->permission,
                'feature' => $global->feature,
                'position' => $global->position,
                'is_active' => $global->is_active,
                'is_system' => true,
            ]
        );
    }

    protected function toast(string $heading): void
    {
        // Flux takes the message as its first argument; a toast with only a
        // heading throws.
        Flux::toast(text: $heading, variant: 'success');
    }
}
