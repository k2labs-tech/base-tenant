<div class="space-y-6">
    <flux:text>
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </flux:text>

    <form wire:submit="confirmPassword" class="space-y-6">
        <flux:input
            wire:model="password"
            type="password"
            :label="__('Password')"
            autocomplete="current-password"
            viewable
            required
            autofocus
        />

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="confirmPassword">{{ __('Confirm') }}</span>
                <span wire:loading wire:target="confirmPassword">{{ __('base-tenant::common.processing') }}</span>
            </flux:button>
        </div>
    </form>
</div>
