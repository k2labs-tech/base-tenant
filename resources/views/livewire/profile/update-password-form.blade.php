<section class="grid gap-6 md:grid-cols-3">
    <div class="md:col-span-1">
        <flux:heading size="lg">{{ __('base-tenant::app.profile.update_password') }}</flux:heading>
        <flux:subheading>{{ __('base-tenant::app.profile.update_password_description') }}</flux:subheading>
    </div>

    <form wire:submit="updatePassword" class="md:col-span-2 max-w-xl space-y-4">
        <flux:input
            wire:model="current_password"
            type="password"
            :label="__('base-tenant::app.profile.current_password')"
            autocomplete="current-password"
            viewable
        />

        <flux:input
            wire:model="password"
            type="password"
            :label="__('base-tenant::app.profile.new_password')"
            autocomplete="new-password"
            viewable
        />

        <flux:input
            wire:model="password_confirmation"
            type="password"
            :label="__('base-tenant::app.profile.confirm_password')"
            autocomplete="new-password"
            viewable
        />

        <flux:button type="submit" variant="primary">
            <span wire:loading.remove wire:target="updatePassword">{{ __('base-tenant::app.profile.save') }}</span>
            <span wire:loading wire:target="updatePassword">{{ __('base-tenant::common.saving') }}</span>
        </flux:button>
    </form>
</section>
