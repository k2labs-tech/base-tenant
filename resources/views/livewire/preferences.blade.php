<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
//use WireUi\Traits\WireUiActions;

new class extends Component
{
//    use WireUiActions;

    public string $locale = '';
    public string $currency = '';
    public int $decimal_places = 2;
    public string $decimals_separator = '.';
    public string $thousands_separator = ',';
    public string $date_format = 'Y-m-d';
    public string $time_format = 'H:i:s';
    public string $timezone = '';

    /**
     * Available options for dropdowns
     */
    public function getLocalesProperty(): array
    {
        return [
            'en' => 'English',
            'es' => 'Español',
        ];
    }

    public function getCurrenciesProperty(): array
    {
        return [
            'USD' => 'USD - US Dollar',
            'EUR' => 'EUR - Euro',
            'GBP' => 'GBP - British Pound',
            'JPY' => 'JPY - Japanese Yen',
            'CAD' => 'CAD - Canadian Dollar',
            'AUD' => 'AUD - Australian Dollar',
            'CHF' => 'CHF - Swiss Franc',
            'CNY' => 'CNY - Chinese Yuan',
            'MXN' => 'MXN - Mexican Peso',
            'BRL' => 'BRL - Brazilian Real',
        ];
    }

    public function getTimezonesProperty(): array
    {
        return [
            'UTC' => 'UTC',
            'America/New_York' => 'Eastern Time (US & Canada)',
            'America/Chicago' => 'Central Time (US & Canada)',
            'America/Denver' => 'Mountain Time (US & Canada)',
            'America/Los_Angeles' => 'Pacific Time (US & Canada)',
            'America/Toronto' => 'Toronto',
            'America/Mexico_City' => 'Mexico City',
            'America/Sao_Paulo' => 'São Paulo',
            'Europe/London' => 'London',
            'Europe/Paris' => 'Paris',
            'Europe/Berlin' => 'Berlin',
            'Europe/Madrid' => 'Madrid',
            'Europe/Rome' => 'Rome',
            'Europe/Moscow' => 'Moscow',
            'Asia/Tokyo' => 'Tokyo',
            'Asia/Shanghai' => 'Shanghai',
            'Asia/Hong_Kong' => 'Hong Kong',
            'Asia/Singapore' => 'Singapore',
            'Asia/Dubai' => 'Dubai',
            'Australia/Sydney' => 'Sydney',
            'Pacific/Auckland' => 'Auckland',
        ];
    }

    public function getSeparatorsProperty(): array
    {
        return [
            '.' => '. (Period)',
            ',' => ', (Comma)',
            ' ' => '(Space)',
        ];
    }

    public function getDateFormatsProperty(): array
    {
        return [
            'Y-m-d' => date('Y-m-d') . ' (Y-m-d)',
            'd/m/Y' => date('d/m/Y') . ' (d/m/Y)',
            'm/d/Y' => date('m/d/Y') . ' (m/d/Y)',
            'd-m-Y' => date('d-m-Y') . ' (d-m-Y)',
            'd.m.Y' => date('d.m.Y') . ' (d.m.Y)',
            'M d, Y' => date('M d, Y') . ' (M d, Y)',
            'd M Y' => date('d M Y') . ' (d M Y)',
            'F j, Y' => date('F j, Y') . ' (F j, Y)',
        ];
    }

    public function getTimeFormatsProperty(): array
    {
        return [
            'H:i:s' => date('H:i:s') . ' (24-hour with seconds)',
            'H:i' => date('H:i') . ' (24-hour)',
            'h:i:s A' => date('h:i:s A') . ' (12-hour with seconds)',
            'h:i A' => date('h:i A') . ' (12-hour)',
            'g:i A' => date('g:i A') . ' (12-hour without leading zero)',
        ];
    }

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();
        $this->locale = $user->locale ?? 'en';
        $this->currency = $user->currency ?? 'USD';
        $this->decimal_places = $user->decimal_places ?? 2;
        $this->decimals_separator = $user->decimals_separator ?? '.';
        $this->thousands_separator = $user->thousands_separator ?? ',';
        $this->date_format = $user->date_format ?? 'Y-m-d';
        $this->time_format = $user->time_format ?? 'H:i:s';
        $this->timezone = $user->timezone ?? 'UTC';
    }

    /**
     * Update user preferences.
     */
    public function updatePreferences(): void
    {
        $validated = $this->validate([
            'locale' => ['required', 'string', 'in:en,es'],
            'currency' => ['required', 'string', 'in:' . implode(',', array_keys($this->currencies))],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
            'decimals_separator' => ['required', 'string', Rule::in(array_keys($this->separators))],
            'thousands_separator' => ['required', 'string', Rule::in(array_keys($this->separators))],
            'date_format' => ['required', 'string', 'in:' . implode(',', array_keys($this->dateFormats))],
            'time_format' => ['required', 'string', 'in:' . implode(',', array_keys($this->timeFormats))],
            'timezone' => ['required', 'string', 'timezone'],
        ]);

        Auth::user()->update($validated);

        // Update the session locale immediately
        session(['locale' => $validated['locale']]);
        app()->setLocale($validated['locale']);

        $this->notification()->send([
            'icon' => 'success',
            'title' => __('app.profile.preferences_updated'),
            'description' => __('app.profile.preferences_updated_description'),
        ]);
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-primary-900">
            {{ __('app.profile.preferences') }}
        </h2>

        <p class="mt-1 text-sm text-primary-600">
            {{ __('app.profile.preferences_description') }}
        </p>
    </header>

    <form wire:submit="updatePreferences" class="mt-6 space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Language -->
            <div>
                <x-input-label for="locale" :value="__('app.profile.language')" />
                <select wire:model="locale" id="locale" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200">
                    @foreach($this->locales as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('locale')" class="mt-2" />
            </div>

            <!-- Currency -->
            <div>
                <x-input-label for="currency" :value="__('app.profile.currency')" />
                <select wire:model="currency" id="currency" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200">
                    @foreach($this->currencies as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('currency')" class="mt-2" />
            </div>

            <!-- Decimal Places -->
            <div>
                <x-input-label for="decimal_places" :value="__('app.profile.decimal_places')" />
                <select wire:model="decimal_places" id="decimal_places" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200">
                    @for($i = 0; $i <= 4; $i++)
                        <option value="{{ $i }}">{{ $i }}</option>
                    @endfor
                </select>
                <x-input-error :messages="$errors->get('decimal_places')" class="mt-2" />
            </div>

            <!-- Timezone -->
            <div>
                <x-input-label for="timezone" :value="__('app.profile.timezone')" />
                <select wire:model="timezone" id="timezone" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200">
                    @foreach($this->timezones as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
            </div>

            <!-- Decimal Separator -->
            <div>
                <x-input-label for="decimals_separator" :value="__('app.profile.decimals_separator')" />
                <select wire:model="decimals_separator" id="decimals_separator" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200">
                    @foreach($this->separators as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('decimals_separator')" class="mt-2" />
            </div>

            <!-- Thousands Separator -->
            <div>
                <x-input-label for="thousands_separator" :value="__('app.profile.thousands_separator')" />
                <select wire:model="thousands_separator" id="thousands_separator" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200">
                    @foreach($this->separators as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('thousands_separator')" class="mt-2" />
            </div>

            <!-- Date Format -->
            <div>
                <x-input-label for="date_format" :value="__('app.profile.date_format')" />
                <select wire:model="date_format" id="date_format" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200">
                    @foreach($this->dateFormats as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('date_format')" class="mt-2" />
            </div>

            <!-- Time Format -->
            <div>
                <x-input-label for="time_format" :value="__('app.profile.time_format')" />
                <select wire:model="time_format" id="time_format" class="mt-1 w-full px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-primary-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/20 focus:outline-hidden transition-all duration-200">
                    @foreach($this->timeFormats as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('time_format')" class="mt-2" />
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('app.profile.save') }}</x-primary-button>
        </div>
    </form>
</section>
