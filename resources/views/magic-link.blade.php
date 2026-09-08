<x-base-tenant::guest-layout>
    <div class="flex flex-col gap-6">
        @if($invalid)
            <div class="flex flex-col gap-2 text-center">
                <flux:heading size="lg">{{ __('base-tenant::passwordless.link_invalid_title') }}</flux:heading>
                <flux:subheading>{{ __('base-tenant::passwordless.link_invalid') }}</flux:subheading>
            </div>

            @if(Route::has('base-tenant.magic-link.request'))
                <flux:button variant="primary" class="w-full" :href="route('base-tenant.magic-link.request')" wire:navigate>
                    {{ __('base-tenant::passwordless.request_another') }}
                </flux:button>
            @endif
        @else
            <div class="flex flex-col gap-2 text-center">
                <flux:heading size="lg">{{ __('base-tenant::passwordless.confirm_title') }}</flux:heading>
                <flux:subheading>{{ __('base-tenant::passwordless.confirm_body') }}</flux:subheading>
            </div>

            {{-- Un formulario y no un enlace: el GET del correo lo siguen los
                 escáneres antes de que la persona pulse nada, y un enlace gastado
                 ahí es un enlace que nunca le funciona a ella. --}}
            <form method="POST" action="{{ route('base-tenant.magic-link.consume', ['token' => $token]) }}" class="flex flex-col gap-4">
                @csrf

                <flux:button variant="primary" type="submit" class="w-full">
                    {{ __('base-tenant::passwordless.confirm_action') }}
                </flux:button>
            </form>
        @endif

        <div class="text-center text-sm text-zinc-500 dark:text-zinc-400">
            <flux:link :href="route('base-tenant.login')" wire:navigate>
                {{ __('base-tenant::passwordless.back_to_login') }}
            </flux:link>
        </div>
    </div>
</x-base-tenant::guest-layout>
