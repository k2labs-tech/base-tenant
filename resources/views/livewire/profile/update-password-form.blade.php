<section>
    <header>
        <h2 class="text-lg font-medium text-primary-900">
            {{ __('base-tenant::app.profile.update_password') }}
        </h2>

        <p class="mt-1 text-sm text-primary-600">
            {{ __('base-tenant::app.profile.update_password_description') }}
        </p>
    </header>

    <form wire:submit="updatePassword" class="mt-6 space-y-6">
        <div>
            <x-input-label for="update_password_current_password" :value="__('base-tenant::app.profile.current_password')" />
            <x-text-input wire:model="current_password" id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('base-tenant::app.profile.new_password')" />
            <x-text-input wire:model="password" id="update_password_password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('base-tenant::app.profile.confirm_password')" />
            <x-text-input wire:model="password_confirmation" id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('base-tenant::app.profile.save') }}</x-primary-button>

            <x-action-message class="me-3" on="password-updated">
                {{ __('base-tenant::app.profile.saved') }}
            </x-action-message>
        </div>
    </form>
</section>
