<div>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    <form wire:submit="confirmPassword">
        <!-- Password -->
        <div>
            <x-base-tenant::input-label for="password" :value="__('Password')" />

            <x-base-tenant::text-input wire:model="password"
                          id="password"
                          class="block mt-1 w-full"
                          type="password"
                          name="password"
                          required autocomplete="current-password" />

            <x-base-tenant::input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end mt-4">
            <x-base-tenant::primary-button>
                {{ __('Confirm') }}
            </x-base-tenant::primary-button>
        </div>
    </form>
</div>
