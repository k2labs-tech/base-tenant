<div class="py-6">
    <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">{{ __('base-tenant::menus.title') }}</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('base-tenant::menus.description') }}</p>
            </div>

            <flux:button wire:click="confirmRestoreDefaults" variant="subtle" icon="arrow-path">
                {{ __('base-tenant::menus.restore_defaults') }}
            </flux:button>
        </div>

        <div class="mb-4 flex gap-2">
            @foreach($menus as $menu)
                <button type="button"
                        wire:click="selectMenu('{{ $menu->key }}')"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium transition
                               {{ $menuKey === $menu->key
                                  ? 'bg-accent-50 dark:bg-accent-950/40 text-accent-700 dark:text-accent-300'
                                  : 'text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">
                    {{ __('base-tenant::menus.names.'.$menu->key) }}
                </button>
            @endforeach
        </div>

        <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($items as $index => $item)
                    <li class="flex items-center gap-4 px-4 py-3" wire:key="menu-item-{{ $item['key'] }}">
                        <div class="flex flex-col">
                            <button type="button"
                                    wire:click="move('{{ $item['key'] }}', 'up')"
                                    @disabled($index === 0)
                                    class="text-zinc-400 dark:text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-200 disabled:opacity-30">&uarr;</button>
                            <button type="button"
                                    wire:click="move('{{ $item['key'] }}', 'down')"
                                    @disabled($index === $items->count() - 1)
                                    class="text-zinc-400 dark:text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-200 disabled:opacity-30">&darr;</button>
                        </div>

                        <div class="flex-1">
                            <input type="text"
                                   value="{{ $item['label'] }}"
                                   wire:change="rename('{{ $item['key'] }}', $event.target.value)"
                                   class="w-full rounded-md border-zinc-300 dark:border-zinc-600 text-sm shadow-xs focus:border-accent-500 focus:ring-accent-500">
                            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                                {{ $item['key'] }}
                                @if($item['permission'])
                                    &middot; {{ $item['permission'] }}
                                @endif
                                @if($item['customised'])
                                    &middot; {{ __('base-tenant::menus.customised') }}
                                @endif
                            </p>
                        </div>

                        <button type="button"
                                wire:click="toggle('{{ $item['key'] }}')"
                                class="rounded-full px-3 py-1 text-xs font-medium
                                       {{ $item['is_active']
                                          ? 'bg-success-100 dark:bg-success-900/30 text-success-800 dark:text-success-300'
                                          : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400' }}">
                            {{ $item['is_active'] ? __('base-tenant::menus.visible') : __('base-tenant::menus.hidden') }}
                        </button>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('base-tenant::menus.empty') }}
                    </li>
                @endforelse
            </ul>
        </div>
    </div>

    <flux:modal wire:model="showRestoreModal" name="confirm-menu-restore" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ __('base-tenant::menus.confirm_restore_title', ['menu' => __('base-tenant::menus.names.'.$menuKey)]) }}
                </flux:heading>
                <flux:subheading>{{ __('base-tenant::menus.confirm_restore') }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('base-tenant::common.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button wire:click="restoreDefaults" variant="danger">
                    <span wire:loading.remove wire:target="restoreDefaults">{{ __('base-tenant::menus.restore_defaults') }}</span>
                    <span wire:loading wire:target="restoreDefaults">{{ __('base-tenant::common.processing') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
