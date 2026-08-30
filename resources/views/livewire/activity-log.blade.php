@php
    // `Flux::pro()` es solo `isInstalled('livewire/flux-pro')`, así que se puede
    // llamar sin el paquete: devuelve false. Lo que NO se puede es escribir
    // `<flux:date-picker>` tal cual dentro de un `@if`, porque Blade resuelve
    // las etiquetas al compilar y no al pintar: la vista entera reventaría en
    // `view:cache` de cualquier instalación sin Pro. Por eso va por
    // `x-dynamic-component`, que se resuelve en tiempo de ejecución.
    $hasPro = Flux\Flux::pro();
@endphp

<div class="space-y-5">
    @if($heading ?? true)
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2.5">
                    <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                        {{ __('base-tenant::activity.title') }}
                    </h1>

                    {{-- El recuento son las entradas que alcanza quien mira, sin
                         filtros: el tamaño del registro es en sí un dato. --}}
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium tabular-nums text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        {{ $summary['total'] }}
                    </span>
                </div>

                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::activity.description_line') }}
                </p>
            </div>
        </div>
    @endif

    {{-- Aquí no hay tira de contexto a propósito: en un registro todas las
         entradas pesan lo mismo y ninguna pide nada. Una tira que dijera
         «hay entradas» no añadiría nada que no diga ya el recuento. --}}

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        {{-- Barra de herramientas: buscar | filtrar | ver. Siempre en ese
             orden y siempre separadas, para que no se confunda lo que acota
             la lista con lo que cambia cómo se mira. --}}
        <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 bg-zinc-50/60 px-3 py-2.5 dark:border-zinc-800 dark:bg-zinc-900/40">
            <div class="min-w-56 flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    clearable
                    size="sm"
                    type="search"
                    :placeholder="__('base-tenant::activity.search_placeholder')"
                    :label:sr-only="__('base-tenant::common.search')"
                />
            </div>

            <div class="h-5 w-px bg-zinc-200 dark:bg-zinc-700"></div>

            <flux:dropdown>
                <flux:button size="sm" icon="funnel" icon:trailing="chevron-down">
                    {{ __('base-tenant::common.filters') }}

                    @if($activeFilterCount > 0)
                        <flux:badge size="sm" color="zinc" inset="top bottom">{{ $activeFilterCount }}</flux:badge>
                    @endif
                </flux:button>

                <flux:menu class="min-w-72">
                    <flux:menu.heading>{{ __('base-tenant::activity.action') }}</flux:menu.heading>

                    <div class="px-2 py-1.5">
                        <flux:select wire:model.live="filterAction" size="sm">
                            <flux:select.option value="">{{ __('base-tenant::activity.all_actions') }}</flux:select.option>

                            @foreach($actions as $action)
                                <flux:select.option value="{{ $action }}">{{ ucfirst($action) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.heading>{{ __('base-tenant::activity.user') }}</flux:menu.heading>

                    <div class="px-2 py-1.5">
                        {{--
                            Con muchos autores una lista nativa deja de servir.
                            El combobox es de Pro; la etiqueta es la misma
                            `flux:select`, así que compila igual sin el paquete
                            y solo cambia la variante.
                        --}}
                        @if($hasPro && $causers->count() > 10)
                            <flux:select wire:model.live="filterUser" variant="combobox" size="sm">
                                <flux:select.option value="">{{ __('base-tenant::activity.all_users') }}</flux:select.option>

                                @foreach($causers as $causer)
                                    <flux:select.option value="{{ $causer->id }}">{{ $causer->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @else
                            <flux:select wire:model.live="filterUser" size="sm">
                                <flux:select.option value="">{{ __('base-tenant::activity.all_users') }}</flux:select.option>

                                @foreach($causers as $causer)
                                    <flux:select.option value="{{ $causer->id }}">{{ $causer->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif
                    </div>

                    <flux:menu.separator />

                    <flux:menu.heading>{{ __('base-tenant::activity.date_range') }}</flux:menu.heading>

                    <div class="space-y-2 px-2 py-1.5">
                        @if($hasPro)
                            <x-dynamic-component
                                component="flux:date-picker"
                                mode="range"
                                wire:model.live="filterFrom"
                                wire:model.live.end="filterUntil"
                            />
                        @else
                            <flux:input
                                wire:model.live="filterFrom"
                                type="date"
                                size="sm"
                                :label="__('base-tenant::activity.date_from')"
                            />

                            <flux:input
                                wire:model.live="filterUntil"
                                type="date"
                                size="sm"
                                :label="__('base-tenant::activity.date_until')"
                            />
                        @endif
                    </div>
                </flux:menu>
            </flux:dropdown>

            <div class="h-5 w-px bg-zinc-200 dark:bg-zinc-700"></div>

            {{-- Densidad: quien revisa el registro a diario lo quiere compacto. --}}
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

                @if($filterAction !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::activity.action') }}: {{ ucfirst($filterAction) }}
                        <flux:badge.close wire:click="$set('filterAction', '')" />
                    </flux:badge>
                @endif

                @if($filterUser !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::activity.user') }}: {{ $causers->firstWhere('id', $filterUser)?->name ?? $filterUser }}
                        <flux:badge.close wire:click="$set('filterUser', '')" />
                    </flux:badge>
                @endif

                @if($filterFrom !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::activity.date_from') }}: {{ $filterFrom }}
                        <flux:badge.close wire:click="$set('filterFrom', '')" />
                    </flux:badge>
                @endif

                @if($filterUntil !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::activity.date_until') }}: {{ $filterUntil }}
                        <flux:badge.close wire:click="$set('filterUntil', '')" />
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
                    <flux:icon :icon="$hasActiveFilters ? 'magnifying-glass' : 'clock'" variant="outline" class="size-5" />
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
                        {{ __('base-tenant::activity.no_activities_found') }}
                    </p>
                    <p class="mt-1 max-w-sm text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('base-tenant::activity.empty_description') }}
                    </p>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="sticky top-0 z-10 bg-zinc-50/95 backdrop-blur dark:bg-zinc-900/95">
                        <tr class="border-b border-zinc-200 dark:border-zinc-800">
                            <th scope="col" class="w-44 whitespace-nowrap px-4 py-2.5">
                                {{-- Sin orden pedido manda el de por defecto, que
                                     aquí es descendente: la flecha tiene que
                                     pintar eso y no el 'asc' inicial. --}}
                                <button type="button" wire:click="sort('created_at')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::activity.date') }}
                                    <flux:icon :icon="($sortBy === 'created_at' || $sortBy === null) && $effectiveDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" @class(['transition-opacity', 'opacity-0 group-hover:opacity-60' => $sortBy !== null && $sortBy !== 'created_at']) />
                                </button>
                            </th>

                            <th scope="col" class="px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('base-tenant::activity.user') }}
                            </th>

                            <th scope="col" class="w-36 px-4 py-2.5">
                                <button type="button" wire:click="sort('action')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::activity.action') }}
                                    <flux:icon :icon="$sortBy === 'action' && $sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" @class(['transition-opacity', 'opacity-0 group-hover:opacity-60' => $sortBy !== 'action']) />
                                </button>
                            </th>

                            <th scope="col" class="w-48 px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('base-tenant::activity.subject') }}
                            </th>

                            <th scope="col" class="px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('base-tenant::activity.description') }}
                            </th>
                        </tr>
                    </thead>

                    {{-- Mientras la tabla se rehace, tres filas de esqueleto en
                         lugar de un parpadeo: el `delay` evita que asome en las
                         respuestas rápidas, donde molesta más de lo que informa.
                         Los cuatro filtros del desplegable van en el objetivo
                         porque cualquiera de ellos rehace el listado entero. --}}
                    <tbody wire:loading.delay wire:target="search,filterAction,filterUser,filterFrom,filterUntil,perPage,sort,resetFilters,gotoPage,previousPage,nextPage" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach(range(1, 3) as $fila)
                            <tr>
                                {{-- La fecha va en dos alturas, lo relativo y lo
                                     exacto: dos barras, no una. --}}
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="space-y-1.5">
                                        <flux:skeleton class="h-3 w-24" />
                                        <flux:skeleton class="h-2.5 w-32" />
                                    </div>
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="flex items-center gap-3">
                                        <flux:skeleton class="size-6 rounded-full" />
                                        <div class="w-full space-y-1.5">
                                            <flux:skeleton class="h-3 w-32" />
                                            <flux:skeleton class="h-2.5 w-48" />
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="h-3 w-20" /></td>
                                <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="h-3 w-24" /></td>
                                <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="h-3 w-56" /></td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tbody wire:loading.remove.delay wire:target="search,filterAction,filterUser,filterFrom,filterUntil,perPage,sort,resetFilters,gotoPage,previousPage,nextPage" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($activities as $activity)
                            @php
                                $actionDot = match ($activity->action) {
                                    'created' => 'bg-success-500',
                                    'deleted' => 'bg-danger-500',
                                    'updated' => 'bg-info-500',
                                    default => 'bg-zinc-300 dark:bg-zinc-600',
                                };
                            @endphp

                            <tr wire:key="{{ $activity->id }}" class="group border-l-2 border-transparent transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                {{-- La fecha, en dos alturas: lo relativo se lee de
                                     un vistazo y lo exacto está debajo, sin
                                     esconderse en un tooltip. --}}
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="whitespace-nowrap text-sm text-zinc-900 dark:text-white">
                                        {{ $activity->created_at?->diffForHumans() }}
                                    </div>

                                    <div class="whitespace-nowrap text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                                        {{ $activity->created_at?->isoFormat('D MMM YYYY, HH:mm') }}
                                    </div>
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    @if($activity->causer)
                                        <div class="flex items-center gap-3">
                                            <flux:avatar size="xs" :name="$activity->causer->name" />

                                            <div class="min-w-0">
                                                <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $activity->causer->name }}</div>

                                                @if($activity->causer->email)
                                                    <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $activity->causer->email }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex items-center gap-3">
                                            <span class="flex size-6 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">
                                                <flux:icon.cpu-chip variant="micro" />
                                            </span>

                                            <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('base-tenant::activity.system') }}</span>
                                        </div>
                                    @endif
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    <span class="inline-flex items-center gap-1.5 text-sm text-zinc-600 dark:text-zinc-300" data-action="{{ $activity->action }}">
                                        <span class="size-1.5 rounded-full {{ $actionDot }}"></span>
                                        {{ ucfirst($activity->action) }}
                                    </span>
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    @if($activity->subject_type)
                                        {{-- El identificador va en el title y no en pantalla: un UUID
                                             truncado no identifica nada, y la descripción de la fila ya
                                             dice sobre quién se actuó. --}}
                                        <div class="truncate text-sm text-zinc-900 dark:text-white" title="{{ $activity->subject_id }}">
                                            {{ class_basename($activity->subject_type) }}
                                        </div>
                                    @else
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500">&mdash;</span>
                                    @endif
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    @if($activity->description)
                                        <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ $activity->description }}</span>
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
                        'from' => $activities->firstItem(),
                        'to' => $activities->lastItem(),
                        'total' => $activities->total(),
                    ]) }}
                </p>

                <div>{{ $activities->links() }}</div>
            </div>
        @endif
    </div>
</div>
