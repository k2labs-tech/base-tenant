<x-app-layout>
    <x-slot name="header">
        {{ __('app.profile.title') }}
    </x-slot>

    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-soft p-6">
            <div class="max-w-xl">
                <livewire:profile.update-profile-information-form />
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-soft p-6">
            <div class="max-w-xl">
                <livewire:preferences />
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-soft p-6">
            <div class="max-w-xl">
                <livewire:profile.update-password-form />
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-soft p-6">
            <div class="max-w-xl">
                <livewire:two-factor-authentication />
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-soft p-6">
            <div class="max-w-xl">
                <livewire:profile.delete-user-form />
            </div>
        </div>
    </div>
</x-app-layout>
