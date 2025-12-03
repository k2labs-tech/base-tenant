<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Preferences extends Component
{
    public string $locale = '';
    public string $currency = '';
    public int $decimal_places = 2;
    public string $decimals_separator = '.';
    public string $thousands_separator = ',';
    public string $date_format = 'Y-m-d';
    public string $time_format = 'H:i:s';
    public string $timezone = '';
    public ?string $daily_notification_summary = '';

    /**
     * Available locales
     */
    #[Computed]
    public function locales(): array
    {
        return [
            'en' => 'English',
            'es' => 'Español',
        ];
    }

    /**
     * Available currencies
     */
    #[Computed]
    public function currencies(): array
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

    /**
     * Available timezones
     */
    #[Computed]
    public function timezones(): array
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

    /**
     * Available separators
     */
    #[Computed]
    public function separators(): array
    {
        return [
            '.' => '. (Period)',
            ',' => ', (Comma)',
            ' ' => '(Space)',
        ];
    }

    /**
     * Available date formats
     */
    #[Computed]
    public function dateFormats(): array
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

    /**
     * Available time formats
     */
    #[Computed]
    public function timeFormats(): array
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
        $this->daily_notification_summary = $user->daily_notification_summary === null
            ? ''
            : (string) (int) $user->daily_notification_summary;
    }

    /**
     * Update user preferences.
     */
    public function updatePreferences(): void
    {
        $validated = $this->validate([
            'locale' => ['required', 'string', 'in:en,es'],
            'currency' => ['required', 'string', 'in:' . implode(',', array_keys($this->currencies()))],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:4'],
            'decimals_separator' => ['required', 'string', Rule::in(array_keys($this->separators()))],
            'thousands_separator' => ['required', 'string', Rule::in(array_keys($this->separators()))],
            'date_format' => ['required', 'string', 'in:' . implode(',', array_keys($this->dateFormats()))],
            'time_format' => ['required', 'string', 'in:' . implode(',', array_keys($this->timeFormats()))],
            'timezone' => ['required', 'string', 'timezone'],
            'daily_notification_summary' => ['nullable', 'string', 'in:,0,1'],
        ]);

        // Convert string to proper boolean/null
        $dailySummary = $validated['daily_notification_summary'] === ''
            ? null
            : (bool) $validated['daily_notification_summary'];

        $validated['daily_notification_summary'] = $dailySummary;

        Auth::user()->update($validated);

        // Update the session locale immediately
        session(['locale' => $validated['locale']]);
        app()->setLocale($validated['locale']);

        $this->dispatch('preferences-updated');
    }

    public function render()
    {
        return view('base-tenant::livewire.preferences');
    }
}
