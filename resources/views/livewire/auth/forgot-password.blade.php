<div class="space-y-6">
    <flux:text>
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </flux:text>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    <form wire:submit="sendPasswordResetLink" class="space-y-6">
        <flux:input
            wire:model="email"
            type="email"
            :label="__('Email')"
            autocomplete="username"
            required
            autofocus
        />

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="sendPasswordResetLink">{{ __('Email Password Reset Link') }}</span>
                <span wire:loading wire:target="sendPasswordResetLink">{{ __('base-tenant::common.processing') }}</span>
            </flux:button>
        </div>
    </form>
</div>
