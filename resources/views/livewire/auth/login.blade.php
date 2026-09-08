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

    @if($passkeysEnabled || $magicLinksEnabled)
        <flux:separator :text="__('base-tenant::passwordless.or')" />

        <div class="space-y-3">
            @if($passkeysEnabled)
                {{-- La ceremonia de aserción WebAuthn. El navegador ofrece lo
                     que guarda para este origen: no hay usuario que pedir ni
                     dirección que revelar. --}}
                <div
                    x-data="{
                        busy: false,
                        error: '',

                        async signIn() {
                            this.error = '';

                            if (!window.baseTenantPasskeys?.supported()) {
                                this.error = @js(__('base-tenant::passkeys.unsupported'));
                                return;
                            }

                            this.busy = true;

                            try {
                                const data = await window.baseTenantPasskeys.login(
                                    @js(route('base-tenant.passkeys.login-options')),
                                    @js(route('base-tenant.passkeys.login')),
                                );

                                window.location.href = data.redirect;
                            } catch (e) {
                                if (e.name === 'NotAllowedError') {
                                    this.error = @js(__('base-tenant::passkeys.cancelled'));
                                } else if (e.name === 'ServerError' && e.message) {
                                    this.error = e.message;
                                } else {
                                    this.error = @js(__('base-tenant::passkeys.login_failed'));
                                }
                            } finally {
                                this.busy = false;
                            }
                        },
                    }"
                    class="space-y-2"
                >
                    <x-base-tenant::passkeys-script />

                    <flux:button variant="outline" class="w-full" icon="finger-print" x-on:click="signIn()" x-bind:disabled="busy">
                        {{ __('base-tenant::passkeys.sign_in') }}
                    </flux:button>

                    <p x-show="error" x-text="error" x-cloak class="text-center text-sm text-danger-600 dark:text-danger-400"></p>
                </div>
            @endif

            @if($magicLinksEnabled)
                <flux:button variant="ghost" class="w-full" icon="envelope" :href="route('base-tenant.magic-link.request')" wire:navigate>
                    {{ __('base-tenant::passwordless.link_option') }}
                </flux:button>
            @endif
        </div>
    @endif
</div>
