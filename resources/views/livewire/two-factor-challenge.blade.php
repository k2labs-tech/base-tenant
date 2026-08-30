<div class="space-y-6">
    <div class="text-center">
        <flux:heading size="lg">{{ __('base-tenant::auth.2fa.verification_title') }}</flux:heading>
        <flux:subheading>
            @if (! $usingRecoveryCode)
                {{ __('base-tenant::auth.2fa.verification_description') }}
            @else
                {{ __('base-tenant::auth.2fa.recovery_description') }}
            @endif
        </flux:subheading>
    </div>

    <form wire:submit="verify" class="space-y-6">
        @if (! $usingRecoveryCode)
            <flux:input
                wire:model="code"
                :label="__('base-tenant::auth.2fa.verification_code')"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="6"
                placeholder="000000"
                autocomplete="one-time-code"
                required
                autofocus
            />
        @else
            <flux:input
                wire:model="recoveryCode"
                :label="__('base-tenant::auth.2fa.recovery_code')"
                autocomplete="one-time-code"
                required
                autofocus
            />
        @endif

        <div class="flex items-center justify-between gap-4">
            <flux:link wire:click="toggleRecoveryCode" as="button" variant="subtle" class="text-sm">
                @if (! $usingRecoveryCode)
                    {{ __('base-tenant::auth.2fa.use_recovery_code') }}
                @else
                    {{ __('base-tenant::auth.2fa.use_authentication_code') }}
                @endif
            </flux:link>

            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="verify">{{ __('base-tenant::auth.2fa.verify') }}</span>
                <span wire:loading wire:target="verify">{{ __('base-tenant::common.processing') }}</span>
            </flux:button>
        </div>
    </form>

    <div class="text-center">
        <flux:link :href="route('base-tenant.login')" wire:navigate variant="subtle" class="text-sm">
            {{ __('base-tenant::auth.2fa.back_to_login') }}
        </flux:link>
    </div>
</div>
