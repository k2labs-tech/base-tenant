<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Facades\Meter;
use Base\Tenant\Livewire\Concerns\InteractsWithTable;
use Base\Tenant\Metering\Metric;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * What this account has consumed, against what its plan includes.
 *
 * Read only on purpose. A meter that could be edited from the interface would
 * be a number the customer is billed on with a text box next to it.
 */
#[Layout('base-tenant::layouts.app')]
class UsageManager extends Component
{
    use InteractsWithTable {
        resetFilters as protected resetTableFilters;
    }

    /** `capped` for metrics the plan limits, `uncapped` for the rest. */
    #[Url(except: '')]
    public string $filterScope = '';

    /**
     * One render's memory: the table and the header figures ask the same
     * question, and each one would otherwise read every counter again.
     *
     * @var Collection<int, array<string, mixed>>|null
     */
    protected ?Collection $readings = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasPermission('usage.view'), 403);
    }

    public function render(): View
    {
        $metrics = $this->filtered();

        return view('base-tenant::livewire.usage-manager', [
            'metrics' => $metrics,
            'isEmptyTable' => $metrics->isEmpty(),
            'hasActiveFilters' => $this->hasActiveFilters(),
            'summary' => $this->summary(),
            'density' => $this->resolvedDensity(),
            'rowPadding' => $this->densityClasses(),

            // A column of dashes takes width and says nothing: the remaining
            // column only appears when something is actually capped.
            'showsRemaining' => $metrics->contains(fn (array $row): bool => $row['limit'] !== -1),
        ]);
    }

    /** @return array<int, string> */
    protected function sortableColumns(): array
    {
        return ['metric', 'usage'];
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->filterScope !== '';
    }

    public function resetFilters(): void
    {
        $this->reset('filterScope');

        $this->resetTableFilters();
    }

    /**
     * The header figures, counted over every metric rather than over what the
     * filters leave visible.
     *
     * @return array<string, int>
     */
    protected function summary(): array
    {
        $readings = $this->readings();

        return [
            'total' => $readings->count(),
            'at_limit' => $readings->filter(
                fn (array $row): bool => $row['percentage'] !== null && $row['percentage'] >= 100
            )->count(),
            'approaching' => $readings->filter(
                fn (array $row): bool => $row['percentage'] !== null
                    && $row['percentage'] >= 80
                    && $row['percentage'] < 100
            )->count(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function filtered(): Collection
    {
        $search = mb_strtolower(trim($this->search));

        $rows = $this->readings()
            ->when($search !== '', fn (Collection $rows): Collection => $rows->filter(
                fn (array $row): bool => str_contains(mb_strtolower($row['key']), $search)
                    || str_contains(mb_strtolower($row['name']), $search)
            ))
            ->when($this->filterScope !== '', fn (Collection $rows): Collection => $rows->filter(
                fn (array $row): bool => $this->filterScope === 'capped'
                    ? $row['limit'] !== -1
                    : $row['limit'] === -1
            ))
            ->values();

        return $this->applySortToCollection($rows, 'metric');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function readings(): Collection
    {
        if ($this->readings instanceof Collection) {
            return $this->readings;
        }

        return $this->readings = collect(Meter::summary())->map(function (array $reading): array {
            /** @var Metric $metric */
            $metric = $reading['metric'];

            return [
                'key' => $metric->key,
                // La columna se llama `metric` para ordenar y `name` para
                // buscar: se ordena por lo que se ve, que es el nombre.
                'metric' => $this->nameFor($metric),
                'name' => $this->nameFor($metric),
                'usage' => $reading['value'],
                'value' => $reading['value'],
                'limit' => $reading['limit'],
                'remaining' => $reading['remaining'],
                'percentage' => $reading['percentage'],
                'period' => __('base-tenant::metering.period.'.$metric->reset),
                'is_gauge' => $metric->isGauge(),
            ];
        });
    }

    /**
     * The translated name, or the key itself when there is no translation. A
     * key built at runtime is invisible to TranslationKeysTest, so the
     * fallback has to live here rather than depend on the entry existing.
     */
    protected function nameFor(Metric $metric): string
    {
        $key = $metric->label ?: 'base-tenant::metering.metrics.'.$metric->key;
        $translated = __($key);

        return is_string($translated) && $translated !== $key ? $translated : $metric->key;
    }
}
