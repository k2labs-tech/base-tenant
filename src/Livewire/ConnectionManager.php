<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Connections\ConnectionManager as Connections;
use Base\Tenant\Livewire\Concerns\InteractsWithTable;
use Base\Tenant\Models\AccountConnection;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The external services this account is connected to, and whether they still
 * work.
 *
 * The catalogue is bounded by what the product declares as connectors, so this
 * screen does not paginate.
 */
#[Layout('base-tenant::layouts.app')]
class ConnectionManager extends Component
{
    use InteractsWithTable {
        resetFilters as protected resetTableFilters;
    }

    /** `healthy`, `failing`, `unknown`, or empty for all. */
    #[Url(except: '')]
    public string $filterStatus = '';

    public bool $showDeleteModal = false;

    public string $deletingId = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasPermission('connections.manage'), 403);
    }

    public function render(): View
    {
        $connections = $this->connections();

        return view('base-tenant::livewire.connection-manager', [
            'connections' => $connections,
            'isEmptyTable' => $connections->isEmpty(),
            'hasActiveFilters' => $this->hasActiveFilters(),
            'summary' => $this->summary(),
            'density' => $this->resolvedDensity(),
            'rowPadding' => $this->densityClasses(),
        ]);
    }

    /** @return array<int, string> */
    protected function sortableColumns(): array
    {
        return ['provider', 'label', 'checked_at'];
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

    public function check(string $id, Connections $connections): void
    {
        $this->authorizeManaging();

        $connection = AccountConnection::query()->find($id);

        if (! $connection) {
            return;
        }

        $connections->check($connection);

        Flux::toast(text: __('base-tenant::connections.checked'), variant: 'success');
    }

    public function confirmDelete(string $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function deleting(): ?AccountConnection
    {
        return $this->deletingId === '' ? null : AccountConnection::query()->find($this->deletingId);
    }

    public function disconnect(): void
    {
        $this->authorizeManaging();

        $connection = $this->deleting();

        if (! $connection) {
            return;
        }

        $connection->delete();

        $this->reset(['deletingId', 'showDeleteModal']);

        Flux::toast(text: __('base-tenant::connections.disconnected'), variant: 'success');
    }

    /** @return array<string, int> */
    protected function summary(): array
    {
        return [
            'total' => AccountConnection::query()->count(),
            'failing' => AccountConnection::query()->where('status', AccountConnection::FAILING)->count(),
        ];
    }

    /**
     * @return Collection<int, AccountConnection>
     */
    protected function connections(): Collection
    {
        $search = mb_strtolower(trim($this->search));

        $rows = AccountConnection::query()->get()
            ->when($search !== '', fn (Collection $rows): Collection => $rows->filter(
                fn (AccountConnection $c): bool => str_contains(mb_strtolower($c->provider), $search)
                    || str_contains(mb_strtolower($c->label), $search)
            ))
            ->when($this->filterStatus !== '', fn (Collection $rows): Collection => $rows->filter(
                fn (AccountConnection $c): bool => $c->status === $this->filterStatus
            ))
            ->values();

        return $this->applySortToCollection($rows, 'provider');
    }

    protected function authorizeManaging(): void
    {
        abort_unless(Auth::user()->hasPermission('connections.manage'), 403);
    }
}
