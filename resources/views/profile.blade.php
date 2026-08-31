<x-base-tenant::app-layout>
    <div class="space-y-10">
        <div>
            <flux:heading size="xl">{{ __('base-tenant::app.profile.title') }}</flux:heading>
            <flux:subheading>{{ __('base-tenant::app.profile.subtitle') }}</flux:subheading>
        </div>

        <flux:separator />

        <livewire:base-tenant.profile.update-profile-information-form />

        <flux:separator />

        <livewire:base-tenant.preferences />

        <flux:separator />

        <livewire:base-tenant.profile.update-password-form />

        <flux:separator />

        <livewire:base-tenant.profile.connected-accounts />

        <flux:separator />

        <livewire:base-tenant.two-factor-authentication />

        <flux:separator />

        <livewire:base-tenant.profile.active-sessions />

        <flux:separator />

        <livewire:base-tenant.profile.delete-user-form />
    </div>
</x-base-tenant::app-layout>
