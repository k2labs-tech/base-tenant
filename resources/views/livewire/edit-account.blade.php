<div class="space-y-10">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">
                @if($isCreateMode)
                    {{ __('base-tenant::accounts.create_new_account') }}
                @else
                    {{ __('base-tenant::accounts.edit_account_title', ['name' => $account->name]) }}
                @endif
            </flux:heading>
            <flux:subheading>{{ __('base-tenant::accounts.edit_account_description') }}</flux:subheading>
        </div>

        <flux:button :href="route('base-tenant.accounts.index')" variant="ghost" icon="arrow-left">
            {{ __('base-tenant::accounts.back_to_accounts') }}
        </flux:button>
    </div>

    <form wire:submit="saveAccount" class="space-y-10">
        <flux:separator />

        <section class="grid gap-6 md:grid-cols-3">
            <div class="md:col-span-1">
                <flux:heading size="lg">{{ __('base-tenant::accounts.account_information') }}</flux:heading>
                <flux:subheading>{{ __('base-tenant::accounts.account_information_description') }}</flux:subheading>
            </div>

            <div class="md:col-span-2 max-w-xl space-y-4">
                <flux:input wire:model="name" :label="__('base-tenant::accounts.name')" required />

                <flux:select
                    wire:model="selected_owner_id"
                    :label="__('base-tenant::accounts.owner')"
                    :placeholder="__('base-tenant::accounts.select_owner')"
                >
                    @foreach($users as $user)
                        <flux:select.option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:checkbox
                    wire:model="active"
                    :label="__('base-tenant::accounts.active')"
                    :description="__('base-tenant::accounts.active_description')"
                />

                <flux:error name="active" />
            </div>
        </section>

        <flux:separator />

        <section class="grid gap-6 md:grid-cols-3">
            <div class="md:col-span-1">
                <flux:heading size="lg">{{ __('base-tenant::accounts.contact_details') }}</flux:heading>
                <flux:subheading>{{ __('base-tenant::accounts.contact_details_description') }}</flux:subheading>
            </div>

            <div class="md:col-span-2 max-w-xl space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="email" type="email" :label="__('base-tenant::accounts.email')" />
                    <flux:input wire:model="phone" :label="__('base-tenant::accounts.phone')" />
                </div>

                <flux:input wire:model="address" :label="__('base-tenant::accounts.address')" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="city" :label="__('base-tenant::accounts.city')" />
                    <flux:input wire:model="state" :label="__('base-tenant::accounts.state')" />
                    <flux:input wire:model="country" :label="__('base-tenant::accounts.country')" />
                    <flux:input wire:model="postal_code" :label="__('base-tenant::accounts.postal_code')" />
                </div>

                <flux:input wire:model="vat" :label="__('base-tenant::accounts.vat')" />
            </div>
        </section>

        @if($globalForcePasswordChangeEnabled)
            <flux:separator />

            <section class="grid gap-6 md:grid-cols-3">
                <div class="md:col-span-1">
                    <flux:heading size="lg">{{ __('base-tenant::accounts.force_password_change') }}</flux:heading>
                    <flux:subheading>{{ __('base-tenant::accounts.force_password_change_description') }}</flux:subheading>
                </div>

                <div class="md:col-span-2 max-w-xl">
                    <flux:radio.group wire:model="force_password_change">
                        <flux:radio
                            value=""
                            :label="__('base-tenant::accounts.force_password_inherit')"
                            :description="__('base-tenant::accounts.force_password_inherit_description')"
                        />
                        <flux:radio
                            value="1"
                            :label="__('base-tenant::accounts.force_password_enabled')"
                            :description="__('base-tenant::accounts.force_password_enabled_description')"
                        />
                        <flux:radio
                            value="0"
                            :label="__('base-tenant::accounts.force_password_disabled')"
                            :description="__('base-tenant::accounts.force_password_disabled_description')"
                        />
                    </flux:radio.group>
                </div>
            </section>
        @endif

        <flux:separator />

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('base-tenant.accounts.index')" variant="ghost">
                {{ __('base-tenant::accounts.cancel') }}
            </flux:button>

            <flux:button type="submit" variant="primary">
                <span wire:loading.remove wire:target="saveAccount">
                    @if($isCreateMode)
                        {{ __('base-tenant::accounts.create_account') }}
                    @else
                        {{ __('base-tenant::accounts.save_account') }}
                    @endif
                </span>
                <span wire:loading wire:target="saveAccount">{{ __('base-tenant::common.saving') }}</span>
            </flux:button>
        </div>
    </form>

    @if(! $isCreateMode && $accountUsers->count() > 0)
        <flux:separator />

        <section class="grid gap-6 md:grid-cols-3">
            <div class="md:col-span-1">
                <flux:heading size="lg">{{ __('base-tenant::accounts.account_users') }}</flux:heading>
                <flux:subheading>{{ __('base-tenant::accounts.account_users_description') }}</flux:subheading>
            </div>

            <div class="md:col-span-2 max-w-xl space-y-2">
                @foreach($accountUsers as $accountUser)
                    <div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                        <div class="flex items-center gap-3">
                            <flux:avatar size="sm" :name="$accountUser->name" />

                            <div>
                                <flux:link :href="route('base-tenant.users.edit', $accountUser)" wire:navigate variant="ghost">
                                    {{ $accountUser->name }}
                                </flux:link>
                                <flux:text size="sm">{{ $accountUser->email }}</flux:text>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-1">
                            @foreach($accountUser->roles as $role)
                                <flux:badge size="sm">{{ $role->name }}</flux:badge>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
