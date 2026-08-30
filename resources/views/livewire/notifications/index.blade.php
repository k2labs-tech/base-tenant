@php
    // Etiquetas de los valores de filtro, para poder nombrarlos en las
    // insignias sin repetir el mapeo en tres sitios.
    $etiquetasDePrioridad = [
        'high' => __('base-tenant::notifications.priority_high'),
        'medium' => __('base-tenant::notifications.priority_medium'),
        'low' => __('base-tenant::notifications.priority_low'),
    ];

    $etiquetasDeEstado = [
        'unread' => __('base-tenant::notifications.status_unread'),
        'read' => __('base-tenant::notifications.status_read'),
    ];

    // Los del desplegable, que son los que cuenta su insignia. La búsqueda no
    // entra: se ve escrita en su propio campo.
    $filtrosDelDesplegable = ($filterPriority !== 'all' ? 1 : 0) + ($filterReadStatus !== 'all' ? 1 : 0);

    // Vacía de verdad, y no que te hayas pasado de página: con `?page=9` sobre
    // una sola notificación la colección viene vacía y la bandeja no lo está.
    $bandejaVacia = $notifications->total() === 0;

    // Todo lo que rehace la lista, en un sitio solo: el esqueleto y la lista
    // real tienen que nombrar exactamente los mismos objetivos, y un nombre que
    // no exista en el componente deja el esqueleto sin aparecer nunca.
    //
    // `selectedNotifications` queda fuera a propósito: marcar una casilla
    // provoca una ida y vuelta, pero no rehace la lista, y meterlo aquí haría
    // parpadear el esqueleto en cada clic.
    $objetivosDeCarga = 'search,filterPriority,filterReadStatus,resetFilters,markAsRead,markAllAsRead,markSelectedAsRead,deleteSelected,gotoPage,previousPage,nextPage';
@endphp

<div class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2.5">
                <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                    {{ __('base-tenant::notifications.management_title') }}
                </h1>

                {{-- El recuento en la cabecera: aquí lo que importa no es cuántas
                     hay, sino cuántas quedan por leer. --}}
                <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium tabular-nums text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    {{ __('base-tenant::notifications.unread_badge', ['count' => $unreadCount]) }}
                </span>
            </div>

            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('base-tenant::notifications.management_description') }}
            </p>
        </div>

        {{-- La acción primaria de la pantalla vive en la cabecera, no escondida
             en la barra de selección: vaciar la bandeja no exige seleccionar
             nada antes. --}}
        @if($unreadCount > 0)
            <flux:button wire:click="markAllAsRead" variant="primary" icon="check">
                {{ __('base-tenant::notifications.mark_all_as_read') }}
            </flux:button>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        {{-- Barra de herramientas: buscar | filtrar. La misma gramática y el
             mismo orden que las pantallas tabulares. --}}
        <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 bg-zinc-50/60 px-3 py-2.5 dark:border-zinc-800 dark:bg-zinc-900/40">
            <div class="min-w-56 flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    clearable
                    size="sm"
                    type="search"
                    :placeholder="__('base-tenant::notifications.search_placeholder')"
                    :label:sr-only="__('base-tenant::common.search')"
                />
            </div>

            <div class="h-5 w-px bg-zinc-200 dark:bg-zinc-700"></div>

            <flux:dropdown>
                <flux:button size="sm" icon="funnel" icon:trailing="chevron-down">
                    {{ __('base-tenant::common.filters') }}

                    @if($filtrosDelDesplegable > 0)
                        <flux:badge size="sm" color="zinc" inset="top bottom">{{ $filtrosDelDesplegable }}</flux:badge>
                    @endif
                </flux:button>

                <flux:menu class="min-w-64">
                    <flux:menu.heading>{{ __('base-tenant::notifications.filter_priority') }}</flux:menu.heading>

                    <div class="px-2 py-1.5">
                        <flux:select wire:model.live="filterPriority" size="sm">
                            <flux:select.option value="all">{{ __('base-tenant::notifications.all_priorities') }}</flux:select.option>
                            <flux:select.option value="high">{{ __('base-tenant::notifications.priority_high') }}</flux:select.option>
                            <flux:select.option value="medium">{{ __('base-tenant::notifications.priority_medium') }}</flux:select.option>
                            <flux:select.option value="low">{{ __('base-tenant::notifications.priority_low') }}</flux:select.option>
                        </flux:select>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.heading>{{ __('base-tenant::notifications.filter_status') }}</flux:menu.heading>

                    <div class="px-2 py-1.5">
                        <flux:select wire:model.live="filterReadStatus" size="sm">
                            <flux:select.option value="all">{{ __('base-tenant::notifications.all_statuses') }}</flux:select.option>
                            <flux:select.option value="unread">{{ __('base-tenant::notifications.status_unread') }}</flux:select.option>
                            <flux:select.option value="read">{{ __('base-tenant::notifications.status_read') }}</flux:select.option>
                        </flux:select>
                    </div>
                </flux:menu>
            </flux:dropdown>
        </div>

        @if($hasActiveFilters)
            <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
                @if($search !== '')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::common.search') }}: {{ $search }}
                        <flux:badge.close wire:click="$set('search', '')" />
                    </flux:badge>
                @endif

                @if($filterPriority !== 'all')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::notifications.filter_priority') }}: {{ $etiquetasDePrioridad[$filterPriority] ?? $filterPriority }}
                        <flux:badge.close wire:click="$set('filterPriority', 'all')" />
                    </flux:badge>
                @endif

                @if($filterReadStatus !== 'all')
                    <flux:badge size="sm" color="zinc">
                        {{ __('base-tenant::notifications.filter_status') }}: {{ $etiquetasDeEstado[$filterReadStatus] ?? $filterReadStatus }}
                        <flux:badge.close wire:click="$set('filterReadStatus', 'all')" />
                    </flux:badge>
                @endif

                <flux:link href="#" variant="subtle" class="text-xs" wire:click.prevent="resetFilters">
                    {{ __('base-tenant::common.clear_filters') }}
                </flux:link>
            </div>
        @endif

        {{-- Lo que actúa sobre la selección aparece solo cuando hay selección, y
             pegado a la lista sobre la que actúa. --}}
        @if(count($selectedNotifications) > 0)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-accent-200 bg-accent-50 px-3 py-2 dark:border-accent-800 dark:bg-accent-950/40">
                <p class="text-xs tabular-nums text-zinc-600 dark:text-zinc-300">
                    {{ trans_choice('base-tenant::notifications.selected_count', count($selectedNotifications), ['count' => count($selectedNotifications)]) }}
                </p>

                <div class="flex items-center gap-2">
                    <flux:button wire:click="markSelectedAsRead" size="sm" icon="envelope-open">
                        {{ __('base-tenant::notifications.mark_as_read') }}
                    </flux:button>

                    <flux:button wire:click="confirmDeleteSelected" variant="danger" size="sm" icon="trash">
                        {{ __('base-tenant::common.delete') }}
                    </flux:button>
                </div>
            </div>
        @endif

        @if($bandejaVacia)
            <div class="flex flex-col items-center px-6 py-16 text-center">
                <div class="mb-4 flex size-11 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">
                    <flux:icon :icon="$hasActiveFilters ? 'magnifying-glass' : 'bell'" variant="outline" class="size-5" />
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
                        {{ __('base-tenant::notifications.empty_title') }}
                    </p>
                    <p class="mt-1 max-w-sm text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('base-tenant::notifications.empty_description') }}
                    </p>
                @endif
            </div>
        @else
            {{-- Mientras la lista se rehace, tres filas de esqueleto con la
                 forma de una fila real en lugar de un parpadeo; el `delay`
                 evita que asome en las respuestas rápidas. --}}
            <div wire:loading.delay wire:target="{{ $objetivosDeCarga }}" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach(range(1, 3) as $fila)
                    <div data-skeleton-row class="flex items-start gap-3 border-l-2 border-transparent px-4 py-3">
                        <flux:skeleton class="mt-0.5 size-4 rounded" />
                        <flux:skeleton class="mt-1.5 size-2 shrink-0 rounded-full" />

                        <div class="min-w-0 flex-1 space-y-1.5">
                            <flux:skeleton class="h-3 w-48" />
                            <flux:skeleton class="h-2.5 w-full max-w-md" />
                            <flux:skeleton class="h-2.5 w-24" />
                        </div>

                        <flux:skeleton class="size-7 shrink-0 rounded-md" />
                    </div>
                @endforeach
            </div>

            <div wire:loading.remove.delay wire:target="{{ $objetivosDeCarga }}" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach($notifications as $notification)
                    @php
                        $prioridad = $notification->data['priority'] ?? 'low';

                        $colorDePrioridad = match ($prioridad) {
                            'high' => 'bg-danger-500',
                            'medium' => 'bg-warning-500',
                            default => 'bg-info-500',
                        };
                    @endphp

                    {{-- Sin leer: borde izquierdo de acento y título en negrita.
                         Se distingue de un vistazo sin recorrer la fila. --}}
                    <div
                        wire:key="{{ $notification->id }}"
                        data-notification-row
                        class="group flex items-start gap-3 border-l-2 px-4 py-3 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50 {{ $notification->read_at ? 'border-transparent' : 'border-accent-500' }}"
                    >
                        <flux:checkbox
                            wire:model.live="selectedNotifications"
                            value="{{ $notification->id }}"
                            class="mt-0.5"
                            :aria-label="__('base-tenant::notifications.select_one')"
                        />

                        <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $colorDePrioridad }}">
                            <span class="sr-only">{{ $etiquetasDePrioridad[$prioridad] ?? $prioridad }}</span>
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-zinc-900 dark:text-white {{ $notification->read_at ? 'font-normal' : 'font-semibold' }}">
                                {{ $notification->data['title'] ?? __('base-tenant::notifications.untitled') }}
                            </p>

                            @if(($notification->data['message'] ?? '') !== '')
                                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $notification->data['message'] }}
                                </p>
                            @endif

                            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                @if(isset($notification->data['project']['name']))
                                    <flux:badge size="sm" color="zinc">{{ $notification->data['project']['name'] }}</flux:badge>
                                @endif

                                <span class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                                    {{ $notification->created_at->diffForHumans() }}
                                </span>
                            </div>
                        </div>

                        {{-- Visibles siempre y no al pasar el ratón: una bandeja
                             se vacía también desde el móvil, donde no hay ratón
                             que pasar. --}}
                        <div class="flex shrink-0 items-center gap-0.5">
                            @if(($notification->data['action_url'] ?? '#') !== '#' && $notification->data['action_url'])
                                <flux:button
                                    :href="$notification->data['action_url']"
                                    variant="ghost"
                                    size="sm"
                                    icon:trailing="arrow-top-right-on-square"
                                >
                                    {{ $notification->data['action_text'] ?? __('base-tenant::common.view') }}
                                </flux:button>
                            @endif

                            @if(! $notification->read_at)
                                <flux:button
                                    wire:click="markAsRead('{{ $notification->id }}')"
                                    variant="ghost"
                                    size="sm"
                                    icon="check"
                                    :aria-label="__('base-tenant::notifications.mark_one_as_read')"
                                />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 bg-zinc-50/60 px-4 py-2.5 dark:border-zinc-800 dark:bg-zinc-900/40">
                <p class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::common.showing', [
                        'from' => $notifications->firstItem(),
                        'to' => $notifications->lastItem(),
                        'total' => $notifications->total(),
                    ]) }}
                </p>

                <div>{{ $notifications->links() }}</div>
            </div>
        @endif
    </div>

    <flux:modal wire:model="showDeleteModal" name="confirm-notifications-deletion" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ __('base-tenant::notifications.confirm_delete_selected', ['count' => count($selectedNotifications)]) }}
                </flux:heading>
                <flux:subheading>{{ __('base-tenant::notifications.confirm_delete_selected_description') }}</flux:subheading>
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('base-tenant::common.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button wire:click="deleteSelected" variant="danger">
                    <span wire:loading.remove wire:target="deleteSelected">{{ __('base-tenant::common.delete') }}</span>
                    <span wire:loading wire:target="deleteSelected">{{ __('base-tenant::common.processing') }}</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
