<div>
    <div class="">
        <div class="">
            <div class="mb-6">
                <a href="{{ route('base-tenant.accounts.index') }}" class="text-primary-600 hover:text-primary-900">
                    ← {{ __('base-tenant::accounts.back_to_accounts') }}
                </a>
            </div>

            <h2 class="text-2xl font-semibold text-secondary-900 mb-6">
                @if($isCreateMode)
                    {{ __('base-tenant::accounts.create_new_account') }}
                @else
                    {{ __('base-tenant::accounts.edit_account_title', ['name' => $account->name]) }}
                @endif
            </h2>

            <form wire:submit="saveAccount">
                <!-- Account Information -->
                <div class="bg-white overflow-hidden rounded-xl shadow-soft mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-secondary-900 mb-4">{{ __('base-tenant::accounts.account_information') }}</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="name" value="{{ __('base-tenant::accounts.name') }}" />
                                <x-text-input id="name" type="text" class="mt-1 block w-full" wire:model="name" required />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="selected_owner_id" value="{{ __('base-tenant::accounts.owner') }}" />
                                <select id="selected_owner_id" wire:model="selected_owner_id" class="mt-1 block w-full border-secondary-300 focus:border-primary-500 focus:ring-primary-500 rounded-md shadow-xs">
                                    <option value="">{{ __('base-tenant::accounts.select_owner') }}</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('selected_owner_id')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="email" value="{{ __('base-tenant::accounts.email') }}" />
                                <x-text-input id="email" type="email" class="mt-1 block w-full" wire:model="email" />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="phone" value="{{ __('base-tenant::accounts.phone') }}" />
                                <x-text-input id="phone" type="text" class="mt-1 block w-full" wire:model="phone" />
                                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                            </div>

                            <div class="md:col-span-2">
                                <x-input-label for="address" value="{{ __('base-tenant::accounts.address') }}" />
                                <x-text-input id="address" type="text" class="mt-1 block w-full" wire:model="address" />
                                <x-input-error :messages="$errors->get('address')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="city" value="{{ __('base-tenant::accounts.city') }}" />
                                <x-text-input id="city" type="text" class="mt-1 block w-full" wire:model="city" />
                                <x-input-error :messages="$errors->get('city')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="state" value="{{ __('base-tenant::accounts.state') }}" />
                                <x-text-input id="state" type="text" class="mt-1 block w-full" wire:model="state" />
                                <x-input-error :messages="$errors->get('state')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="country" value="{{ __('base-tenant::accounts.country') }}" />
                                <x-text-input id="country" type="text" class="mt-1 block w-full" wire:model="country" />
                                <x-input-error :messages="$errors->get('country')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="postal_code" value="{{ __('base-tenant::accounts.postal_code') }}" />
                                <x-text-input id="postal_code" type="text" class="mt-1 block w-full" wire:model="postal_code" />
                                <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="vat" value="{{ __('base-tenant::accounts.vat') }}" />
                                <x-text-input id="vat" type="text" class="mt-1 block w-full" wire:model="vat" />
                                <x-input-error :messages="$errors->get('vat')" class="mt-2" />
                            </div>

                            <div class="flex items-center">
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model="active" class="rounded-sm border-secondary-300 text-primary-600 shadow-xs focus:ring-primary-500">
                                    <span class="ml-2 text-sm text-secondary-700">{{ __('base-tenant::accounts.active') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="mt-4">
                            <x-primary-button>
                                @if($isCreateMode)
                                    {{ __('base-tenant::accounts.create_account') }}
                                @else
                                    {{ __('base-tenant::accounts.save_account') }}
                                @endif
                            </x-primary-button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Account Users -->
            @if(!$isCreateMode && $accountUsers->count() > 0)
                <div class="bg-white overflow-hidden rounded-xl shadow-soft mt-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-secondary-900 mb-4">{{ __('base-tenant::accounts.account_users') }}</h3>
                        <p class="text-sm text-secondary-600 mb-4">{{ __('base-tenant::accounts.account_users_description') }}</p>

                        <div class="space-y-3">
                            @foreach($accountUsers as $accountUser)
                                <div class="flex items-center justify-between p-3 bg-surface-50 rounded-md hover:bg-surface-100 transition">
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            <div class="w-10 h-10 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center font-medium">
                                                {{ strtoupper(substr($accountUser->name, 0, 1)) }}
                                            </div>
                                        </div>
                                        <div>
                                            <a href="{{ route('base-tenant.users.edit', $accountUser) }}"
                                               class="text-sm font-medium text-primary-600 hover:text-primary-900"
                                               wire:navigate>
                                                {{ $accountUser->name }}
                                            </a>
                                            <p class="text-xs text-secondary-500">{{ $accountUser->email }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        @foreach($accountUser->roles as $role)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800">
                                                {{ $role->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
