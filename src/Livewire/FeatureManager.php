<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Facades\Feature as FeatureFacade;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Livewire\Concerns\InteractsWithTable;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\Feature;
use Base\Tenant\Services\FeatureService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * What an account is entitled to, and where each answer comes from.
 *
 * The plan is the baseline; a row in the `features` table overrides it for one
 * account only, optionally until a date. That is how trials, pilots and
 * grandfathered customers are handled without inventing a plan for each one.
 */
#[Layout('base-tenant::layouts.app')]
class FeatureManager extends Component
{
    // Alias porque `resetFilters()` se amplía aquí con el ámbito, y `parent::`
    // no serviría: un trait se aplana dentro de la clase.
    use InteractsWithTable {
        resetFilters as protected resetTableFilters;
    }

    /** `global` para lo que dicta el plan, `account` para lo sobrescrito. */
    #[Url(except: '')]
    public string $filterScope = '';

    public ?string $accountId = null;

    public string $editing = '';

    public string $value = '';

    public string $expiresAt = '';

    /**
     * Memoria de un solo pintado: la tabla y las cifras de la cabecera piden lo
     * mismo, y Livewire construye el componente de nuevo en cada petición, así
     * que esto no sobrevive a nada que pudiera quedarse rancio entre peticiones.
     *
     * @var Collection<int, array<string, mixed>>|null
     */
    protected ?Collection $catalogue = null;

    /** @var Collection<string, Feature>|null */
    protected ?Collection $memoizedOverrides = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasPermission('features.view'), 403);

        $this->accountId = Tenant::currentId();
    }

    public function render(): View
    {
        $features = $this->features();

        return view('base-tenant::livewire.feature-manager', [
            'features' => $features,
            'isEmptyTable' => $features->isEmpty(),
            'hasActiveFilters' => $this->hasActiveFilters(),
            'account' => $this->account(),
            'accounts' => $this->selectableAccounts(),
            'planName' => $this->account() ? FeatureService::getAccountPlanName($this->account()) : null,
            'canEdit' => Auth::user()->hasPermission('features.update'),
            'density' => $this->resolvedDensity(),
            'rowPadding' => $this->densityClasses(),
            'summary' => $this->summary(),

            // La columna de caducidad solo aparece si alguna fila tiene algo
            // que poner en ella: una columna entera de guiones ocupa ancho y
            // no informa de nada.
            'showsExpiry' => $features->contains(
                fn (array $feature): bool => $feature['expires_at'] !== null || $feature['overridden']
            ),
        ]);
    }

    /**
     * Las cifras de la cabecera, contadas sobre el catálogo entero y no sobre
     * lo que dejan ver los filtros.
     *
     * @return array<string, int>
     */
    protected function summary(): array
    {
        $catalogue = $this->catalogue();

        return [
            'total' => $catalogue->count(),
            'overridden' => $catalogue->where('overridden', true)->count(),
            // Una sobrescritura con fecha se apaga sola: llegado el día la
            // cuenta vuelve a lo que diga el plan sin que nadie toque nada.
            'expiring' => $catalogue->filter(
                fn (array $row): bool => $row['overridden'] && $row['expires_at'] !== null
            )->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['key'];
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->filterScope !== '';
    }

    public function updatedFilterScope(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('filterScope');

        $this->resetTableFilters();
    }

    public function selectAccount(string $accountId): void
    {
        abort_unless(Auth::user()->isSuperAdmin(), 403);

        $this->accountId = $accountId;
        $this->forgetCatalogue();
        $this->cancel();
    }

    public function edit(string $key): void
    {
        $this->authorizeEditing();

        $override = $this->overrideFor($key);

        $this->editing = $key;
        $this->value = $this->asInput($override?->casted_value ?? $this->planValue($key));
        $this->expiresAt = $override?->expires_at?->format('Y-m-d') ?? '';
    }

    public function cancel(): void
    {
        $this->reset(['editing', 'value', 'expiresAt']);
    }

    public function save(): void
    {
        $this->authorizeEditing();

        $account = $this->account();

        if (! $account) {
            return;
        }

        $this->validate([
            'value' => ['required', 'string', 'max:255'],
            'expiresAt' => ['nullable', 'date', 'after:today'],
        ]);

        FeatureFacade::for($account)->set(
            $this->editing,
            $this->parse($this->value, $this->planValue($this->editing)),
            $this->expiresAt ? now()->parse($this->expiresAt)->endOfDay() : null
        );

        $this->forgetCatalogue();
        $this->cancel();

        $this->toast(__('base-tenant::features.updated'));
    }

    /**
     * Turn a boolean feature on or off in one click, without opening the form.
     */
    public function toggle(string $key): void
    {
        $this->authorizeEditing();

        $account = $this->account();

        if (! $account) {
            return;
        }

        FeatureFacade::for($account)->set($key, ! FeatureFacade::for($account)->active($key));

        $this->forgetCatalogue();

        $this->toast(__('base-tenant::features.updated'));
    }

    /**
     * Drop the override so the account falls back to whatever its plan says.
     */
    public function resetToPlan(string $key): void
    {
        $this->authorizeEditing();

        $account = $this->account();

        if (! $account) {
            return;
        }

        FeatureFacade::for($account)->forget($key);

        $this->forgetCatalogue();
        $this->cancel();

        $this->toast(__('base-tenant::features.reset_to_plan'));
    }

    /**
     * El catálogo ya filtrado y ordenado, que es lo que pinta la tabla.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function features(): Collection
    {
        return $this->applySortToCollection($this->filterRows($this->catalogue()), 'key');
    }

    /**
     * Every feature the plan defines, plus any override the account carries for
     * a feature the plan does not mention.
     *
     * Se memoriza porque lo piden dos: la tabla y las cifras de la cabecera.
     * Sin memoria, cada pintado consultaría las sobrescrituras dos veces para
     * responder lo mismo.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function catalogue(): Collection
    {
        if ($this->catalogue instanceof Collection) {
            return $this->catalogue;
        }

        $account = $this->account();

        if (! $account) {
            return $this->catalogue = new Collection;
        }

        $plan = FeatureService::planFeatures($account);
        $overrides = $this->overrides();

        return $this->catalogue = collect(array_keys([...$plan, ...$overrides->all()]))
            ->values()
            ->map(function (string $key) use ($plan, $overrides): array {
                $override = $overrides->get($key);
                $effective = $override?->casted_value ?? ($plan[$key] ?? null);

                return [
                    'key' => $key,
                    'plan_value' => $plan[$key] ?? null,
                    'in_plan' => array_key_exists($key, $plan),
                    'overridden' => $override !== null,
                    'expires_at' => $override?->expires_at,
                    'effective' => $effective,
                    'is_boolean' => is_bool($effective) || is_bool($plan[$key] ?? null),
                    'name' => $this->nameFor($key),
                    'scope' => $override !== null ? 'account' : 'global',
                ];
            });
    }

    /**
     * La búsqueda mira clave y nombre traducido, y el ámbito separa lo que
     * dicta el plan de lo que se sobrescribió para esta cuenta.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    protected function filterRows(Collection $rows): Collection
    {
        $search = mb_strtolower(trim($this->search));

        return $rows
            ->when($search !== '', fn (Collection $rows): Collection => $rows->filter(
                fn (array $row): bool => str_contains(mb_strtolower($row['key']), $search)
                    || str_contains(mb_strtolower($row['name']), $search)
            ))
            ->when($this->filterScope !== '', fn (Collection $rows): Collection => $rows->filter(
                fn (array $row): bool => $row['scope'] === $this->filterScope
            ))
            ->values();
    }

    /**
     * El nombre traducido, o la propia clave cuando no hay traducción: la clave
     * construida no la puede comprobar `TranslationKeysTest`, así que el
     * respaldo tiene que estar aquí y no depender de que exista.
     */
    protected function nameFor(string $key): string
    {
        $translationKey = 'base-tenant::features.names.'.$key;
        $translated = __($translationKey);

        return is_string($translated) && $translated !== $translationKey ? $translated : $key;
    }

    /** @return Collection<string, Feature> */
    protected function overrides(): Collection
    {
        return $this->memoizedOverrides ??= Feature::query()
            ->where('account_id', $this->accountId)
            ->active()
            ->get()
            ->keyBy('key');
    }

    /**
     * Cualquier escritura invalida la memoria: el pintado que sigue a guardar
     * ocurre en la misma petición, y servirle el catálogo de antes enseñaría el
     * valor viejo justo después de cambiarlo.
     */
    protected function forgetCatalogue(): void
    {
        $this->catalogue = null;
        $this->memoizedOverrides = null;
    }

    protected function overrideFor(string $key): ?Feature
    {
        return $this->overrides()->get($key);
    }

    protected function planValue(string $key): mixed
    {
        return FeatureService::planValue($this->account(), $key);
    }

    protected function account(): ?Account
    {
        return $this->accountId ? Account::find($this->accountId) : null;
    }

    /** @return Collection<int, Account> */
    protected function selectableAccounts(): Collection
    {
        return Auth::user()->isSuperAdmin()
            ? Account::query()->orderBy('name')->get()
            : new Collection;
    }

    /**
     * Keep the type the plan uses, so a numeric allowance does not silently
     * become the string "10".
     */
    protected function parse(string $input, mixed $planValue): mixed
    {
        if (is_bool($planValue) || in_array(strtolower($input), ['true', 'false'], true)) {
            return filter_var($input, FILTER_VALIDATE_BOOLEAN);
        }

        if (is_int($planValue) || preg_match('/^-?\d+$/', $input)) {
            return (int) $input;
        }

        return $input;
    }

    protected function asInput(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => '',
            default => (string) $value,
        };
    }

    protected function authorizeEditing(): void
    {
        abort_unless(Auth::user()->hasPermission('features.update'), 403);
    }

    protected function toast(string $heading): void
    {
        // Flux takes the message as its first argument; a toast with only a
        // heading throws.
        Flux::toast(text: $heading, variant: 'success');
    }
}
