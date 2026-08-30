<x-base-tenant::guest-layout>
    <div class="mx-auto max-w-3xl space-y-10 py-10">
        <div class="space-y-3 text-center">
            <h1 class="text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">
                {{ config('app.name') }}
            </h1>

            <p class="text-base text-zinc-600 dark:text-zinc-300">
                {{ __('base-tenant::presale.registration_closed') }}
            </p>

            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('base-tenant::presale.registration_closed_hint') }}
            </p>
        </div>

        <div class="mx-auto max-w-md">
            <livewire:base-tenant.presale.waitlist-form source="landing" />
        </div>

        <livewire:base-tenant.presale.pricing-table />
    </div>
</x-base-tenant::guest-layout>
