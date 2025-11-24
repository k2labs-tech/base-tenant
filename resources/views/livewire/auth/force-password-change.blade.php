<div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-secondary-100">
    <div class="w-full sm:max-w-md mt-6 px-6 py-8 bg-white shadow-md overflow-hidden sm:rounded-lg">
        {{-- Header --}}
        <div class="text-center mb-6">
            <h2 class="text-2xl font-bold text-secondary-900">{{ __('base-tenant::auth.change_password') }}</h2>
            <p class="mt-2 text-sm text-secondary-600">
                {{ __('base-tenant::auth.change_password_required') }}
            </p>
        </div>

        {{-- Warning Banner --}}
        <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-yellow-600 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <div class="text-sm text-yellow-700">
                    <p class="font-semibold">{{ __('base-tenant::auth.password_change_required') }}</p>
                    <p class="mt-1">{{ __('base-tenant::auth.account_created_by_admin') }}</p>
                </div>
            </div>
        </div>

        {{-- Form --}}
        <form wire:submit="updatePassword" class="space-y-6">
            {{-- Current Password --}}
            <div>
                <label for="current_password" class="block text-sm font-medium text-secondary-700">
                    {{ __('base-tenant::auth.current_password') }}
                </label>
                <input
                    wire:model="current_password"
                    type="password"
                    id="current_password"
                    required
                    autofocus
                    class="mt-1 w-full px-4 py-2 border border-secondary-300 rounded-md focus:ring-primary-500 focus:border-primary-500"
                />
                @error('current_password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- New Password --}}
            <div>
                <label for="password" class="block text-sm font-medium text-secondary-700">
                    {{ __('base-tenant::auth.new_password') }}
                </label>
                <input
                    wire:model="password"
                    type="password"
                    id="password"
                    required
                    class="mt-1 w-full px-4 py-2 border border-secondary-300 rounded-md focus:ring-primary-500 focus:border-primary-500"
                />
                <p class="mt-1 text-xs text-secondary-500">{{ __('base-tenant::auth.password_requirements') }}</p>
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Confirm Password --}}
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-secondary-700">
                    {{ __('base-tenant::auth.confirm_new_password') }}
                </label>
                <input
                    wire:model="password_confirmation"
                    type="password"
                    id="password_confirmation"
                    required
                    class="mt-1 w-full px-4 py-2 border border-secondary-300 rounded-md focus:ring-primary-500 focus:border-primary-500"
                />
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between gap-4">
                <button
                    wire:click="logout"
                    type="button"
                    class="text-sm text-secondary-600 hover:text-secondary-900 underline"
                >
                    {{ __('base-tenant::auth.logout_instead') }}
                </button>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 focus:bg-primary-700 active:bg-primary-900 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50"
                >
                    <svg wire:loading class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ __('base-tenant::auth.change_password') }}
                </button>
            </div>
        </form>
    </div>
</div>
