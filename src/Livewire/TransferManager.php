<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Livewire\Concerns\InteractsWithTable;
use Base\Tenant\Models\DataTransfer;
use Base\Tenant\Support\Search;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every import and export this account has run.
 *
 * Polls while anything is still moving and stops when nothing is: a screen
 * that keeps asking after the work finished is a screen that costs a request
 * every two seconds for as long as the tab is open.
 */
#[Layout('base-tenant::layouts.app')]
class TransferManager extends Component
{
    use InteractsWithTable {
        resetFilters as protected resetTableFilters;
    }
    use WithPagination;

    /** `import`, `export`, or empty for both. */
    #[Url(except: '')]
    public string $filterType = '';

    #[Url(except: '')]
    public string $filterStatus = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasPermission('transfers.view'), 403);
    }

    public function render(): View
    {
        $transfers = $this->transfers();

        return view('base-tenant::livewire.transfer-manager', [
            'transfers' => $transfers,
            'isEmptyTable' => $this->isEmptyResult($transfers),
            'hasActiveFilters' => $this->hasActiveFilters(),
            'summary' => $this->summary(),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'density' => $this->resolvedDensity(),
            'rowPadding' => $this->densityClasses(),

            // Only while something is actually moving.
            'polling' => $this->summary()['running'] > 0,
        ]);
    }

    /** @return array<int, string> */
    protected function sortableColumns(): array
    {
        return ['name', 'status', 'created_at'];
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->filterType !== '' || $this->filterStatus !== '';
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['filterType', 'filterStatus']);

        $this->resetTableFilters();
    }

    /** @return array<string, int> */
    protected function summary(): array
    {
        return [
            'total' => DataTransfer::query()->count(),
            'running' => DataTransfer::query()
                ->whereIn('status', [DataTransfer::PENDING, DataTransfer::PROCESSING])
                ->count(),
            'failed' => DataTransfer::query()->where('status', DataTransfer::FAILED)->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, DataTransfer>
     */
    protected function transfers(): LengthAwarePaginator
    {
        $query = DataTransfer::query()
            ->with(['file', 'errorFile', 'creator'])
            ->when($this->search !== '', fn (Builder $query) => $query->where(
                'name',
                Search::operator(),
                '%'.trim($this->search).'%'
            ))
            ->when($this->filterType !== '', fn (Builder $query) => $query->where('type', $this->filterType))
            ->when($this->filterStatus !== '', fn (Builder $query) => $query->where('status', $this->filterStatus));

        return $this->applySort($query, 'created_at', 'desc')
            ->paginate($this->resolvedPerPage());
    }
}
