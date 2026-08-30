<div>
    <x-base-tenant::social-buttons class="mb-6" :label="__('base-tenant::social.sign_up_with')" />

    <form wire:submit="register" class="space-y-6">
        <flux:input
            wire:model="name"
            :label="__('Name')"
            autocomplete="name"
            required
            autofocus
        />

        <flux:input
            wire:model="companyName"
            :label="__('Company Name')"
            autocomplete="organization"
            required
        />

        <flux:input
            wire:model="email"
            type="email"
            :label="__('Email')"
            autocomplete="username"
            required
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

        <div class="flex items-center justify-end gap-4">
            <flux:link :href="route('base-tenant.login')" wire:navigate variant="subtle" class="text-sm">
                {{ __('Already registered?') }}
            </flux:link>

            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="register">{{ __('Register') }}</span>
                <span wire:loading wire:target="register">{{ __('base-tenant::common.processing') }}</span>
            </flux:button>
        </div>
    </form>
</div>
