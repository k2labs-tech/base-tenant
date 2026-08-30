<div>
    @php
        // Una sola cuenta y sin ser personal de plataforma: no hay nada entre
        // lo que elegir, así que el nombre se enseña y ya está. Un desplegable
        // de un elemento invita a pulsarlo para no encontrar nada.
        $onlyOne = ! $isStaff && $accounts->count() <= 1;
    @endphp

    @if($onlyOne)
        @if($accounts->count() === 1)
            <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                <flux:icon.building-office-2 variant="micro" class="text-zinc-400 dark:text-zinc-500" />
                <span class="truncate">{{ $accounts->first()->name }}</span>
            </div>
        @endif
    @else
        <flux:dropdown position="bottom" align="start">
            <flux:button size="sm" icon="building-office-2" icon:trailing="chevron-down" class="max-w-56">
                <span class="truncate">
                    {{ $current?->name ?? __('base-tenant::accounts.no_account') }}
                </span>

                @if($isStaff)
                    {{-- Que el personal de plataforma sepa siempre que está
                         mirando datos de un cliente y no los suyos. --}}
                    <flux:badge size="sm" color="amber" inset="top bottom">
                        {{ __('base-tenant::accounts.staff') }}
                    </flux:badge>
                @endif
            </flux:button>

            <flux:menu class="min-w-72">
                @if($searchable)
                    <div class="px-2 py-1.5">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            icon="magnifying-glass"
                            size="sm"
                            type="search"
                            :placeholder="__('base-tenant::accounts.search_placeholder')"
                            :label:sr-only="__('base-tenant::common.search')"
                        />
                    </div>

                    <flux:menu.separator />
                @endif

                @forelse($accounts as $account)
                    <flux:menu.item
                        wire:click="switchAccount('{{ $account->id }}')"
                        :icon="$currentAccountId === $account->id ? 'check' : null"
                    >
                        {{ $account->name }}
                    </flux:menu.item>
                @empty
                    <div class="px-3 py-4 text-center text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $search !== '' ? __('base-tenant::common.empty_search') : __('base-tenant::accounts.none') }}
                    </div>
                @endforelse

                @if($isStaff && $currentAccountId)
                    <flux:menu.separator />

                    {{-- La salida. Sin ella, entrar en una cuenta es un viaje
                         de ida: el resolver recuerda la última y no habría
                         forma de volver a la vista de plataforma. --}}
                    <flux:menu.item wire:click="leaveAccount" icon="arrow-left-start-on-rectangle">
                        {{ __('base-tenant::accounts.leave') }}
                    </flux:menu.item>
                @endif
            </flux:menu>
        </flux:dropdown>
    @endif
</div>
