<div class="space-y-5">
    @if($heading ?? true)
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                        {{ __('base-tenant::features.title') }}
                    </h1>

                    {{-- El recuento es el catálogo entero de la cuenta, no lo
                         que dejan ver los filtros. --}}
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium tabular-nums text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        {{ $summary['total'] }}
                    </span>

                    {{-- El plan es la línea base de todo lo que hay debajo, así
                         que se lee junto al título y no perdido en la barra. --}}
                    @if($planName)
                        <flux:badge size="sm" color="blue">
                            {{ __('base-tenant::features.current_plan') }}: {{ $planName }}
                        </flux:badge>
                    @endif
                </div>

                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::features.description') }}
                </p>
            </div>

            @if($accounts->isNotEmpty())
                <div class="w-full sm:w-64">
                    <flux:select wire:change="selectAccount($event.target.value)" size="sm" :label:sr-only="__('base-tenant::features.account')">
                        @foreach($accounts as $option)
                            <flux:select.option value="{{ $option->id }}" :selected="$option->id === $accountId">{{ $option->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif
        </div>
    @endif

    @if(! $account)
        <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col items-center px-6 py-16 text-center">
                <div class="mb-4 flex size-11 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">
                    <flux:icon icon="building-office-2" variant="outline" class="size-5" />
                </div>

                <p class="text-sm font-semibold text-zinc-900 dark:text-white">
                    {{ __('base-tenant::features.no_account') }}
                </p>
            </div>
        </div>
    @else
        {{-- Una sobrescritura con fecha se apaga sola: llegado el día la cuenta
             vuelve a lo que diga el plan sin que nadie toque nada, y eso no se
             ve recorriendo la tabla. --}}
        @if($summary['expiring'] > 0)
            <div class="flex items-center gap-2 rounded-lg border border-warning-200 bg-warning-50 px-3 py-2 text-sm text-warning-800 dark:border-warning-900 dark:bg-warning-950/40 dark:text-warning-200">
                <flux:icon.exclamation-triangle variant="micro" />
                {{ trans_choice('base-tenant::features.expiring_summary', $summary['expiring'], ['count' => $summary['expiring']]) }}
            </div>
        @endif

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
                        :placeholder="__('base-tenant::features.search_placeholder')"
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
                        <flux:menu.heading>{{ __('base-tenant::features.scope') }}</flux:menu.heading>

                        <div class="px-2 py-1.5">
                            <flux:select wire:model.live="filterScope" size="sm">
                                <flux:select.option value="">{{ __('base-tenant::features.all_scopes') }}</flux:select.option>
                                <flux:select.option value="global">{{ __('base-tenant::features.scope_global') }}</flux:select.option>
                                <flux:select.option value="account">{{ __('base-tenant::features.scope_account') }}</flux:select.option>
                            </flux:select>
                        </div>
                    </flux:menu>
                </flux:dropdown>

                <div class="h-5 w-px bg-zinc-200 dark:bg-zinc-700"></div>

                {{-- Densidad: el mismo conmutador y en el mismo sitio que en el
                     resto de pantallas. Lo que no hay aquí es tamaño de página:
                     el catálogo lo define el plan, no crece con el uso y esta
                     pantalla no pagina. Un selector que no manda nada engaña. --}}
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
                        'global' => __('base-tenant::features.scope_global'),
                        'account' => __('base-tenant::features.scope_account'),
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
                            {{ __('base-tenant::features.scope') }}: {{ $scopeLabels[$filterScope] ?? $filterScope }}
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
                        <flux:icon :icon="$hasActiveFilters ? 'magnifying-glass' : 'squares-2x2'" variant="outline" class="size-5" />
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
                            {{ __('base-tenant::features.empty') }}
                        </p>
                        <p class="mt-1 max-w-sm text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('base-tenant::features.empty_description') }}
                        </p>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="sticky top-0 z-10 bg-zinc-50/95 backdrop-blur dark:bg-zinc-900/95">
                            <tr class="border-b border-zinc-200 dark:border-zinc-800">
                                <th scope="col" class="px-4 py-2.5">
                                    <button type="button" wire:click="sort('key')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                        {{ __('base-tenant::features.feature') }}
                                        <flux:icon :icon="$sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" class="transition-opacity" />
                                    </button>
                                </th>

                                <th scope="col" class="w-48 px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                    {{ __('base-tenant::features.scope') }}
                                </th>

                                <th scope="col" class="w-44 px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                    {{ __('base-tenant::features.value') }}
                                </th>

                                @if($showsExpiry)
                                    <th scope="col" class="w-40 px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                        {{ __('base-tenant::features.expires_at') }}
                                    </th>
                                @endif

                                <th scope="col" class="w-16 px-4 py-2.5">
                                    <span class="sr-only">{{ __('base-tenant::common.actions') }}</span>
                                </th>
                            </tr>
                        </thead>

                        {{-- Mientras la tabla se rehace, tres filas de esqueleto
                             en lugar de un parpadeo: el `delay` evita que asome
                             en las respuestas rápidas, donde molesta más de lo
                             que informa. Aquí el objetivo no lleva paginación
                             porque esta pantalla no pagina; lo que sí lleva es
                             `selectAccount`, que cambia el catálogo entero. --}}
                        <tbody wire:loading.delay wire:target="search,filterScope,sort,resetFilters,selectAccount" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach(range(1, 3) as $fila)
                                <tr>
                                    <td class="px-4 {{ $rowPadding }}">
                                        <div class="space-y-1.5">
                                            <flux:skeleton class="h-3 w-40" />
                                            <flux:skeleton class="h-2.5 w-28" />
                                        </div>
                                    </td>

                                    <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="h-4 w-24 rounded-full" /></td>
                                    <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="h-3 w-20" /></td>

                                    @if($showsExpiry)
                                        <td class="px-4 {{ $rowPadding }}">
                                            <div class="space-y-1.5">
                                                <flux:skeleton class="h-3 w-24" />
                                                <flux:skeleton class="h-2.5 w-16" />
                                            </div>
                                        </td>
                                    @endif

                                    <td class="px-4 {{ $rowPadding }}"></td>
                                </tr>
                            @endforeach
                        </tbody>

                        <tbody wire:loading.remove.delay wire:target="search,filterScope,sort,resetFilters,selectAccount" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($features as $feature)
                                @php
                                    // Lo que dice el plan, escrito como se lee:
                                    // un booleano en palabras, el ilimitado en
                                    // símbolo y el hueco en raya.
                                    $planValue = match (true) {
                                        is_bool($feature['plan_value']) => $feature['plan_value']
                                            ? __('base-tenant::features.enabled')
                                            : __('base-tenant::features.disabled'),
                                        $feature['plan_value'] === null => '—',
                                        (int) $feature['plan_value'] === -1 => '∞',
                                        default => (string) $feature['plan_value'],
                                    };
                                @endphp

                                <tr wire:key="feature-{{ $feature['key'] }}" class="group border-l-2 border-transparent transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    {{-- Celda primaria compuesta: el nombre legible
                                         como dato y la clave real debajo, que es
                                         lo que se busca y lo que se configura. --}}
                                    <td class="px-4 {{ $rowPadding }}">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ $feature['name'] }}</div>
                                        <div class="font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $feature['key'] }}</div>
                                    </td>

                                    <td class="px-4 {{ $rowPadding }}">
                                        <div class="flex flex-wrap items-center gap-1">
                                            @if($feature['scope'] === 'global')
                                                <flux:badge size="sm" color="blue" data-scope="global">
                                                    {{ __('base-tenant::features.scope_global') }}
                                                </flux:badge>
                                            @else
                                                <flux:badge size="sm" color="amber" data-scope="account">
                                                    {{ __('base-tenant::features.scope_account') }}
                                                </flux:badge>
                                            @endif

                                            @if(! $feature['in_plan'])
                                                <flux:badge size="sm" color="zinc">{{ __('base-tenant::features.not_in_plan') }}</flux:badge>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-4 {{ $rowPadding }}">
                                        @if($feature['is_boolean'])
                                            <span class="inline-flex items-center gap-1.5 text-sm text-zinc-600 dark:text-zinc-300" data-feature-state="{{ $feature['effective'] ? 'enabled' : 'disabled' }}">
                                                <span @class(['size-1.5 rounded-full', 'bg-success-500' => (bool) $feature['effective'], 'bg-zinc-300 dark:bg-zinc-600' => ! $feature['effective']])></span>
                                                {{ $feature['effective'] ? __('base-tenant::features.enabled') : __('base-tenant::features.disabled') }}
                                            </span>
                                        @else
                                            <span class="text-sm font-medium tabular-nums text-zinc-900 dark:text-white">
                                                {{ (int) $feature['effective'] === -1 ? '∞' : $feature['effective'] }}
                                            </span>
                                        @endif

                                        {{-- Con una sobrescritura puesta, lo que
                                             dice el plan es la mitad de la
                                             historia y va debajo como metadato. --}}
                                        @if($feature['overridden'] && $feature['in_plan'])
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ __('base-tenant::features.plan_says') }}: {{ $planValue }}
                                            </div>
                                        @endif
                                    </td>

                                    @if($showsExpiry)
                                        <td class="px-4 {{ $rowPadding }}">
                                            @if($feature['expires_at'])
                                                <div class="whitespace-nowrap text-sm tabular-nums text-zinc-900 dark:text-white">
                                                    {{ $feature['expires_at']->isoFormat('D MMM YYYY') }}
                                                </div>
                                                <div class="whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ $feature['expires_at']->diffForHumans() }}
                                                </div>
                                            @elseif($feature['overridden'])
                                                <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('base-tenant::features.no_expiry') }}</span>
                                            @else
                                                <span class="text-xs text-zinc-400 dark:text-zinc-500">&mdash;</span>
                                            @endif
                                        </td>
                                    @endif

                                    <td class="px-4 {{ $rowPadding }} text-right">
                                        @if($canEdit)
                                            <div class="flex items-center justify-end gap-0.5">
                                                {{-- La acción principal se revela al pasar el ratón;
                                                     el resto sigue en el menú. --}}
                                                <flux:button
                                                    wire:click="edit('{{ $feature['key'] }}')"
                                                    variant="ghost"
                                                    size="sm"
                                                    icon="pencil-square"
                                                    class="opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
                                                    :aria-label="__('base-tenant::common.edit')"
                                                />

                                                <flux:dropdown position="bottom" align="end">
                                                    <flux:button
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="ellipsis-horizontal"
                                                        :aria-label="__('base-tenant::common.actions')"
                                                    />

                                                    <flux:menu>
                                                        @if($feature['is_boolean'])
                                                            <flux:menu.item wire:click="toggle('{{ $feature['key'] }}')" icon="arrow-path">
                                                                {{ __('base-tenant::features.toggle') }}
                                                            </flux:menu.item>
                                                        @endif

                                                        <flux:menu.item wire:click="edit('{{ $feature['key'] }}')" icon="pencil-square">
                                                            {{ __('base-tenant::common.edit') }}
                                                        </flux:menu.item>

                                                        @if($feature['overridden'])
                                                            <flux:menu.separator />

                                                            <flux:menu.item wire:click="resetToPlan('{{ $feature['key'] }}')" variant="danger" icon="arrow-uturn-left">
                                                                {{ __('base-tenant::features.reset') }}
                                                            </flux:menu.item>
                                                        @endif
                                                    </flux:menu>
                                                </flux:dropdown>
                                            </div>
                                        @else
                                            <span class="text-xs text-zinc-400 dark:text-zinc-500">&mdash;</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($editing !== '')
                    <form wire:submit="save" class="flex flex-wrap items-end gap-3 border-t border-zinc-200 bg-zinc-50 px-4 py-4 dark:border-zinc-800 dark:bg-zinc-800/50">
                        <div>
                            <flux:input
                                wire:model="value"
                                class="w-40"
                                size="sm"
                                :label="__('base-tenant::features.value')"
                                :description="__('base-tenant::features.value_hint')"
                            />
                        </div>

                        <div>
                            <flux:input
                                wire:model="expiresAt"
                                type="date"
                                size="sm"
                                :label="__('base-tenant::features.expires_at')"
                                :description="__('base-tenant::features.expires_hint')"
                            />
                        </div>

                        <div class="flex items-center gap-2 pb-1">
                            <flux:button type="submit" variant="primary" size="sm">{{ __('base-tenant::common.save') }}</flux:button>
                            <flux:button wire:click="cancel" variant="ghost" size="sm">{{ __('base-tenant::common.cancel') }}</flux:button>
                        </div>
                    </form>
                @endif

                {{-- El pie no lleva paginación porque no hay páginas: el
                     catálogo lo define el plan y cabe entero. Lo que sí lleva
                     es el recuento, en el mismo sitio que en el resto. --}}
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 bg-zinc-50/60 px-4 py-2.5 dark:border-zinc-800 dark:bg-zinc-900/40">
                    <p class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                        {{ trans_choice('base-tenant::features.count_summary', $features->count(), ['count' => $features->count()]) }}
                    </p>

                    <p class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                        {{ trans_choice('base-tenant::features.overridden_summary', $summary['overridden'], ['count' => $summary['overridden']]) }}
                    </p>
                </div>
            @endif
        </div>
    @endif
</div>
