<div class="space-y-5">
    @if($heading ?? true)
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2.5">
                    <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                        {{ __('base-tenant::invitations.title') }}
                    </h1>

                    {{-- El recuento cuenta las invitaciones de la cuenta en
                         cualquier estado, que es justo lo que lista la tabla. --}}
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium tabular-nums text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        {{ $summary['total'] }}
                    </span>
                </div>

                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::invitations.management_description') }}
                </p>
            </div>
        </div>
    @endif

    {{-- Una invitación caducada no la ve nadie: ni llegó a usarse ni avisa de
         nada. Decirlo arriba es lo que convierte la lista en algo accionable. --}}
    @if($summary['expired'] > 0)
        <div class="flex items-center gap-2 rounded-lg border border-warning-200 bg-warning-50 px-3 py-2 text-sm text-warning-800 dark:border-warning-900 dark:bg-warning-950/40 dark:text-warning-200">
            <flux:icon.exclamation-triangle variant="micro" />
            {{ trans_choice('base-tenant::invitations.expired_summary', $summary['expired'], ['count' => $summary['expired']]) }}
        </div>
    @endif

    {{-- El formulario se queda encima de la tabla, no en un modal: invitar es
         lo que se viene a hacer aquí, y esconderlo tras un botón añade un paso
         a la acción principal de la pantalla. --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('base-tenant::invitations.send_invite') }}</h2>

        <form wire:submit="sendInvite" class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-start">
            <div class="flex-1">
                <flux:input
                    wire:model="email"
                    type="email"
                    :placeholder="__('base-tenant::invitations.email_placeholder')"
                    :label:sr-only="__('base-tenant::invitations.col_email')"
                />
            </div>

            <div class="sm:w-56">
                <flux:select wire:model="selectedRole" :label:sr-only="__('base-tenant::invitations.col_role')">
                    <flux:select.option value="">{{ __('base-tenant::invitations.select_role') }}</flux:select.option>

                    @foreach($roles as $role)
                        <flux:select.option value="{{ $role->id }}">{{ $role->label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:button type="submit" variant="primary" icon="paper-airplane">
                {{ __('base-tenant::invitations.send_invite') }}
            </flux:button>
        </form>
    </div>

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
                    :placeholder="__('base-tenant::invitations.search_placeholder')"
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
                    <flux:menu.heading>{{ __('base-tenant::invitations.filter_status') }}</flux:menu.heading>

                    <div class="px-2 py-1.5">
                        <flux:select wire:model.live="filterStatus" size="sm">
                            <flux:select.option value="">{{ __('base-tenant::invitations.all_statuses') }}</flux:select.option>
                            <flux:select.option value="pending">{{ __('base-tenant::invitations.status_pending') }}</flux:select.option>
                            <flux:select.option value="expired">{{ __('base-tenant::invitations.status_expired') }}</flux:select.option>
                            <flux:select.option value="accepted">{{ __('base-tenant::invitations.status_accepted') }}</flux:select.option>
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
                $statusLabels = [
                    'pending' => __('base-tenant::invitations.status_pending'),
                    'expired' => __('base-tenant::invitations.status_expired'),
                    'accepted' => __('base-tenant::invitations.status_accepted'),
                ];
            @endphp

            <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
                @if($search !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::common.search') }}: {{ $search }}
                        <flux:badge.close wire:click="$set('search', '')" />
                    </flux:badge>
                @endif

                @if($filterStatus !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::invitations.filter_status') }}: {{ $statusLabels[$filterStatus] ?? $filterStatus }}
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
                    <flux:icon :icon="$hasActiveFilters ? 'magnifying-glass' : 'envelope'" variant="outline" class="size-5" />
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
                        {{ __('base-tenant::invitations.empty_description') }}
                    </p>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="sticky top-0 z-10 bg-zinc-50/95 backdrop-blur dark:bg-zinc-900/95">
                        <tr class="border-b border-zinc-200 dark:border-zinc-800">
                            <th scope="col" class="px-4 py-2.5">
                                <button type="button" wire:click="sort('email')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::invitations.col_email') }}
                                    <flux:icon :icon="$sortBy === 'email' && $sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" @class(['transition-opacity', 'opacity-0 group-hover:opacity-60' => $sortBy !== 'email']) />
                                </button>
                            </th>

                            <th scope="col" class="px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('base-tenant::invitations.col_role') }}
                            </th>

                            <th scope="col" class="w-36 px-4 py-2.5 text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                {{ __('base-tenant::invitations.col_status') }}
                            </th>

                            <th scope="col" class="w-36 whitespace-nowrap px-4 py-2.5">
                                <button type="button" wire:click="sort('created_at')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::invitations.col_sent') }}
                                    <flux:icon :icon="$sortBy === 'created_at' && $sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" @class(['transition-opacity', 'opacity-0 group-hover:opacity-60' => $sortBy !== 'created_at']) />
                                </button>
                            </th>

                            <th scope="col" class="w-40 whitespace-nowrap px-4 py-2.5">
                                <button type="button" wire:click="sort('expires_at')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('base-tenant::invitations.col_expires') }}
                                    <flux:icon :icon="$sortBy === 'expires_at' && $sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" @class(['transition-opacity', 'opacity-0 group-hover:opacity-60' => $sortBy !== 'expires_at']) />
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
                    <tbody wire:loading.delay wire:target="search,filterStatus,perPage,sort,resetFilters,gotoPage,previousPage,nextPage" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach(range(1, 3) as $fila)
                            <tr>
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="flex items-center gap-3">
                                        <flux:skeleton class="size-6 rounded-full" />
                                        <div class="w-full space-y-1.5">
                                            <flux:skeleton class="h-3 w-48" />
                                            <flux:skeleton class="h-2.5 w-32" />
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="h-4 w-20 rounded-full" /></td>
                                <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="h-3 w-20" /></td>
                                <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="h-3 w-24" /></td>

                                {{-- La caducidad va en dos alturas, la fecha y el
                                     «dentro de tanto»: dos barras, no una. --}}
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

                    <tbody wire:loading.remove.delay wire:target="search,filterStatus,perPage,sort,resetFilters,gotoPage,previousPage,nextPage" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($invites as $invite)
                            @php
                                // Aceptada gana sobre caducada: una invitación que se
                                // usó hace meses también tiene la fecha pasada, y
                                // anunciarla como caducada sería falso.
                                if ($invite->isAccepted()) {
                                    $status = 'accepted';
                                    $statusDot = 'bg-success-500';
                                } elseif ($invite->isExpired()) {
                                    $status = 'expired';
                                    $statusDot = 'bg-danger-500';
                                } elseif ($invite->expires_at?->diffInHours(now(), absolute: true) < 48) {
                                    $status = 'expiring';
                                    $statusDot = 'bg-warning-500';
                                } else {
                                    $status = 'pending';
                                    $statusDot = 'bg-zinc-300 dark:bg-zinc-600';
                                }

                                // Claves literales, no `'...status_'.$status`: una
                                // clave construida no se puede comprobar leyendo el
                                // código, y `TranslationKeysTest` deja de cubrirla.
                                $statusLabel = [
                                    'accepted' => __('base-tenant::invitations.status_accepted'),
                                    'expired' => __('base-tenant::invitations.status_expired'),
                                    'expiring' => __('base-tenant::invitations.status_expiring'),
                                    'pending' => __('base-tenant::invitations.status_pending'),
                                ][$status];
                            @endphp

                            <tr wire:key="{{ $invite->id }}" class="group border-l-2 border-transparent transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                {{-- Celda primaria compuesta: a quién se invitó y
                                     quién lo hizo, que es la pregunta que sigue
                                     siempre a la primera. --}}
                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="flex items-center gap-3">
                                        <flux:avatar size="xs" :name="$invite->email" />

                                        <div class="min-w-0">
                                            <div class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $invite->email }}</div>

                                            <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                                                @if($invite->invitedBy)
                                                    {{ __('base-tenant::invitations.invited_by_name', ['name' => $invite->invitedBy->name]) }}
                                                @else
                                                    {{ __('base-tenant::invitations.invited_by_unknown') }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    @if($invite->role)
                                        <flux:badge size="sm" color="zinc">{{ $invite->role->label }}</flux:badge>
                                    @else
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500">&mdash;</span>
                                    @endif
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    {{--
                                        `data-invite-status` da un asidero estable a
                                        los tests: las etiquetas no sirven porque el
                                        desplegable de filtros pinta las mismas
                                        palabras en la misma página.
                                    --}}
                                    <span class="inline-flex items-center gap-1.5 text-sm text-zinc-600 dark:text-zinc-300" data-invite-status="{{ $status }}">
                                        <span class="size-1.5 rounded-full {{ $statusDot }}"></span>
                                        {{ $statusLabel }}
                                    </span>
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    <span class="whitespace-nowrap text-sm tabular-nums text-zinc-500 dark:text-zinc-400">
                                        {{ $invite->created_at?->isoFormat('D MMM YYYY') }}
                                    </span>
                                </td>

                                <td class="px-4 {{ $rowPadding }}">
                                    <div class="whitespace-nowrap text-sm tabular-nums text-zinc-500 dark:text-zinc-400">
                                        {{ $invite->expires_at?->isoFormat('D MMM YYYY') }}
                                    </div>

                                    <div class="whitespace-nowrap text-xs text-zinc-400 dark:text-zinc-500">
                                        {{ $invite->expires_at?->diffForHumans() }}
                                    </div>
                                </td>

                                <td class="px-4 {{ $rowPadding }} text-right">
                                    <div class="flex items-center justify-end gap-0.5">
                                        {{-- La acción principal se revela al pasar el ratón;
                                             el resto sigue en el menú. --}}
                                        <flux:button
                                            wire:click="resendInvite('{{ $invite->id }}')"
                                            variant="ghost"
                                            size="sm"
                                            icon="arrow-path"
                                            class="opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100"
                                            :aria-label="__('base-tenant::invitations.resend')"
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
                                                    wire:click="resendInvite('{{ $invite->id }}')"
                                                    icon="arrow-path"
                                                >
                                                    {{ __('base-tenant::invitations.resend') }}
                                                </flux:menu.item>

                                                <flux:menu.separator />

                                                <flux:menu.item
                                                    wire:click="revokeInvite('{{ $invite->id }}')"
                                                    variant="danger"
                                                    icon="trash"
                                                >
                                                    {{ __('base-tenant::invitations.revoke') }}
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
                        'from' => $invites->firstItem(),
                        'to' => $invites->lastItem(),
                        'total' => $invites->total(),
                    ]) }}
                </p>

                <div>{{ $invites->links() }}</div>
            </div>
        @endif
    </div>
</div>
