<div class="space-y-6">
    @if($presale && $seatsLeft !== null)
        {{-- El contador es el argumento entero de una pre-venta: sin él, una
             oferta de fundador es una rebaja sin motivo. --}}
        <div @class([
            'flex items-center justify-center gap-2 rounded-lg border px-4 py-2.5 text-sm',
            'border-accent-200 bg-accent-50 text-accent-800 dark:border-accent-900 dark:bg-accent-950/40 dark:text-accent-200' => ! $soldOut,
            'border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300' => $soldOut,
        ])>
            <flux:icon.sparkles variant="micro" />

            @if($soldOut)
                {{ __('base-tenant::presale.sold_out') }}
            @else
                {{ trans_choice('base-tenant::presale.seats_left', $seatsLeft, ['count' => $seatsLeft]) }}
            @endif
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        @foreach($plans as $key => $plan)
            @php($destacado = $presale && $key === $foundingPlan)

            <div @class([
                'flex flex-col rounded-xl border bg-white p-6 dark:bg-zinc-900',
                'border-accent-300 ring-1 ring-accent-300 dark:border-accent-700 dark:ring-accent-700' => $destacado,
                'border-zinc-200 dark:border-zinc-800' => ! $destacado,
            ]) data-plan="{{ $key }}">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $plan['name'] ?? $key }}</h3>

                    @if($destacado)
                        <flux:badge size="sm" color="violet">{{ __('base-tenant::presale.founding') }}</flux:badge>
                    @endif
                </div>

                <ul class="mt-4 flex-1 space-y-2">
                    @foreach($plan['features'] ?? [] as $feature => $value)
                        <li class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                            @if(is_bool($value))
                                <flux:icon :icon="$value ? 'check' : 'x-mark'" variant="micro" @class([
                                    'mt-0.5 shrink-0',
                                    'text-success-500' => $value,
                                    'text-zinc-300 dark:text-zinc-600' => ! $value,
                                ]) />
                            @else
                                <flux:icon.check variant="micro" class="mt-0.5 shrink-0 text-success-500" />
                            @endif

                            <span>
                                {{ __('base-tenant::features.names.'.$feature) === 'base-tenant::features.names.'.$feature ? $feature : __('base-tenant::features.names.'.$feature) }}
                                @if(! is_bool($value))
                                    <span class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ (int) $value === -1 ? '∞' : $value }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</div>
