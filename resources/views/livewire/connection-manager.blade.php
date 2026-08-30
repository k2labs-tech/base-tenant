<div class="space-y-5">
    @if($heading ?? true)
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                        {{ __('base-tenant::connections.title') }}
                    </h1>

                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium tabular-nums text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        {{ $summary['total'] }}
                    </span>
                </div>

                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::connections.description') }}
                </p>
            </div>
        </div>
    @endif

    {{-- Una credencial caducada para de sincronizar sin avisar a nadie: la
         cifra va arriba y no escondida en una fila. --}}
    @if($summary['failing'] > 0)
        <div class="flex items-center gap-2 rounded-lg border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-800 dark:border-danger-900 dark:bg-danger-950/40 dark:text-danger-200">
            <flux:icon.exclamation-circle variant="micro" />
            {{ trans_choice('base-tenant::connections.summary.failing', $summary['failing'], ['count' => $summary['failing']]) }}
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 bg-zinc-50/60 px-3 py-2.5 dark:border-zinc-800 dark:bg-zinc-900/40">
            <div class="min-w-56 flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    clearable
                    size="sm"
                    type="search"
                    :placeholder="__('base-tenant::connections.search_placeholder')"
                    :label:sr-only="__('base-tenant::common.search')"
                />
            </div>

            <div class="h-5 w-px bg-zinc-200 dark:bg-zinc-700"></div>

            <flux:dropdown>
                <flux:button size="sm" icon="funnel" icon:trailing="chevron-down">
                    {{ __('base-tenant::common.filters') }}

                    @if($filterStatus !== '')
                        <flux:badge size="sm" color="zinc" inset="top bottom">1</flux:badge>
                    @endif
                </flux:button>

                <flux:menu class="min-w-64">
                    <flux:menu.heading>{{ __('base-tenant::connections.column.status') }}</flux:menu.heading>

                    <div class="px-2 py-1.5">
                        <flux:select wire:model.live="filterStatus" size="sm">
                            <flux:select.option value="">{{ __('base-tenant::common.all') }}</flux:select.option>
                            @foreach(['healthy', 'failing', 'unknown'] as $estado)
                                <flux:select.option value="{{ $estado }}">{{ __('base-tenant::connections.statuses.'.$estado) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </flux:menu>
            </flux:dropdown>

            <div class="h-5 w-px bg-zinc-200 dark:bg-zinc-700"></div>

            <flux:button.group>
                <flux:button size="sm" icon="bars-3" :variant="$density === 'comfortable' ? 'filled' : 'ghost'" wire:click="$set('density', 'comfortable')" :aria-label="__('base-tenant::common.density_comfortable')" />
                <flux:button size="sm" icon="bars-4" :variant="$density === 'compact' ? 'filled' : 'ghost'" wire:click="$set('density', 'compact')" :aria-label="__('base-tenant::common.density_compact')" />
            </flux:button.group>
        </div>

        @if($hasActiveFilters)
            <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
                @if($search !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::common.search') }}: {{ $search }}
                        <flux:badge.close wire:click="$set('search', '')" />
                    </flux:badge>
                @endif

                @if($filterStatus !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::connections.statuses.'.$filterStatus) }}
                        <flux:badge.close wire:click="$set('filterStatus', '')" />
                    </flux:badge>
                @endif

                <flux:link href="#" variant="subtle" class="text-xs" wire:click.prevent="resetFilters">
                    {{ __('base-tenant::common.clear_filters') }}
                </flux:link>
            </div>
        @endif

        @if($isEmptyTable)
            <div class="flex flex-col items-center px-6 py-16 text-center">
                <div class="mb-4 flex size-11 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">
                    <flux:icon :icon="$hasActiveFilters ? 'magnifying-glass' : 'link'" variant="outline" class="size-5" />
                </div>

                @if($hasActiveFilters)
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('base-tenant::common.empty_search') }}</p>
                    <p class="mt-1 max-w-sm text-xs text-zinc-500 dark:text-zinc-400">{{ __('base-tenant::common.empty_search_hint') }}</p>

                    <flux:button wire:click="resetFilters" variant="ghost" size="sm" icon="x-mark" class="mt-4">
                        {{ __('base-tenant::common.clear_filters') }}
                    </flux:button>
                @else
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('base-tenant::connections.empty') }}</p>
                    <p class="mt-1 max-w-sm text-xs text-zinc-500 dark:text-zinc-400">{{ __('base-tenant::connections.empty_hint') }}</p>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="sticky top-0 z-10 bg-zinc-50/95 backdrop-blur dark:bg-zinc-900/95">
                        <tr class="border-b border-zinc-200 dark:border-zinc-800">
                            <th scope="col" class="px-4 py-2.5">
                                <button type="button" wire:click="sort('provider')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::connections.column.provider') }}
                                    <flux:icon :icon="$sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" />
                                </button>
                            </th>

                            <th scope="col" class="w-40 px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('base-tenant::connections.column.status') }}
                            </th>

                            <th scope="col" class="w-48 px-4 py-2.5">
                                <button type="button" wire:click="sort('checked_at')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::connections.column.checked') }}
                                    <flux:icon :icon="$sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" />
                                </button>
                            </th>

                            <th scope="col" class="w-16 px-4 py-2.5">
                                <span class="sr-only">{{ __('base-tenant::common.actions') }}</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody wire:loading.delay wire:target="search,filterStatus,sort,resetFilters,check,disconnect" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach(range(1, 3) as $fila)
                            <tr>
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="space-y-1.5">
                                        <flux:skeleton class="h-3 w-32" />
                                        <flux:skeleton class="h-2.5 w-20" />
                                    </div>
                                </td>
                                <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="h-4 w-20 rounded-full" /></td>
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="space-y-1.5">
                                        <flux:skeleton class="h-3 w-24" />
                                        <flux:skeleton class="h-2.5 w-16" />
                                    </div>
                                </td>
                                <td class="px-4 {{ $rowPadding }}"></td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tbody wire:loading.remove.delay wire:target="search,filterStatus,sort,resetFilters,check,disconnect" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($connections as $connection)
                            @php
                                $color = match ($connection->status) {
                                    'healthy' => 'bg-success-500',
                                    'failing' => 'bg-danger-500',
                                    default => 'bg-zinc-300 dark:bg-zinc-600',
                                };
                            @endphp

                            <tr wire:key="connection-{{ $connection->id }}" class="group border-l-2 border-transparent transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50" data-connection-status="{{ $connection->status }}">
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ $connection->provider }}</div>
                                    <div class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $connection->label }}</div>
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    <span class="inline-flex items-center gap-1.5 text-sm text-zinc-600 dark:text-zinc-300">
                                        <span class="size-1.5 rounded-full {{ $color }}"></span>
                                        {{ __('base-tenant::connections.statuses.'.$connection->status) }}
                                    </span>

                                    @if($connection->status === 'failing' && $connection->status_message)
                                        <div class="mt-0.5 max-w-[16rem] truncate text-xs text-danger-600 dark:text-danger-400" title="{{ $connection->status_message }}">
                                            {{ $connection->status_message }}
                                        </div>
                                    @endif
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    @if($connection->checked_at)
                                        <div class="whitespace-nowrap text-sm tabular-nums text-zinc-900 dark:text-white">
                                            {{ $connection->checked_at->isoFormat('D MMM YYYY') }}
                                        </div>
                                        <div class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $connection->checked_at->diffForHumans() }}
                                        </div>
                                    @else
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('base-tenant::connections.never_checked') }}</span>
                                    @endif
                                </td>

                                <td class="px-4 {{ $rowPadding }} text-right">
                                    <div class="flex items-center justify-end gap-0.5">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="arrow-path"
                                            wire:click="check('{{ $connection->id }}')"
                                            class="opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
                                            :aria-label="__('base-tenant::connections.check_now')"
                                        />

                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" :aria-label="__('base-tenant::common.actions')" />

                                            <flux:menu>
                                                <flux:menu.item wire:click="check('{{ $connection->id }}')" icon="arrow-path">
                                                    {{ __('base-tenant::connections.check_now') }}
                                                </flux:menu.item>

                                                <flux:menu.separator />

                                                <flux:menu.item wire:click="confirmDelete('{{ $connection->id }}')" variant="danger" icon="trash">
                                                    {{ __('base-tenant::connections.disconnect') }}
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <flux:modal wire:model="showDeleteModal" name="confirm-connection-removal" class="min-w-[22rem]">
        @php($aEliminar = $this->deleting())

        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('base-tenant::connections.disconnect') }}</flux:heading>
                <flux:subheading>{{ $aEliminar?->provider }} · {{ $aEliminar?->label }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('base-tenant::common.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button wire:click="disconnect" variant="danger">{{ __('base-tenant::connections.disconnect') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
