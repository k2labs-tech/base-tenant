<section class="grid gap-6 md:grid-cols-3">
    <div class="md:col-span-1">
        <flux:heading size="lg">{{ __('base-tenant::app.profile.delete_account') }}</flux:heading>
        <flux:subheading>{{ __('base-tenant::app.profile.delete_account_description') }}</flux:subheading>
    </div>

    <div class="md:col-span-2 max-w-xl">
        <flux:modal.trigger name="confirm-user-deletion">
            <flux:button variant="danger" icon="trash">
                {{ __('base-tenant::app.profile.delete_account') }}
            </flux:button>
        </flux:modal.trigger>

        <flux:modal name="confirm-user-deletion" class="min-w-[22rem]">
            <form wire:submit="deleteUser" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('base-tenant::app.profile.are_you_sure') }}</flux:heading>

                    {{-- Un modal de borrado que no nombra lo que borra invita a
                         confirmar sin leer: aquí va el titular concreto. --}}
                    <flux:subheading>
                        {{ __('base-tenant::app.profile.delete_account_target', [
                            'name' => auth()->user()->name,
                            'email' => auth()->user()->email,
                        ]) }}
                    </flux:subheading>

                    <flux:callout variant="danger" icon="exclamation-triangle" class="mt-4">
                        <flux:callout.text>
                            {{ __('base-tenant::app.profile.delete_account_warning') }}
                        </flux:callout.text>
                    </flux:callout>
                </div>

                <flux:input
                    wire:model="password"
                    type="password"
                    :label="__('base-tenant::app.profile.password')"
                    :placeholder="__('base-tenant::app.profile.password')"
                    viewable
                />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('base-tenant::app.profile.cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button type="submit" variant="danger">
                        <span wire:loading.remove wire:target="deleteUser">{{ __('base-tenant::app.profile.delete_account') }}</span>
                        <span wire:loading wire:target="deleteUser">{{ __('base-tenant::common.processing') }}</span>
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    </div>
</section>
