<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-primary-900">
            {{ __('base-tenant::app.profile.delete_account') }}
        </h2>

        <p class="mt-1 text-sm text-primary-600">
            {{ __('base-tenant::app.profile.delete_account_description') }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('base-tenant::app.profile.delete_account') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6">

            <h2 class="text-lg font-medium text-primary-900">
                {{ __('base-tenant::app.profile.are_you_sure') }}
            </h2>

            <p class="mt-1 text-sm text-primary-600">
                {{ __('base-tenant::app.profile.delete_account_warning') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('base-tenant::app.profile.password') }}" class="sr-only" />

                <x-text-input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="{{ __('base-tenant::app.profile.password') }}"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('base-tenant::app.profile.cancel') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('base-tenant::app.profile.delete_account') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
