<div>
    <form wire:submit="register">
        <!-- Name -->
        <div>
            <x-base-tenant::input-label for="name" :value="__('Name')" />
            <x-base-tenant::text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus autocomplete="name" />
            <x-base-tenant::input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Company Name -->
        <div class="mt-4">
            <x-base-tenant::input-label for="company_name" :value="__('Company Name')" />
            <x-base-tenant::text-input wire:model="companyName" id="company_name" class="block mt-1 w-full" type="text" name="company_name" required autocomplete="company_name" />
            <x-base-tenant::input-error :messages="$errors->get('companyName')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-base-tenant::input-label for="email" :value="__('Email')" />
            <x-base-tenant::text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="username" />
            <x-base-tenant::input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-base-tenant::input-label for="password" :value="__('Password')" />

            <x-base-tenant::text-input wire:model="password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-base-tenant::input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-base-tenant::input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-base-tenant::text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-base-tenant::input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('base-tenant.login') }}" wire:navigate>
                {{ __('Already registered?') }}
            </a>

            <x-base-tenant::primary-button class="ms-4">
                {{ __('Register') }}
            </x-base-tenant::primary-button>
        </div>
    </form>
</div>
