<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Facades\Language as LanguageFacade;
use Base\Tenant\Languages\TranslationCoverage;
use Base\Tenant\Livewire\Concerns\InteractsWithTable;
use Base\Tenant\Models\Language;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Turn languages on and off without a deploy.
 *
 * A system screen, not a tenant one: the catalogue belongs to the
 * installation, and one account switching Catalan off for everybody would be
 * an odd amount of power to hand a customer.
 */
#[Layout('base-tenant::layouts.app')]
class LanguageManager extends Component
{
    use InteractsWithTable {
        resetFilters as protected resetTableFilters;
    }

    /** `enabled`, `disabled`, or empty for everything. */
    #[Url(except: '')]
    public string $filterStatus = '';

    /** @var array<string, array{total: int, translated: int, missing: list<string>, percentage: int}>|null */
    protected ?array $coverage = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasPermission('languages.manage'), 403);
    }

    public function render(): View
    {
        $languages = $this->languages();

        return view('base-tenant::livewire.language-manager', [
            'languages' => $languages,
            'isEmptyTable' => $languages->isEmpty(),
            'hasActiveFilters' => $this->hasActiveFilters(),
            'summary' => $this->summary(),
            'coverage' => $this->coverage(),
            'reference' => config('base-tenant.languages.reference', 'en'),
            'density' => $this->resolvedDensity(),
            'rowPadding' => $this->densityClasses(),
        ]);
    }

    /** @return array<int, string> */
    protected function sortableColumns(): array
    {
        return ['name', 'code', 'position'];
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->filterStatus !== '';
    }

    public function resetFilters(): void
    {
        $this->reset('filterStatus');

        $this->resetTableFilters();
    }

    public function toggle(string $code): void
    {
        $this->authorizeManaging();

        try {
            LanguageFacade::isEnabled($code)
                ? LanguageFacade::disable($code)
                : LanguageFacade::enable($code);
        } catch (RuntimeException $exception) {
            // The one refusal that reaches the interface: the default cannot
            // be switched off. Saying so is more useful than a disabled toggle
            // nobody can explain.
            Flux::toast(text: $exception->getMessage(), variant: 'danger');

            return;
        }

        Flux::toast(text: __('base-tenant::languages.updated'), variant: 'success');
    }

    public function makeDefault(string $code): void
    {
        $this->authorizeManaging();

        LanguageFacade::setDefault($code);

        Flux::toast(text: __('base-tenant::languages.default_updated'), variant: 'success');
    }

    /** @return array<string, int> */
    protected function summary(): array
    {
        $all = LanguageFacade::all();

        return [
            'total' => $all->count(),
            'enabled' => $all->where('enabled', true)->count(),
            // Anything below the bar is enabled but showing fallback strings
            // to real users, which is worth a number on the page.
            'incomplete' => $all->where('enabled', true)->filter(
                fn (Language $language): bool => $this->coverage()[$language->code]['percentage'] < 100
            )->count(),
        ];
    }

    /**
     * @return Collection<int, Language>
     */
    protected function languages(): Collection
    {
        $search = mb_strtolower(trim($this->search));

        $rows = LanguageFacade::all()
            ->when($search !== '', fn (Collection $rows): Collection => $rows->filter(
                fn (Language $language): bool => str_contains(mb_strtolower($language->name), $search)
                    || str_contains(mb_strtolower($language->native_name), $search)
                    || str_contains(mb_strtolower($language->code), $search)
            ))
            ->when($this->filterStatus !== '', fn (Collection $rows): Collection => $rows->filter(
                fn (Language $language): bool => $language->enabled === ($this->filterStatus === 'enabled')
            ))
            ->values();

        return $this->applySortToCollection($rows, 'position');
    }

    /**
     * Coverage for every language, computed once per render: it reads every
     * translation file, and the table and the header both ask for it.
     *
     * @return array<string, array{total: int, translated: int, missing: list<string>, percentage: int}>
     */
    protected function coverage(): array
    {
        if ($this->coverage !== null) {
            return $this->coverage;
        }

        $reader = app(TranslationCoverage::class);

        return $this->coverage = LanguageFacade::all()
            ->mapWithKeys(fn (Language $language): array => [
                $language->code => $reader->for($language->code),
            ])
            ->all();
    }

    protected function authorizeManaging(): void
    {
        abort_unless(Auth::user()->hasPermission('languages.manage'), 403);
    }
}
