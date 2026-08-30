<div class="space-y-6">
    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- El error de un intento social vuelve al login, y sin esto se perdería
         en silencio: la persona volvería a la misma pantalla sin saber por qué. --}}
    @error('social')
        <flux:callout variant="danger" icon="exclamation-triangle" class="mb-6">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    <x-base-tenant::social-buttons class="mb-6" />

    <form wire:submit="login" class="space-y-6">
        {{-- El aviso de credenciales incorrectas y el de demasiados intentos
             llegan bajo `form.email`, que es de donde Flux saca el nombre del
             campo: atar esto a `email` a secas dejaría el formulario mudo. --}}
        <flux:input
            wire:model="form.email"
            type="email"
            :label="__('Email')"
            autocomplete="username"
            required
            autofocus
        />

        <flux:input
            wire:model="form.password"
            type="password"
            :label="__('Password')"
            autocomplete="current-password"
            viewable
            required
        />

        <flux:checkbox wire:model="form.remember" :label="__('Remember me')" />

        <div class="flex items-center justify-end gap-4">
            @if (Route::has('base-tenant.password.request'))
                <flux:link :href="route('base-tenant.password.request')" wire:navigate variant="subtle" class="text-sm">
                    {{ __('Forgot your password?') }}
                </flux:link>
            @endif

            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="login">{{ __('Log in') }}</span>
                <span wire:loading wire:target="login">{{ __('base-tenant::common.processing') }}</span>
            </flux:button>
        </div>
    </form>
</div>
