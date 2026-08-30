<div class="space-y-5">
    @if($heading ?? true)
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                        {{ __('base-tenant::languages.title') }}
                    </h1>

                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium tabular-nums text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        {{ $summary['enabled'] }}/{{ $summary['total'] }}
                    </span>
                </div>

                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::languages.description') }}
                </p>
            </div>
        </div>
    @endif

    {{-- Un idioma activo por debajo del 100 % enseña frases del idioma de
         respaldo a usuarios reales. Es una decisión legítima, pero tiene que
         verse desde arriba y no descubrirse por un aviso de un cliente. --}}
    @if($summary['incomplete'] > 0)
        <div class="flex items-center gap-2 rounded-lg border border-warning-200 bg-warning-50 px-3 py-2 text-sm text-warning-800 dark:border-warning-900 dark:bg-warning-950/40 dark:text-warning-200">
            <flux:icon.exclamation-triangle variant="micro" />
            {{ trans_choice('base-tenant::languages.incomplete_summary', $summary['incomplete'], ['count' => $summary['incomplete']]) }}
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
                    :placeholder="__('base-tenant::languages.search_placeholder')"
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
                    <flux:menu.heading>{{ __('base-tenant::languages.status') }}</flux:menu.heading>

                    <div class="px-2 py-1.5">
                        <flux:select wire:model.live="filterStatus" size="sm">
                            <flux:select.option value="">{{ __('base-tenant::languages.statuses.all') }}</flux:select.option>
                            <flux:select.option value="enabled">{{ __('base-tenant::languages.statuses.enabled') }}</flux:select.option>
                            <flux:select.option value="disabled">{{ __('base-tenant::languages.statuses.disabled') }}</flux:select.option>
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
                        {{ __('base-tenant::languages.status') }}: {{ __('base-tenant::languages.statuses.'.$filterStatus) }}
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
                    <flux:icon :icon="$hasActiveFilters ? 'magnifying-glass' : 'language'" variant="outline" class="size-5" />
                </div>

                @if($hasActiveFilters)
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">
                        {{ __('base-tenant::common.empty_search') }}
                    </p>
                    <p class="mt-1 max-w-sm text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('base-tenant::common.empty_search_hint') }}
                    </p>

                    <flux:button wire:click="resetFilters" variant="ghost" size="sm" icon="x-mark" class="mt-4">
                        {{ __('base-tenant::common.clear_filters') }}
                    </flux:button>
                @else
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">
                        {{ __('base-tenant::languages.empty') }}
                    </p>
                    <p class="mt-1 max-w-sm text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('base-tenant::languages.empty_hint') }}
                    </p>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="sticky top-0 z-10 bg-zinc-50/95 backdrop-blur dark:bg-zinc-900/95">
                        <tr class="border-b border-zinc-200 dark:border-zinc-800">
                            <th scope="col" class="px-4 py-2.5">
                                <button type="button" wire:click="sort('name')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::languages.column.language') }}
                                    <flux:icon :icon="$sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" />
                                </button>
                            </th>

                            <th scope="col" class="w-36 px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('base-tenant::languages.column.status') }}
                            </th>

                            <th scope="col" class="w-64 px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('base-tenant::languages.column.coverage') }}
                            </th>

                            <th scope="col" class="w-16 px-4 py-2.5">
                                <span class="sr-only">{{ __('base-tenant::common.actions') }}</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody wire:loading.delay wire:target="search,filterStatus,sort,resetFilters,toggle,makeDefault" class="divide-y divide-zinc-100 dark:divide-zinc-800">
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
                                        <flux:skeleton class="h-2 w-full rounded-full" />
                                        <flux:skeleton class="h-2.5 w-16" />
                                    </div>
                                </td>
                                <td class="px-4 {{ $rowPadding }}"></td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tbody wire:loading.remove.delay wire:target="search,filterStatus,sort,resetFilters,toggle,makeDefault" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($languages as $language)
                            @php
                                $cobertura = $coverage[$language->code] ?? ['percentage' => 100, 'translated' => 0, 'total' => 0];

                                $nivel = match (true) {
                                    $cobertura['percentage'] >= 100 => 'complete',
                                    $cobertura['percentage'] >= 80 => 'partial',
                                    default => 'thin',
                                };
                            @endphp

                            <tr wire:key="language-{{ $language->id }}" class="group border-l-2 border-transparent transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50" data-coverage-level="{{ $nivel }}">
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $language->native_name }}</span>

                                        @if($language->is_default)
                                            <flux:badge size="sm" color="blue" data-default="true">
                                                {{ __('base-tenant::languages.default') }}
                                            </flux:badge>
                                        @endif
                                    </div>

                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $language->name }} &middot; <span class="font-mono">{{ $language->code }}</span>
                                    </div>
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    {{-- Punto y palabra, no una insignia suelta:
                                         el punto se lee de un vistazo en una
                                         lista larga. --}}
                                    <span class="inline-flex items-center gap-1.5 text-sm text-zinc-600 dark:text-zinc-300" data-language-state="{{ $language->enabled ? 'enabled' : 'disabled' }}">
                                        <span @class(['size-1.5 rounded-full', 'bg-success-500' => $language->enabled, 'bg-zinc-300 dark:bg-zinc-600' => ! $language->enabled])></span>
                                        {{ $language->enabled ? __('base-tenant::languages.statuses.enabled') : __('base-tenant::languages.statuses.disabled') }}
                                    </span>
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                        <div
                                            @class([
                                                'h-full rounded-full transition-all',
                                                'bg-success-500' => $nivel === 'complete',
                                                'bg-warning-500' => $nivel === 'partial',
                                                'bg-danger-500' => $nivel === 'thin',
                                            ])
                                            style="width: {{ min(100, $cobertura['percentage']) }}%"
                                        ></div>
                                    </div>

                                    <div class="mt-1.5 text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                                        {{ $cobertura['percentage'] }}%
                                        &middot;
                                        {{ __('base-tenant::languages.keys_translated', [
                                            'translated' => number_format($cobertura['translated']),
                                            'total' => number_format($cobertura['total']),
                                        ]) }}
                                    </div>
                                </td>

                                <td class="px-4 {{ $rowPadding }} text-right">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" :aria-label="__('base-tenant::common.actions')" />

                                        <flux:menu>
                                            <flux:menu.item wire:click="toggle('{{ $language->code }}')" :icon="$language->enabled ? 'eye-slash' : 'eye'">
                                                {{ $language->enabled ? __('base-tenant::languages.disable') : __('base-tenant::languages.enable') }}
                                            </flux:menu.item>

                                            @if(! $language->is_default)
                                                <flux:menu.separator />

                                                <flux:menu.item wire:click="makeDefault('{{ $language->code }}')" icon="star">
                                                    {{ __('base-tenant::languages.make_default') }}
                                                </flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 bg-zinc-50/60 px-4 py-2.5 dark:border-zinc-800 dark:bg-zinc-900/40">
                <p class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                    {{ trans_choice('base-tenant::languages.count_summary', $languages->count(), ['count' => $languages->count()]) }}
                </p>

                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::languages.measured_against', ['locale' => $reference]) }}
                </p>
            </div>
        @endif
    </div>
</div>
