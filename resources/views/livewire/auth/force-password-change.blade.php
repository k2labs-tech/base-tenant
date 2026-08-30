<div class="space-y-6">
    <div class="text-center">
        <flux:heading size="lg">{{ __('base-tenant::auth.change_password') }}</flux:heading>
        <flux:subheading>{{ __('base-tenant::auth.change_password_required') }}</flux:subheading>
    </div>

    <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('base-tenant::auth.password_change_required')">
        <flux:callout.text>{{ __('base-tenant::auth.account_created_by_admin') }}</flux:callout.text>
    </flux:callout>

    <form wire:submit="updatePassword" class="space-y-6">
        <flux:input
            wire:model="current_password"
            type="password"
            :label="__('base-tenant::auth.current_password')"
            autocomplete="current-password"
            viewable
            required
            autofocus
        />

        <flux:input
            wire:model="password"
            type="password"
            :label="__('base-tenant::auth.new_password')"
            :description="__('base-tenant::auth.password_requirements')"
            autocomplete="new-password"
            viewable
            required
        />

        <flux:input
            wire:model="password_confirmation"
            type="password"
            :label="__('base-tenant::auth.confirm_new_password')"
            autocomplete="new-password"
            viewable
            required
        />

        <div class="flex items-center justify-between gap-4">
            <flux:link wire:click="logout" as="button" variant="subtle" class="text-sm">
                {{ __('base-tenant::auth.logout_instead') }}
            </flux:link>

            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="updatePassword">{{ __('base-tenant::auth.change_password') }}</span>
                <span wire:loading wire:target="updatePassword">{{ __('base-tenant::common.processing') }}</span>
            </flux:button>
        </div>
    </form>
</div>
