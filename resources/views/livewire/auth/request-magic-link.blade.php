<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-2 text-center">
        <flux:heading size="lg">{{ __('base-tenant::passwordless.title') }}</flux:heading>
        <flux:subheading>{{ __('base-tenant::passwordless.subtitle') }}</flux:subheading>
    </div>

    @if($sent)
        {{-- La misma respuesta exista o no la dirección: lo contrario
             convierte este formulario en una forma de preguntarle al producto
             si alguien es cliente suyo. --}}
        <div class="rounded-lg border border-zinc-200 bg-zinc-50/60 px-4 py-4 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900/40 dark:text-zinc-300">
            <div class="flex items-start gap-2">
                <flux:icon.envelope variant="micro" class="mt-0.5 shrink-0" />
                <div class="space-y-1">
                    <p class="font-medium text-zinc-900 dark:text-white">
                        {{ __('base-tenant::passwordless.sent_title') }}
                    </p>
                    <p>{{ __('base-tenant::passwordless.sent_body', ['minutes' => $ttl]) }}</p>
                </div>
            </div>
        </div>

        <flux:button variant="ghost" :href="route('base-tenant.login')" wire:navigate>
            {{ __('base-tenant::passwordless.back_to_login') }}
        </flux:button>
    @else
        <form wire:submit="send" class="flex flex-col gap-6">
            <flux:input
                wire:model="email"
                type="email"
                required
                autofocus
                autocomplete="email"
                :label="__('base-tenant::auth.email')"
                placeholder="ada@example.com"
            />

            <flux:button variant="primary" type="submit" class="w-full" wire:loading.attr="disabled">
                {{ __('base-tenant::passwordless.send') }}
            </flux:button>
        </form>

        <div class="text-center text-sm text-zinc-500 dark:text-zinc-400">
            <flux:link :href="route('base-tenant.login')" wire:navigate>
                {{ __('base-tenant::passwordless.use_password') }}
            </flux:link>
        </div>
    @endif
</div>
