<section>
    <header>
        <h2 class="text-lg font-medium text-primary-900">
            {{ __('base-tenant::app.profile.preferences') }}
        </h2>

        <p class="mt-1 text-sm text-primary-600">
            {{ __('base-tenant::app.profile.preferences_description') }}
        </p>
    </header>

    <form wire:submit="updatePreferences" class="mt-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Language -->
            <div>
                <x-input-label for="locale" :value="__('base-tenant::app.profile.language')" />
                <select wire:model="locale" id="locale" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200">
                    @foreach($this->locales as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('locale')" class="mt-2" />
            </div>

            <!-- Currency -->
            <div>
                <x-input-label for="currency" :value="__('base-tenant::app.profile.currency')" />
                <select wire:model="currency" id="currency" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200">
                    @foreach($this->currencies as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('currency')" class="mt-2" />
            </div>

            <!-- Decimal Places -->
            <div>
                <x-input-label for="decimal_places" :value="__('base-tenant::app.profile.decimal_places')" />
                <select wire:model="decimal_places" id="decimal_places" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200">
                    @for($i = 0; $i <= 4; $i++)
                        <option value="{{ $i }}">{{ $i }}</option>
                    @endfor
                </select>
                <x-input-error :messages="$errors->get('decimal_places')" class="mt-2" />
            </div>

            <!-- Timezone -->
            <div>
                <x-input-label for="timezone" :value="__('base-tenant::app.profile.timezone')" />
                <select wire:model="timezone" id="timezone" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200">
                    @foreach($this->timezones as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
            </div>

            <!-- Decimal Separator -->
            <div>
                <x-input-label for="decimals_separator" :value="__('base-tenant::app.profile.decimals_separator')" />
                <select wire:model="decimals_separator" id="decimals_separator" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200">
                    @foreach($this->separators as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('decimals_separator')" class="mt-2" />
            </div>

            <!-- Thousands Separator -->
            <div>
                <x-input-label for="thousands_separator" :value="__('base-tenant::app.profile.thousands_separator')" />
                <select wire:model="thousands_separator" id="thousands_separator" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200">
                    @foreach($this->separators as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('thousands_separator')" class="mt-2" />
            </div>

            <!-- Date Format -->
            <div>
                <x-input-label for="date_format" :value="__('base-tenant::app.profile.date_format')" />
                <select wire:model="date_format" id="date_format" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200">
                    @foreach($this->dateFormats as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('date_format')" class="mt-2" />
            </div>

            <!-- Time Format -->
            <div>
                <x-input-label for="time_format" :value="__('base-tenant::app.profile.time_format')" />
                <select wire:model="time_format" id="time_format" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200">
                    @foreach($this->timeFormats as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('time_format')" class="mt-2" />
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('base-tenant::app.profile.save') }}</x-primary-button>
        </div>
    </form>
</section>
