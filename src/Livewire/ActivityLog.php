<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Livewire\Concerns\InteractsWithTable;
use Base\Tenant\Models\ActivityLog as ActivityLogModel;
use Base\Tenant\Models\User;
use Base\Tenant\Support\Search;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('base-tenant::layouts.app')]
class ActivityLog extends Component
{
    // Alias porque `resetFilters()` se amplía aquí con cuatro filtros más, y
    // `parent::` no serviría: un trait se aplana dentro de la clase.
    use InteractsWithTable {
        resetFilters as protected resetTableFilters;
    }

    #[Url(except: '')]
    public string $filterAction = '';

    #[Url(except: '')]
    public string $filterUser = '';

    /** Fechas `Y-m-d` tal como las escribe un `input[type=date]`. */
    #[Url(except: '')]
    public string $filterFrom = '';

    #[Url(except: '')]
    public string $filterUntil = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasPermission('activity.view'), 403);
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['created_at', 'action'];
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->filterAction !== ''
            || $this->filterUser !== ''
            || $this->filterFrom !== ''
            || $this->filterUntil !== '';
    }

    public function updatedFilterAction(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUser(): void
    {
        $this->resetPage();
    }

    public function updatedFilterFrom(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUntil(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['filterAction', 'filterUser', 'filterFrom', 'filterUntil']);

        $this->resetTableFilters();
    }

    public function render(): View
    {
        $activities = $this->activities();

        return view('base-tenant::livewire.activity-log', [
            'activities' => $activities,
            'isEmptyTable' => $this->isEmptyResult($activities),
            'hasActiveFilters' => $this->hasActiveFilters(),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'actions' => $this->baseQuery()->distinct()->pluck('action'),
            'causers' => $this->causers(),
            'activeFilterCount' => $this->activeFilterCount(),
            // Sin orden pedido manda el de por defecto, que aquí es
            // descendente: la cabecera tiene que pintar esa flecha y no la de
            // `sortDirection`, que sigue en su 'asc' inicial.
            'effectiveDirection' => $this->sortBy === null ? 'desc' : $this->sortDirection,
            'density' => $this->resolvedDensity(),
            'rowPadding' => $this->densityClasses(),
            'summary' => $this->summary(),
        ]);
    }

    /**
     * El recuento de la cabecera: cuántas entradas alcanza quien mira, sin
     * filtros. Repite el alcance de `baseQuery()` a propósito, porque esa lleva
     * los filtros puestos y el recuento tiene que ser el del total.
     *
     * @return array<string, int>
     */
    protected function summary(): array
    {
        return [
            'total' => ActivityLogModel::query()
                ->when(Auth::user()->isSuperAdmin(), fn (Builder $query): Builder => $query->acrossAccounts())
                ->count(),
        ];
    }

    /** Cuántos filtros hay puestos, para el contador del desplegable. */
    protected function activeFilterCount(): int
    {
        return count(array_filter([
            $this->filterAction,
            $this->filterUser,
            $this->filterFrom,
            $this->filterUntil,
        ], fn (string $filter): bool => $filter !== ''));
    }

    /**
     * Un registro de actividad se lee de lo más nuevo a lo más viejo, así que
     * el orden por defecto es descendente y no el ascendente del trait.
     */
    protected function activities(): LengthAwarePaginator
    {
        $query = $this->baseQuery()->with(['causer', 'subject']);

        return $this->applySort($query, 'created_at', 'desc')->paginate($this->resolvedPerPage());
    }

    /**
     * Quienes aparecen como autores, para el desplegable. Sale de la consulta
     * sin filtrar por usuario: si saliera de la página visible, filtrar por
     * alguien lo dejaría como única opción y no se podría volver atrás.
     *
     * @return Collection<int, User>
     */
    protected function causers(): Collection
    {
        $ids = ActivityLogModel::query()
            ->when(Auth::user()->isSuperAdmin(), fn (Builder $query): Builder => $query->acrossAccounts())
            ->whereNotNull('causer_id')
            ->distinct()
            ->pluck('causer_id');

        return config('base-tenant.models.user', User::class)::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }

    /**
     * The account scope is applied by the model's global scope; superadmins
     * opt out of it explicitly.
     */
    /**
     * Los filtros llegan de la petición, y una columna de fecha comparada con
     * algo que no lo es da error en PostgreSQL en vez de lista vacía.
     */
    protected function esFecha(string $valor): bool
    {
        if ($valor === '') {
            return false;
        }

        try {
            Carbon::parse($valor);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    protected function baseQuery(): Builder
    {
        return ActivityLogModel::query()
            ->when(Auth::user()->isSuperAdmin(), fn (Builder $query): Builder => $query->acrossAccounts())
            ->when($this->filterAction, fn (Builder $query): Builder => $query->where('action', $this->filterAction))
            // Sólo si el valor puede ser una clave: comparar una columna uuid
            // con cualquier otra cosa es un error en PostgreSQL, no una lista
            // vacía, y el valor viene de la petición.
            ->when(
                Str::isUuid($this->filterUser),
                fn (Builder $query): Builder => $query->where('causer_id', $this->filterUser)
            )
            ->when(
                $this->esFecha($this->filterFrom),
                fn (Builder $query): Builder => $query->where('created_at', '>=', $this->filterFrom.' 00:00:00')
            )
            // Hasta el final del día: con `<= 'Y-m-d'` una entrada de esa tarde
            // se quedaría fuera y parecería que ese día no pasó nada.
            ->when(
                $this->esFecha($this->filterUntil),
                fn (Builder $query): Builder => $query->where('created_at', '<=', $this->filterUntil.' 23:59:59')
            )
            ->when($this->search, function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('description', Search::operator(), "%{$this->search}%")
                        ->orWhere('action', Search::operator(), "%{$this->search}%");
                });
            });
    }
}
