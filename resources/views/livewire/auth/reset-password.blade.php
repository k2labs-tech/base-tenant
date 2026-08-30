<div>
    <form wire:submit="resetPassword" class="space-y-6">
        <flux:input
            wire:model="email"
            type="email"
            :label="__('Email')"
            autocomplete="username"
            required
            autofocus
        />

        <flux:input
            wire:model="password"
            type="password"
            :label="__('Password')"
            autocomplete="new-password"
            viewable
            required
        />

        <flux:input
            wire:model="password_confirmation"
            type="password"
            :label="__('Confirm Password')"
            autocomplete="new-password"
            viewable
            required
        />

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="resetPassword">{{ __('Reset Password') }}</span>
                <span wire:loading wire:target="resetPassword">{{ __('base-tenant::common.processing') }}</span>
            </flux:button>
        </div>
    </form>
</div>
