<div class="space-y-5">
    @if($heading ?? true)
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                        {{ __('base-tenant::metering.title') }}
                    </h1>

                    {{-- El recuento son todas las métricas de la cuenta, no las
                         que dejan ver los filtros. --}}
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium tabular-nums text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        {{ $summary['total'] }}
                    </span>
                </div>

                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::metering.description') }}
                </p>
            </div>
        </div>
    @endif

    {{-- Lo que hay que saber antes de recorrer la tabla: qué está tocando techo
         y qué está a punto. Nada de esto se ve leyendo fila a fila. --}}
    @if($summary['at_limit'] > 0 || $summary['approaching'] > 0)
        <div class="flex flex-wrap items-center gap-2">
            @if($summary['at_limit'] > 0)
                <div class="flex items-center gap-2 rounded-lg border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-800 dark:border-danger-900 dark:bg-danger-950/40 dark:text-danger-200">
                    <flux:icon.exclamation-circle variant="micro" />
                    {{ trans_choice('base-tenant::metering.at_limit_summary', $summary['at_limit'], ['count' => $summary['at_limit']]) }}
                </div>
            @endif

            @if($summary['approaching'] > 0)
                <div class="flex items-center gap-2 rounded-lg border border-warning-200 bg-warning-50 px-3 py-2 text-sm text-warning-800 dark:border-warning-900 dark:bg-warning-950/40 dark:text-warning-200">
                    <flux:icon.exclamation-triangle variant="micro" />
                    {{ trans_choice('base-tenant::metering.approaching_summary', $summary['approaching'], ['count' => $summary['approaching']]) }}
                </div>
            @endif
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        {{-- Barra de herramientas: buscar | filtrar | ver, en ese orden y con
             las tres zonas separadas, igual que en el resto de pantallas. --}}
        <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 bg-zinc-50/60 px-3 py-2.5 dark:border-zinc-800 dark:bg-zinc-900/40">
            <div class="min-w-56 flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    clearable
                    size="sm"
                    type="search"
                    :placeholder="__('base-tenant::metering.search_placeholder')"
                    :label:sr-only="__('base-tenant::common.search')"
                />
            </div>

            <div class="h-5 w-px bg-zinc-200 dark:bg-zinc-700"></div>

            <flux:dropdown>
                <flux:button size="sm" icon="funnel" icon:trailing="chevron-down">
                    {{ __('base-tenant::common.filters') }}

                    @if($filterScope !== '')
                        <flux:badge size="sm" color="zinc" inset="top bottom">1</flux:badge>
                    @endif
                </flux:button>

                <flux:menu class="min-w-64">
                    <flux:menu.heading>{{ __('base-tenant::metering.scope') }}</flux:menu.heading>

                    <div class="px-2 py-1.5">
                        <flux:select wire:model.live="filterScope" size="sm">
                            <flux:select.option value="">{{ __('base-tenant::metering.filter.all') }}</flux:select.option>
                            <flux:select.option value="capped">{{ __('base-tenant::metering.filter.capped') }}</flux:select.option>
                            <flux:select.option value="uncapped">{{ __('base-tenant::metering.filter.uncapped') }}</flux:select.option>
                        </flux:select>
                    </div>
                </flux:menu>
            </flux:dropdown>

            <div class="h-5 w-px bg-zinc-200 dark:bg-zinc-700"></div>

            {{-- Densidad sí; tamaño de página no. Las métricas las declara la
                 configuración, no crecen con el uso y esta pantalla no pagina:
                 un selector que no gobierna nada engaña sobre lo que hace. --}}
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
            @php
                $scopeLabels = [
                    'capped' => __('base-tenant::metering.filter.capped'),
                    'uncapped' => __('base-tenant::metering.filter.uncapped'),
                ];
            @endphp

            <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
                @if($search !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::common.search') }}: {{ $search }}
                        <flux:badge.close wire:click="$set('search', '')" />
                    </flux:badge>
                @endif

                @if($filterScope !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::metering.scope') }}: {{ $scopeLabels[$filterScope] ?? $filterScope }}
                        <flux:badge.close wire:click="$set('filterScope', '')" />
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
                    <flux:icon :icon="$hasActiveFilters ? 'magnifying-glass' : 'chart-bar'" variant="outline" class="size-5" />
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
                        {{ __('base-tenant::metering.empty') }}
                    </p>
                    <p class="mt-1 max-w-sm text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('base-tenant::metering.empty_hint') }}
                    </p>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="sticky top-0 z-10 bg-zinc-50/95 backdrop-blur dark:bg-zinc-900/95">
                        <tr class="border-b border-zinc-200 dark:border-zinc-800">
                            <th scope="col" class="px-4 py-2.5">
                                <button type="button" wire:click="sort('metric')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::metering.column.metric') }}
                                    <flux:icon :icon="$sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" class="transition-opacity" />
                                </button>
                            </th>

                            <th scope="col" class="w-32 px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('base-tenant::metering.column.period') }}
                            </th>

                            <th scope="col" class="w-72 px-4 py-2.5">
                                <button type="button" wire:click="sort('usage')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::metering.column.usage') }}
                                    <flux:icon :icon="$sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" class="transition-opacity" />
                                </button>
                            </th>

                            @if($showsRemaining)
                                <th scope="col" class="w-40 px-4 py-2.5 text-right text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                    {{ __('base-tenant::metering.column.remaining') }}
                                </th>
                            @endif
                        </tr>
                    </thead>

                    {{-- Tres filas de esqueleto mientras la tabla se rehace, con
                         el mismo número de celdas que una fila real: una de
                         menos endereza la tabla al desaparecer y la tuerce al
                         aparecer. --}}
                    <tbody wire:loading.delay wire:target="search,filterScope,sort,resetFilters" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach(range(1, 3) as $fila)
                            <tr>
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="space-y-1.5">
                                        <flux:skeleton class="h-3 w-40" />
                                        <flux:skeleton class="h-2.5 w-28" />
                                    </div>
                                </td>

                                <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="h-3 w-16" /></td>

                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="space-y-1.5">
                                        <flux:skeleton class="h-2 w-full rounded-full" />
                                        <flux:skeleton class="h-2.5 w-24" />
                                    </div>
                                </td>

                                @if($showsRemaining)
                                    <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="ml-auto h-3 w-16" /></td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>

                    <tbody wire:loading.remove.delay wire:target="search,filterScope,sort,resetFilters" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($metrics as $row)
                            @php
                                // El color lo decide lo consumido, no la métrica:
                                // el mismo verde en todo hasta que deja de serlo
                                // es lo que hace legible una lista larga.
                                $level = match (true) {
                                    $row['percentage'] === null => 'none',
                                    $row['percentage'] >= 100 => 'exceeded',
                                    $row['percentage'] >= 80 => 'warning',
                                    default => 'ok',
                                };

                                $barColour = match ($level) {
                                    'exceeded' => 'bg-danger-500',
                                    'warning' => 'bg-warning-500',
                                    'ok' => 'bg-accent-500',
                                    default => 'bg-zinc-300 dark:bg-zinc-600',
                                };
                            @endphp

                            <tr wire:key="metric-{{ $row['key'] }}" class="group border-l-2 border-transparent transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50" data-usage-level="{{ $level }}">
                                {{-- Celda primaria compuesta: el nombre legible
                                     como dato y la clave real como metadato, que
                                     es lo que se configura y lo que se busca. --}}
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ $row['name'] }}</div>
                                    <div class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $row['key'] }}</div>
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    <span class="whitespace-nowrap text-sm text-zinc-600 dark:text-zinc-300">{{ $row['period'] }}</span>
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    @if($row['percentage'] !== null)
                                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                            <div class="h-full rounded-full transition-all {{ $barColour }}" style="width: {{ min(100, $row['percentage']) }}%"></div>
                                        </div>

                                        <div class="mt-1.5 flex items-baseline gap-1.5">
                                            <span class="text-sm font-medium tabular-nums text-zinc-900 dark:text-white">
                                                {{ number_format($row['value']) }}
                                            </span>
                                            <span class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                                                {{ __('base-tenant::metering.of_limit', ['limit' => number_format($row['limit'])]) }}
                                                &middot; {{ $row['percentage'] }}%
                                            </span>
                                        </div>
                                    @else
                                        <div class="flex items-baseline gap-1.5">
                                            <span class="text-sm font-medium tabular-nums text-zinc-900 dark:text-white">
                                                {{ number_format($row['value']) }}
                                            </span>
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ __('base-tenant::metering.no_limit') }}
                                            </span>
                                        </div>
                                    @endif
                                </td>

                                @if($showsRemaining)
                                    <td class="px-4 {{ $rowPadding }} text-right">
                                        @if($row['limit'] === -1)
                                            <span class="text-sm text-zinc-500 dark:text-zinc-400">&infin;</span>
                                        @else
                                            <span class="text-sm font-medium tabular-nums text-zinc-900 dark:text-white">
                                                {{ number_format($row['remaining']) }}
                                            </span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Sin paginación porque no hay páginas: las métricas las declara
                 la configuración y caben enteras. El recuento sí, en el mismo
                 sitio que en el resto de pantallas. --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 bg-zinc-50/60 px-4 py-2.5 dark:border-zinc-800 dark:bg-zinc-900/40">
                <p class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                    {{ trans_choice('base-tenant::metering.count_summary', $metrics->count(), ['count' => $metrics->count()]) }}
                </p>
            </div>
        @endif
    </div>
</div>
