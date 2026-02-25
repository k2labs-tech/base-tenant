<div>
    {{-- Header --}}
    <div class="text-center mb-4">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ __('base-tenant::auth.change_password') }}</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('base-tenant::auth.change_password_required') }}
        </p>
    </div>

    {{-- Warning Banner --}}
    <div class="mb-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <div class="text-sm text-yellow-700 dark:text-yellow-400">
                <p class="font-medium">{{ __('base-tenant::auth.password_change_required') }}</p>
                <p class="mt-1">{{ __('base-tenant::auth.account_created_by_admin') }}</p>
            </div>
        </div>
    </div>

    {{-- Form --}}
    <form wire:submit="updatePassword">
        {{-- Current Password --}}
        <div>
            <x-base-tenant::input-label for="current_password" :value="__('base-tenant::auth.current_password')" />
            <x-base-tenant::text-input
                wire:model="current_password"
                id="current_password"
                class="block mt-1 w-full"
                type="password"
                name="current_password"
                required
                autofocus
                autocomplete="current-password"
            />
            <x-base-tenant::input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        {{-- New Password --}}
        <div class="mt-4">
            <x-base-tenant::input-label for="password" :value="__('base-tenant::auth.new_password')" />
            <x-base-tenant::text-input
                wire:model="password"
                id="password"
                class="block mt-1 w-full"
                type="password"
                name="password"
                required
                autocomplete="new-password"
            />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('base-tenant::auth.password_requirements') }}</p>
            <x-base-tenant::input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        {{-- Confirm Password --}}
        <div class="mt-4">
            <x-base-tenant::input-label for="password_confirmation" :value="__('base-tenant::auth.confirm_new_password')" />
            <x-base-tenant::text-input
                wire:model="password_confirmation"
                id="password_confirmation"
                class="block mt-1 w-full"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
            />
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between mt-4">
            <button
                wire:click="logout"
                type="button"
                class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800"
            >
                {{ __('base-tenant::auth.logout_instead') }}
            </button>

            <x-base-tenant::primary-button wire:loading.attr="disabled">
                <svg wire:loading wire:target="updatePassword" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ __('base-tenant::auth.change_password') }}
            </x-base-tenant::primary-button>
        </div>
    </form>
</div>
