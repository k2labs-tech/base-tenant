<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Facades\Settings;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Settings\SettingsSchema;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Editor for the account's typed settings.
 *
 * The form is built from the registered schema classes: a declared property
 * type decides the control, so adding a setting means adding a property, not
 * touching this screen.
 */
#[Layout('base-tenant::layouts.app')]
class AccountSettings extends Component
{
    public string $group = '';

    /** @var array<string, mixed> */
    public array $values = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->hasPermission('settings.view'), 403);

        $this->group = $this->schemas()->keys()->first() ?? '';

        $this->loadValues();
    }

    public function render(): View
    {
        return view('base-tenant::livewire.account-settings', [
            'schemas' => $this->schemas(),
            'fields' => $this->fields(),
            'account' => Tenant::current(),
            'canEdit' => Auth::user()->hasPermission('settings.update'),
        ]);
    }

    public function selectGroup(string $group): void
    {
        $this->group = $group;

        $this->loadValues();
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasPermission('settings.update'), 403);

        $account = Tenant::current();
        $schema = $this->schemaClass();

        if (! $account || ! $schema) {
            return;
        }

        Settings::for($account)->put($schema, $this->normalised());

        $this->loadValues();

        $this->toast(__('base-tenant::settings.updated'));
    }

    /**
     * Registered schemas keyed by their group.
     *
     * @return Collection<string, class-string<SettingsSchema>>
     */
    protected function schemas(): Collection
    {
        return collect(Settings::schemas())
            ->filter(fn (string $schema): bool => is_subclass_of($schema, SettingsSchema::class))
            ->keyBy(fn (string $schema): string => $schema::group());
    }

    /** @return class-string<SettingsSchema>|null */
    protected function schemaClass(): ?string
    {
        return $this->schemas()->get($this->group);
    }

    /** @return array<int, array{name: string, type: string, value: mixed}> */
    protected function fields(): array
    {
        $account = Tenant::current();
        $schema = $this->schemaClass();

        if (! $account || ! $schema) {
            return [];
        }

        return Settings::for($account)->get($schema)->fields();
    }

    protected function loadValues(): void
    {
        $this->values = collect($this->fields())
            ->mapWithKeys(fn (array $field): array => [
                $field['name'] => $field['type'] === 'json'
                    ? json_encode($field['value'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : $field['value'],
            ])
            ->all();
    }

    /**
     * JSON fields come back from a textarea as text and have to be decoded
     * before the schema casts them.
     *
     * @return array<string, mixed>
     */
    protected function normalised(): array
    {
        $values = $this->values;

        foreach ($this->fields() as $field) {
            if ($field['type'] !== 'json') {
                continue;
            }

            $decoded = json_decode((string) ($values[$field['name']] ?? ''), true);

            $values[$field['name']] = is_array($decoded) ? $decoded : [];
        }

        return $values;
    }

    protected function toast(string $heading): void
    {
        // Flux takes the message as its first argument; a toast with only a
        // heading throws.
        Flux::toast(text: $heading, variant: 'success');
    }
}
