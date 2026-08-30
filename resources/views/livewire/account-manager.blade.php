<div class="space-y-5">
    @if($heading ?? true)
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2.5">
                    <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                        {{ __('base-tenant::accounts.management_title') }}
                    </h1>

                    {{-- El recuento en la cabecera: cuántas cuentas alcanza
                         quien mira, no cuántas caben en esta página. --}}
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium tabular-nums text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        {{ $summary['total'] }}
                    </span>
                </div>

                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::accounts.management_description') }}
                </p>
            </div>

            @if($isSystemAdmin)
                <flux:button :href="route('base-tenant.accounts.create')" variant="primary" icon="plus">
                    {{ __('base-tenant::accounts.add_new') }}
                </flux:button>
            @endif
        </div>
    @endif

    {{-- Una cuenta inactiva conserva sus datos pero nadie puede trabajar
         dentro: no se ve en el listado más que por una insignia pequeña, así
         que merece decirlo arriba. --}}
    @if($summary['inactive'] > 0)
        <div class="flex items-center gap-2 rounded-lg border border-warning-200 bg-warning-50 px-3 py-2 text-sm text-warning-800 dark:border-warning-900 dark:bg-warning-950/40 dark:text-warning-200">
            <flux:icon.exclamation-triangle variant="micro" />
            {{ trans_choice('base-tenant::accounts.inactive_summary', $summary['inactive'], ['count' => $summary['inactive']]) }}
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
                    :placeholder="__('base-tenant::accounts.search_placeholder')"
                    :label:sr-only="__('base-tenant::common.search')"
                />
            </div>

            <div class="h-5 w-px bg-zinc-200 dark:bg-zinc-700"></div>

            <flux:dropdown>
                <flux:button size="sm" icon="funnel" icon:trailing="chevron-down">
                    {{ __('base-tenant::common.filters') }}

                    @if($filterSubscription !== '')
                        <flux:badge size="sm" color="zinc" inset="top bottom">1</flux:badge>
                    @endif
                </flux:button>

                <flux:menu class="min-w-64">
                    <flux:menu.heading>{{ __('base-tenant::accounts.filter_subscription') }}</flux:menu.heading>

                    <div class="px-2 py-1.5">
                        <flux:select wire:model.live="filterSubscription" size="sm">
                            <flux:select.option value="">{{ __('base-tenant::accounts.all_subscriptions') }}</flux:select.option>
                            <flux:select.option value="active">{{ __('base-tenant::accounts.subscription_active') }}</flux:select.option>
                            <flux:select.option value="trialing">{{ __('base-tenant::accounts.subscription_trialling') }}</flux:select.option>
                            <flux:select.option value="none">{{ __('base-tenant::accounts.subscription_none') }}</flux:select.option>
                        </flux:select>
                    </div>
                </flux:menu>
            </flux:dropdown>

            <div class="h-5 w-px bg-zinc-200 dark:bg-zinc-700"></div>

            {{-- Densidad: quien revisa la lista a diario la quiere compacta. --}}
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
            @php
                $subscriptionLabels = [
                    'active' => __('base-tenant::accounts.subscription_active'),
                    'trialing' => __('base-tenant::accounts.subscription_trialling'),
                    'none' => __('base-tenant::accounts.subscription_none'),
                ];
            @endphp

            <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
                @if($search !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::common.search') }}: {{ $search }}
                        <flux:badge.close wire:click="$set('search', '')" />
                    </flux:badge>
                @endif

                @if($filterSubscription !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::accounts.filter_subscription') }}: {{ $subscriptionLabels[$filterSubscription] ?? $filterSubscription }}
                        <flux:badge.close wire:click="$set('filterSubscription', '')" />
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
                    <flux:icon :icon="$hasActiveFilters ? 'magnifying-glass' : 'building-office-2'" variant="outline" class="size-5" />
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
                        {{ __('base-tenant::common.empty_title') }}
                    </p>
                    <p class="mt-1 max-w-sm text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('base-tenant::accounts.empty_description') }}
                    </p>

                    @if($isSystemAdmin)
                        <flux:button :href="route('base-tenant.accounts.create')" variant="primary" size="sm" icon="plus" class="mt-4">
                            {{ __('base-tenant::accounts.add_new') }}
                        </flux:button>
                    @endif
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="sticky top-0 z-10 bg-zinc-50/95 backdrop-blur dark:bg-zinc-900/95">
                        <tr class="border-b border-zinc-200 dark:border-zinc-800">
                            <th scope="col" class="px-4 py-2.5">
                                <button type="button" wire:click="sort('name')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::accounts.name') }}
                                    <flux:icon :icon="$sortBy === 'name' && $sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" @class(['transition-opacity', 'opacity-0 group-hover:opacity-60' => $sortBy !== 'name']) />
                                </button>
                            </th>

                            <th scope="col" class="px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('base-tenant::accounts.owner_short') }}
                            </th>

                            <th scope="col" class="w-24 px-4 py-2.5">
                                <button type="button" wire:click="sort('users_count')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::accounts.users_count') }}
                                    <flux:icon :icon="$sortBy === 'users_count' && $sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" @class(['transition-opacity', 'opacity-0 group-hover:opacity-60' => $sortBy !== 'users_count']) />
                                </button>
                            </th>

                            <th scope="col" class="w-40 px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('base-tenant::accounts.status') }}
                            </th>

                            <th scope="col" class="w-36 whitespace-nowrap px-4 py-2.5">
                                <button type="button" wire:click="sort('created_at')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::accounts.created') }}
                                    <flux:icon :icon="$sortBy === 'created_at' && $sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" @class(['transition-opacity', 'opacity-0 group-hover:opacity-60' => $sortBy !== 'created_at']) />
                                </button>
                            </th>

                            <th scope="col" class="w-16 px-4 py-2.5">
                                <span class="sr-only">{{ __('base-tenant::common.actions') }}</span>
                            </th>
                        </tr>
                    </thead>

                    {{-- Mientras la tabla se rehace, tres filas de esqueleto en
                         lugar de un parpadeo: el `delay` evita que asome en las
                         respuestas rápidas, donde molesta más de lo que informa. --}}
                    <tbody wire:loading.delay wire:target="search,filterSubscription,perPage,sort,resetFilters,gotoPage,previousPage,nextPage" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach(range(1, 3) as $fila)
                            <tr>
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="flex items-center gap-3">
                                        <flux:skeleton class="size-6 rounded-full" />
                                        <div class="w-full space-y-1.5">
                                            <flux:skeleton class="h-3 w-40" />
                                            <flux:skeleton class="h-2.5 w-56" />
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="h-3 w-28" /></td>
                                <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="h-3 w-6" /></td>
                                <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="h-3 w-24" /></td>
                                <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="h-3 w-24" /></td>
                                <td class="px-4 {{ $rowPadding }}"></td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tbody wire:loading.remove.delay wire:target="search,filterSubscription,perPage,sort,resetFilters,gotoPage,previousPage,nextPage" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($accounts as $account)
                            @php
                                // `subscriptions` viene precargada, así que esto no
                                // consulta. Se mira la prueba antes que el estado
                                // activo porque el scope `active()` de Cashier
                                // también recoge las suscripciones en prueba.
                                $onTrial = $account->subscriptions->contains(fn ($subscription) => $subscription->onTrial());
                                $isActive = ! $onTrial && $account->subscriptions->contains(fn ($subscription) => $subscription->active());
                            @endphp

                            <tr wire:key="{{ $account->id }}" class="group border-l-2 border-transparent transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                {{-- Celda primaria compuesta: la cuenta entera en
                                     una columna, con el correo de contacto como
                                     metadato. --}}
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="flex items-center gap-3">
                                        <flux:avatar size="xs" :name="$account->name" />

                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $account->name }}</span>

                                                @if(! $account->active)
                                                    <flux:badge size="sm" color="zinc" inset="top bottom">{{ __('base-tenant::accounts.inactive') }}</flux:badge>
                                                @endif
                                            </div>

                                            @if($account->email)
                                                <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $account->email }}</div>
                                            @else
                                                <div class="truncate text-xs text-zinc-400 dark:text-zinc-500">{{ __('base-tenant::accounts.no_email') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    @if($account->owner)
                                        <span class="truncate text-sm text-zinc-600 dark:text-zinc-300">{{ $account->owner->name }}</span>
                                    @else
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('base-tenant::accounts.no_owner') }}</span>
                                    @endif
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    <span class="text-sm tabular-nums text-zinc-600 dark:text-zinc-300">{{ $account->users_count }}</span>
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    {{--
                                        `data-subscription` da un asidero estable a
                                        los tests: las etiquetas no sirven porque el
                                        desplegable de filtros ya pinta las tres en
                                        la misma página.
                                    --}}
                                    @if($onTrial)
                                        <span class="inline-flex items-center gap-1.5 text-sm text-zinc-600 dark:text-zinc-300" data-subscription="trialing">
                                            <span class="size-1.5 rounded-full bg-warning-500"></span>
                                            {{ __('base-tenant::accounts.subscription_trialling') }}
                                        </span>
                                    @elseif($isActive)
                                        <span class="inline-flex items-center gap-1.5 text-sm text-zinc-600 dark:text-zinc-300" data-subscription="active">
                                            <span class="size-1.5 rounded-full bg-success-500"></span>
                                            {{ __('base-tenant::accounts.subscription_active') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-sm text-zinc-600 dark:text-zinc-300" data-subscription="none">
                                            <span class="size-1.5 rounded-full bg-zinc-300 dark:bg-zinc-600"></span>
                                            {{ __('base-tenant::accounts.subscription_none') }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    <span class="whitespace-nowrap text-sm tabular-nums text-zinc-500 dark:text-zinc-400">
                                        {{ $account->created_at?->isoFormat('D MMM YYYY') }}
                                    </span>
                                </td>

                                <td class="px-4 {{ $rowPadding }} text-right">
                                    <div class="flex items-center justify-end gap-0.5">
                                        {{-- La acción principal se revela al pasar el ratón;
                                             el resto sigue en el menú. --}}
                                        <flux:button
                                            :href="route('base-tenant.accounts.edit', $account)"
                                            variant="ghost"
                                            size="sm"
                                            icon="pencil-square"
                                            class="opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
                                            :aria-label="__('base-tenant::accounts.edit')"
                                        />

                                        <flux:dropdown position="bottom" align="end">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="ellipsis-horizontal"
                                                :aria-label="__('base-tenant::common.actions')"
                                            />

                                            <flux:menu>
                                                <flux:menu.item
                                                    :href="route('base-tenant.accounts.edit', $account)"
                                                    icon="pencil-square"
                                                >
                                                    {{ __('base-tenant::accounts.edit') }}
                                                </flux:menu.item>

                                                <flux:menu.separator />

                                                <flux:menu.item
                                                    wire:click="confirmDelete('{{ $account->id }}')"
                                                    variant="danger"
                                                    icon="trash"
                                                >
                                                    {{ __('base-tenant::accounts.delete') }}
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

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 bg-zinc-50/60 px-4 py-2.5 dark:border-zinc-800 dark:bg-zinc-900/40">
                <p class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::common.showing', [
                        'from' => $accounts->firstItem(),
                        'to' => $accounts->lastItem(),
                        'total' => $accounts->total(),
                    ]) }}
                </p>

                <div>{{ $accounts->links() }}</div>
            </div>
        @endif
    </div>

    <flux:modal name="delete-account-modal" class="min-w-[22rem] space-y-6">
        <div>
            <flux:heading size="lg">{{ __('base-tenant::accounts.delete_account') }}</flux:heading>
            <flux:subheading>
                <p class="mt-4">
                    {{ __('base-tenant::accounts.delete_confirmation') }}
                </p>
            </flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('base-tenant::accounts.cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button wire:click="deleteAccount" variant="danger">{{ __('base-tenant::accounts.delete') }}</flux:button>
        </div>
    </flux:modal>
</div>
