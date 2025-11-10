<x-base-tenant::app-layout>
    <x-slot name="header">
        {{ __('base-tenant::app.profile.title') }}
    </x-slot>

    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-soft p-6">
            <div class="max-w-xl">
                <livewire:base-tenant.profile.update-profile-information-form />
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-soft p-6">
            <div class="max-w-xl">
                <livewire:base-tenant.preferences />
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-soft p-6">
            <div class="max-w-xl">
                <livewire:base-tenant.profile.update-password-form />
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-soft p-6">
            <div class="max-w-xl">
                <livewire:base-tenant.two-factor-authentication />
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-soft p-6">
            <div class="max-w-xl">
                <livewire:base-tenant.profile.delete-user-form />
            </div>
        </div>
    </div>
</x-base-tenant::app-layout>
