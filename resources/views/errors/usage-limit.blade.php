<x-base-tenant::app-layout>
    <div class="flex items-center justify-center min-h-[60vh]">
        <div class="w-full max-w-md rounded-xl border border-zinc-200 bg-white p-8 text-center dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-warning-100 dark:bg-warning-900/40">
                <flux:icon.exclamation-triangle class="size-8 text-warning-600 dark:text-warning-400" />
            </div>

            <h2 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                {{ __('base-tenant::metering.limit_reached_title') }}
            </h2>

            <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">
                {{ __('base-tenant::metering.limit_reached_body', [
                    'current' => number_format($current),
                    'limit' => number_format($limit),
                ]) }}
            </p>

            <div class="mt-8 flex items-center justify-center gap-3">
                @if ($upgradeUrl)
                    <flux:button variant="primary" href="{{ $upgradeUrl }}">
                        {{ __('base-tenant::metering.upgrade') }}
                    </flux:button>
                @endif

                <flux:button variant="ghost" href="{{ url()->previous() }}">
                    {{ __('base-tenant::metering.go_back') }}
                </flux:button>
            </div>
        </div>
    </div>
</x-base-tenant::app-layout>
