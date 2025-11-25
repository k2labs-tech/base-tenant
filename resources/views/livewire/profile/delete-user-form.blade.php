<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-primary-900">
            {{ __('base-tenant::app.profile.delete_account') }}
        </h2>

        <p class="mt-1 text-sm text-primary-600">
            {{ __('base-tenant::app.profile.delete_account_description') }}
        </p>
    </header>

    <button
        x-on:click="$flux.modal('confirm-user-deletion').show()"
        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-hidden focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150"
    >{{ __('base-tenant::app.profile.delete_account') }}</button>

    <flux:modal name="confirm-user-deletion" class="min-w-[22rem] space-y-6">
        <form wire:submit="deleteUser">
            <div>
                <flux:heading size="lg">{{ __('base-tenant::app.profile.are_you_sure') }}</flux:heading>
                <flux:subheading>
                    <p class="mt-4">
                        {{ __('base-tenant::app.profile.delete_account_warning') }}
                    </p>
                </flux:subheading>
            </div>

            <div class="mt-6">
                <flux:input
                    wire:model="password"
                    type="password"
                    label="{{ __('base-tenant::app.profile.password') }}"
                    placeholder="{{ __('base-tenant::app.profile.password') }}"
                />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex gap-2 justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('base-tenant::app.profile.cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="danger">
                    {{ __('base-tenant::app.profile.delete_account') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
