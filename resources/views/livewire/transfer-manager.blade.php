<div class="space-y-5" @if($polling) wire:poll.3s @endif>
    @if($heading ?? true)
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                        {{ __('base-tenant::transfer.title') }}
                    </h1>

                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium tabular-nums text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        {{ $summary['total'] }}
                    </span>
                </div>

                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::transfer.description') }}
                </p>
            </div>
        </div>
    @endif

    @if($summary['running'] > 0 || $summary['failed'] > 0)
        <div class="flex flex-wrap items-center gap-2">
            @if($summary['running'] > 0)
                <div class="flex items-center gap-2 rounded-lg border border-info-200 bg-info-50 px-3 py-2 text-sm text-info-800 dark:border-info-900 dark:bg-info-950/40 dark:text-info-200">
                    <flux:icon.arrow-path variant="micro" class="animate-spin" />
                    {{ trans_choice('base-tenant::transfer.running_summary', $summary['running'], ['count' => $summary['running']]) }}
                </div>
            @endif

            @if($summary['failed'] > 0)
                <div class="flex items-center gap-2 rounded-lg border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-800 dark:border-danger-900 dark:bg-danger-950/40 dark:text-danger-200">
                    <flux:icon.exclamation-circle variant="micro" />
                    {{ trans_choice('base-tenant::transfer.failed_summary', $summary['failed'], ['count' => $summary['failed']]) }}
                </div>
            @endif
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
                    :placeholder="__('base-tenant::transfer.search_placeholder')"
                    :label:sr-only="__('base-tenant::common.search')"
                />
            </div>

            <div class="h-5 w-px bg-zinc-200 dark:bg-zinc-700"></div>

            <flux:dropdown>
                <flux:button size="sm" icon="funnel" icon:trailing="chevron-down">
                    {{ __('base-tenant::common.filters') }}

                    @php $activos = ($filterType !== '' ? 1 : 0) + ($filterStatus !== '' ? 1 : 0); @endphp

                    @if($activos > 0)
                        <flux:badge size="sm" color="zinc" inset="top bottom">{{ $activos }}</flux:badge>
                    @endif
                </flux:button>

                <flux:menu class="min-w-64">
                    <flux:menu.heading>{{ __('base-tenant::transfer.column.type') }}</flux:menu.heading>

                    <div class="px-2 py-1.5">
                        <flux:select wire:model.live="filterType" size="sm">
                            <flux:select.option value="">{{ __('base-tenant::common.all') }}</flux:select.option>
                            <flux:select.option value="import">{{ __('base-tenant::transfer.types.import') }}</flux:select.option>
                            <flux:select.option value="export">{{ __('base-tenant::transfer.types.export') }}</flux:select.option>
                        </flux:select>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.heading>{{ __('base-tenant::transfer.column.status') }}</flux:menu.heading>

                    <div class="px-2 py-1.5">
                        <flux:select wire:model.live="filterStatus" size="sm">
                            <flux:select.option value="">{{ __('base-tenant::common.all') }}</flux:select.option>
                            @foreach(['pending', 'processing', 'completed', 'failed'] as $estado)
                                <flux:select.option value="{{ $estado }}">{{ __('base-tenant::transfer.statuses.'.$estado) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </flux:menu>
            </flux:dropdown>

            <div class="h-5 w-px bg-zinc-200 dark:bg-zinc-700"></div>

            <flux:button.group>
                <flux:button
                    size="sm"
                    icon="bars-3"
                    :variant="$density === 'comfortable' ? 'filled' : 'ghost'"
                    wire:click="$set('density', 'comfortable')"
                    :aria-label="__('base-tenant::common.density_comfortable')"
                />
                <flux:button
                    size="sm"
                    icon="bars-4"
                    :variant="$density === 'compact' ? 'filled' : 'ghost'"
                    wire:click="$set('density', 'compact')"
                    :aria-label="__('base-tenant::common.density_compact')"
                />
            </flux:button.group>

            <flux:select wire:model.live="perPage" size="sm" class="w-20" :label:sr-only="__('base-tenant::common.per_page')">
                @foreach($perPageOptions as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if($hasActiveFilters)
            <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
                @if($search !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::common.search') }}: {{ $search }}
                        <flux:badge.close wire:click="$set('search', '')" />
                    </flux:badge>
                @endif

                @if($filterType !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::transfer.types.'.$filterType) }}
                        <flux:badge.close wire:click="$set('filterType', '')" />
                    </flux:badge>
                @endif

                @if($filterStatus !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::transfer.statuses.'.$filterStatus) }}
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
                    <flux:icon :icon="$hasActiveFilters ? 'magnifying-glass' : 'arrows-right-left'" variant="outline" class="size-5" />
                </div>

                @if($hasActiveFilters)
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('base-tenant::common.empty_search') }}</p>
                    <p class="mt-1 max-w-sm text-xs text-zinc-500 dark:text-zinc-400">{{ __('base-tenant::common.empty_search_hint') }}</p>

                    <flux:button wire:click="resetFilters" variant="ghost" size="sm" icon="x-mark" class="mt-4">
                        {{ __('base-tenant::common.clear_filters') }}
                    </flux:button>
                @else
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('base-tenant::transfer.empty') }}</p>
                    <p class="mt-1 max-w-sm text-xs text-zinc-500 dark:text-zinc-400">{{ __('base-tenant::transfer.empty_hint') }}</p>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="sticky top-0 z-10 bg-zinc-50/95 backdrop-blur dark:bg-zinc-900/95">
                        <tr class="border-b border-zinc-200 dark:border-zinc-800">
                            <th scope="col" class="px-4 py-2.5">
                                <button type="button" wire:click="sort('name')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::transfer.column.name') }}
                                    <flux:icon :icon="$sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" />
                                </button>
                            </th>

                            <th scope="col" class="w-36 px-4 py-2.5">
                                <button type="button" wire:click="sort('status')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::transfer.column.status') }}
                                    <flux:icon :icon="$sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" />
                                </button>
                            </th>

                            <th scope="col" class="w-56 px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('base-tenant::transfer.column.rows') }}
                            </th>

                            <th scope="col" class="w-44 px-4 py-2.5">
                                <button type="button" wire:click="sort('created_at')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::transfer.column.started') }}
                                    <flux:icon :icon="$sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" />
                                </button>
                            </th>

                            <th scope="col" class="w-16 px-4 py-2.5">
                                <span class="sr-only">{{ __('base-tenant::common.actions') }}</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody wire:loading.delay wire:target="search,filterType,filterStatus,sort,resetFilters,gotoPage,previousPage,nextPage,perPage" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach(range(1, 3) as $fila)
                            <tr>
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="space-y-1.5">
                                        <flux:skeleton class="h-3 w-40" />
                                        <flux:skeleton class="h-2.5 w-24" />
                                    </div>
                                </td>
                                <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="h-4 w-20 rounded-full" /></td>
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="space-y-1.5">
                                        <flux:skeleton class="h-2 w-full rounded-full" />
                                        <flux:skeleton class="h-2.5 w-20" />
                                    </div>
                                </td>
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

                    <tbody wire:loading.remove.delay wire:target="search,filterType,filterStatus,sort,resetFilters,gotoPage,previousPage,nextPage,perPage" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($transfers as $transfer)
                            @php
                                $progreso = $transfer->progress();

                                $colorEstado = match ($transfer->status) {
                                    'completed' => $transfer->hasErrors() ? 'bg-warning-500' : 'bg-success-500',
                                    'failed' => 'bg-danger-500',
                                    'processing' => 'bg-info-500',
                                    default => 'bg-zinc-300 dark:bg-zinc-600',
                                };
                            @endphp

                            <tr wire:key="transfer-{{ $transfer->id }}" class="group border-l-2 border-transparent transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50" data-transfer-status="{{ $transfer->status }}">
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-white">
                                        {{ $transfer->name ?: $transfer->handler }}
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('base-tenant::transfer.types.'.$transfer->type) }}
                                        &middot; <span class="font-mono">{{ $transfer->handler }}</span>
                                    </div>
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    <span class="inline-flex items-center gap-1.5 text-sm text-zinc-600 dark:text-zinc-300">
                                        <span class="size-1.5 rounded-full {{ $colorEstado }}"></span>
                                        {{ __('base-tenant::transfer.statuses.'.$transfer->status) }}
                                    </span>

                                    @if($transfer->status === 'failed' && $transfer->message)
                                        <div class="mt-0.5 max-w-[14rem] truncate text-xs text-danger-600 dark:text-danger-400" title="{{ $transfer->message }}">
                                            {{ $transfer->message }}
                                        </div>
                                    @endif
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    @if($progreso !== null)
                                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                            <div class="h-full rounded-full transition-all {{ $colorEstado }}" style="width: {{ $progreso }}%"></div>
                                        </div>
                                    @endif

                                    <div class="mt-1.5 text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                                        {{ __('base-tenant::transfer.rows_summary', [
                                            'processed' => number_format($transfer->processed_rows),
                                            'total' => number_format($transfer->total_rows),
                                        ]) }}

                                        @if($transfer->hasErrors())
                                            &middot; <span class="text-warning-600 dark:text-warning-400">{{ __('base-tenant::transfer.failed_rows', ['count' => $transfer->failed_rows]) }}</span>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="whitespace-nowrap text-sm tabular-nums text-zinc-900 dark:text-white">
                                        {{ $transfer->created_at?->isoFormat('D MMM YYYY') }}
                                    </div>
                                    <div class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $transfer->created_at?->diffForHumans() }}
                                    </div>
                                </td>

                                <td class="px-4 {{ $rowPadding }} text-right">
                                    @if($transfer->file || $transfer->errorFile)
                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" :aria-label="__('base-tenant::common.actions')" />

                                            <flux:menu>
                                                @if($transfer->file)
                                                    <flux:menu.item href="{{ route('base-tenant.files.show', $transfer->file) }}" icon="arrow-down-tray">
                                                        {{ __('base-tenant::transfer.download') }}
                                                    </flux:menu.item>
                                                @endif

                                                @if($transfer->errorFile)
                                                    <flux:menu.item href="{{ route('base-tenant.files.show', $transfer->errorFile) }}" icon="exclamation-triangle">
                                                        {{ __('base-tenant::transfer.download_errors') }}
                                                    </flux:menu.item>
                                                @endif
                                            </flux:menu>
                                        </flux:dropdown>
                                    @else
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 bg-zinc-50/60 px-4 py-2.5 dark:border-zinc-800 dark:bg-zinc-900/40">
                <p class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::common.showing', [
                        'from' => $transfers->firstItem(),
                        'to' => $transfers->lastItem(),
                        'total' => $transfers->total(),
                    ]) }}
                </p>

                <div>{{ $transfers->links() }}</div>
            </div>
        @endif
    </div>
</div>
