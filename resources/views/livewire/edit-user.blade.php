<div>
    <div class="">
        <div class="">
            <div class="mb-6">
                <a href="{{ route('users.index') }}" class="text-primary-600 hover:text-primary-900">
                    ← {{ __('users.back_to_users') }}
                </a>
            </div>

            <h2 class="text-2xl font-semibold text-secondary-900 mb-6">
                @if($isCreateMode)
                    {{ __('users.create_new_user') }}
                @else
                    {{ __('users.edit_user_title', ['name' => $user->name]) }}
                @endif
            </h2>

            @if($isCreateMode)
                <!-- Create User Form -->
                <form wire:submit="updateProfileInformation">
            @endif

            <!-- Profile Information -->
            <div class="bg-white overflow-hidden rounded-xl shadow-soft mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-secondary-900 mb-4">{{ __('users.profile_information') }}</h3>

                    @if(!$isCreateMode)
                        <form wire:submit="updateProfileInformation">
                    @endif
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="name" value="{{ __('users.name') }}" />
                                <x-text-input id="name" type="text" class="mt-1 block w-full" wire:model="name" required />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="email" value="{{ __('users.email') }}" />
                                <x-text-input id="email" type="email" class="mt-1 block w-full" wire:model="email" required />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="phone" value="{{ __('users.phone') }}" />
                                <x-text-input id="phone" type="text" class="mt-1 block w-full" wire:model="phone" />
                                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                            </div>
                        </div>

                        <div class="mt-4">
                            <x-primary-button>
                                @if($isCreateMode)
                                    {{ __('users.create_user') }}
                                @else
                                    {{ __('users.save_profile') }}
                                @endif
                            </x-primary-button>
                        </div>
                    @if(!$isCreateMode)
                        </form>
                    @endif
                </div>
            </div>

            @if($isCreateMode)
                <!-- Password for new user -->
                <div class="bg-white overflow-hidden shadow-xs sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-secondary-900 mb-4">{{ __('users.password') }}</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="password" value="{{ __('users.password') }}" />
                                <x-text-input id="password" type="password" class="mt-1 block w-full" wire:model="password" required />
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="password_confirmation" value="{{ __('users.confirm_password') }}" />
                                <x-text-input id="password_confirmation" type="password" class="mt-1 block w-full" wire:model="password_confirmation" required />
                                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <!-- Update Password -->
                <div class="bg-white overflow-hidden shadow-xs sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-secondary-900 mb-4">{{ __('users.update_password') }}</h3>

                        <form wire:submit="updatePassword">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="password" value="{{ __('users.new_password') }}" />
                                    <x-text-input id="password" type="password" class="mt-1 block w-full" wire:model="password" />
                                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="password_confirmation" value="{{ __('users.confirm_password') }}" />
                                    <x-text-input id="password_confirmation" type="password" class="mt-1 block w-full" wire:model="password_confirmation" />
                                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                                </div>
                            </div>

                            <div class="mt-4">
                                <x-primary-button>{{ __('users.update_password') }}</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            <!-- Preferences -->
            @if(!$isCreateMode)
                <div class="bg-white overflow-hidden shadow-xs sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-secondary-900 mb-4">{{ __('users.preferences') }}</h3>

                    <form wire:submit="updatePreferences">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="timezone" value="{{ __('users.timezone') }}" />
                                <select id="timezone" wire:model="timezone" class="mt-1 block w-full border-secondary-300 focus:border-primary-500 focus:ring-primary-500 rounded-md shadow-xs">
                                    <option value="">{{ __('users.select_timezone') }}</option>
                                    @foreach($timezones as $tz)
                                        <option value="{{ $tz }}">{{ $tz }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="locale" value="{{ __('users.language') }}" />
                                <select id="locale" wire:model="locale" class="mt-1 block w-full border-secondary-300 focus:border-primary-500 focus:ring-primary-500 rounded-md shadow-xs">
                                    <option value="">{{ __('users.select_language') }}</option>
                                    @foreach($locales as $code => $name)
                                        <option value="{{ $code }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('locale')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="currency" value="{{ __('users.currency') }}" />
                                <select id="currency" wire:model="currency" class="mt-1 block w-full border-secondary-300 focus:border-primary-500 focus:ring-primary-500 rounded-md shadow-xs">
                                    <option value="">{{ __('users.select_currency') }}</option>
                                    @foreach($currencies as $code => $name)
                                        <option value="{{ $code }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="decimal_places" value="{{ __('users.decimal_places') }}" />
                                <select id="decimal_places" wire:model="decimal_places" class="mt-1 block w-full border-secondary-300 focus:border-primary-500 focus:ring-primary-500 rounded-md shadow-xs">
                                    <option value="">{{ __('users.select_decimal_places') }}</option>
                                    @for($i = 0; $i <= 4; $i++)
                                        <option value="{{ $i }}">{{ $i }}</option>
                                    @endfor
                                </select>
                                <x-input-error :messages="$errors->get('decimal_places')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="decimals_separator" value="{{ __('users.decimal_separator') }}" />
                                <select id="decimals_separator" wire:model="decimals_separator" class="mt-1 block w-full border-secondary-300 focus:border-primary-500 focus:ring-primary-500 rounded-md shadow-xs">
                                    <option value="">{{ __('users.select_separator') }}</option>
                                    <option value=".">. ({{ __('users.dot') }})</option>
                                    <option value=",">, ({{ __('users.comma') }})</option>
                                </select>
                                <x-input-error :messages="$errors->get('decimals_separator')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="thousands_separator" value="{{ __('users.thousands_separator') }}" />
                                <select id="thousands_separator" wire:model="thousands_separator" class="mt-1 block w-full border-secondary-300 focus:border-primary-500 focus:ring-primary-500 rounded-md shadow-xs">
                                    <option value="">{{ __('users.select_separator') }}</option>
                                    <option value=",">, ({{ __('users.comma') }})</option>
                                    <option value=".">. ({{ __('users.dot') }})</option>
                                    <option value=" ">{{ __('users.space') }}</option>
                                </select>
                                <x-input-error :messages="$errors->get('thousands_separator')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="date_format" value="{{ __('users.date_format') }}" />
                                <select id="date_format" wire:model="date_format" class="mt-1 block w-full border-secondary-300 focus:border-primary-500 focus:ring-primary-500 rounded-md shadow-xs">
                                    <option value="">{{ __('users.select_format') }}</option>
                                    @foreach($dateFormats as $format => $example)
                                        <option value="{{ $format }}">{{ $example }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('date_format')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="time_format" value="{{ __('users.time_format') }}" />
                                <select id="time_format" wire:model="time_format" class="mt-1 block w-full border-secondary-300 focus:border-primary-500 focus:ring-primary-500 rounded-md shadow-xs">
                                    <option value="">{{ __('users.select_format') }}</option>
                                    @foreach($timeFormats as $format => $example)
                                        <option value="{{ $format }}">{{ $example }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('time_format')" class="mt-2" />
                            </div>
                        </div>

                        @if(!$isCreateMode)
                            <div class="mt-4">
                                <x-primary-button>{{ __('users.save_preferences') }}</x-primary-button>
                            </div>
                        @endif
                    </form>
                </div>
                </div>
            @endif

            <!-- User Roles -->
            <div class="bg-white overflow-hidden shadow-xs sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-secondary-900 mb-4">{{ __('users.user_roles') }}</h3>

                    <form wire:submit="updateRoles">
                        <div class="space-y-2">
                            @foreach($roles as $role)
                                <label class="flex items-center">
                                    <input type="checkbox" wire:model="selectedRoles" value="{{ $role->id }}" class="rounded-smborder-secondary-300 text-primary-600 shadow-xs focus:ring-primary-500">
                                    <span class="ml-2 text-sm text-secondary-700">
                                        {{ $role->name }}
                                        @if($role->description)
                                            <span class="text-secondary-500">({{ $role->description }})</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @if(!$isCreateMode)
                            <div class="mt-4">
                                <x-primary-button>{{ __('users.update_roles') }}</x-primary-button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>

            @if($isCreateMode)
                </form>
            @endif
        </div>
    </div>

</div>
