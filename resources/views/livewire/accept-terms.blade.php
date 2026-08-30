<div class="mx-auto max-w-xl">
    <div class="rounded-xl border border-zinc-200 bg-white p-8 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mb-6 flex size-11 items-center justify-center rounded-full bg-accent-100 text-accent-600 dark:bg-accent-950/50 dark:text-accent-400">
            <flux:icon.document-text variant="outline" class="size-5" />
        </div>

        <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-white">
            {{ __('base-tenant::gdpr.terms_title') }}
        </h1>

        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
            {{ __('base-tenant::gdpr.terms_body') }}
        </p>

        <form wire:submit="accept" class="mt-6 space-y-5">
            <flux:checkbox
                wire:model="accepted"
                :label="__('base-tenant::gdpr.terms_checkbox', ['version' => $version])"
            />

            @if($url)
                <flux:link href="{{ $url }}" target="_blank" rel="noopener" variant="subtle" class="text-sm">
                    {{ __('base-tenant::gdpr.terms_read') }}
                </flux:link>
            @endif

            <flux:error name="accepted" />

            <div class="flex items-center justify-between gap-3">
                {{-- Salir tiene que seguir siendo posible: obligar a aceptar
                     sin dejar irse no es un consentimiento. --}}
                <flux:link href="{{ route('base-tenant.logout') }}" variant="subtle" class="text-sm">
                    {{ __('base-tenant::gdpr.terms_decline') }}
                </flux:link>

                <flux:button type="submit" variant="primary">
                    {{ __('base-tenant::gdpr.terms_accept') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
