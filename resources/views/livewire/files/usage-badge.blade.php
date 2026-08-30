<div>
    @if(! $metered)
        {{-- Sin métrica de almacenamiento no hay nada que enseñar. Pintar
             «0 de ∞» ocuparía sitio para no decir nada. --}}
    @else
        @php
            $level = match (true) {
                $percentage === null => 'none',
                $percentage >= 100 => 'exceeded',
                $percentage >= 80 => 'warning',
                default => 'ok',
            };
        @endphp

        <div class="space-y-1.5" data-usage-level="{{ $level }}">
            <div class="flex items-baseline justify-between gap-3">
                <span class="text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                    {{ __('base-tenant::files.storage_used') }}
                </span>

                <span class="text-xs tabular-nums text-zinc-600 dark:text-zinc-300">
                    @if($limit === -1)
                        {{ \Illuminate\Support\Number::fileSize($used, precision: 1) }}
                    @else
                        {{ __('base-tenant::metering.used_of', [
                            'used' => \Illuminate\Support\Number::fileSize($used, precision: 1),
                            'limit' => \Illuminate\Support\Number::fileSize($limit, precision: 1),
                        ]) }}
                    @endif
                </span>
            </div>

            @if($percentage !== null)
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <div
                        @class([
                            'h-full rounded-full transition-all',
                            'bg-danger-500' => $level === 'exceeded',
                            'bg-warning-500' => $level === 'warning',
                            'bg-accent-500' => $level === 'ok',
                        ])
                        style="width: {{ min(100, $percentage) }}%"
                    ></div>
                </div>
            @endif
        </div>
    @endif
</div>
