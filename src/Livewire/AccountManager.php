<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Livewire\Concerns\InteractsWithTable;
use Base\Tenant\Models\Account;
use Base\Tenant\Services\AccountDeletionService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('base-tenant::layouts.app')]
class AccountManager extends Component
{
    // Alias porque `resetFilters()` se amplía aquí con la suscripción, y
    // `parent::` no serviría: un trait se aplana dentro de la clase.
    use InteractsWithTable {
        resetFilters as protected resetTableFilters;
    }

    public ?Account $deletingAccount = null;

    /** Uno de `active`, `trialing` o `none`; cualquier otra cosa no filtra. */
    #[Url(except: '')]
    public string $filterSubscription = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Account::class);
    }

    public function render(): View
    {
        $accounts = $this->accounts();

        return view('base-tenant::livewire.account-manager', [
            'accounts' => $accounts,
            'isEmptyTable' => $this->isEmptyResult($accounts),
            'isSystemAdmin' => Auth::user()->isSuperAdmin(),
            'hasActiveFilters' => $this->hasActiveFilters(),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'density' => $this->resolvedDensity(),
            'rowPadding' => $this->densityClasses(),
            'summary' => $this->summary(),
        ]);
    }

    /**
     * Las cifras de la cabecera. Se cuentan sobre el alcance de la pantalla, no
     * sobre la página: quien mira quiere saber cuántas cuentas alcanza, no
     * cuántas está viendo ahora mismo.
     *
     * @return array<string, int>
     */
    protected function summary(): array
    {
        $base = fn (): Builder => Account::query()->unless(
            Auth::user()->isSuperAdmin(),
            fn (Builder $query): Builder => $query->whereIn('id', Auth::user()->accounts()->pluck('accounts.id'))
        );

        return [
            'total' => $base()->count(),
            'inactive' => $base()->where('active', false)->count(),
        ];
    }

    public function confirmDelete(Account $account): void
    {
        $this->authorize('delete', $account);

        $this->deletingAccount = $account;
        $this->modal('delete-account-modal')->show();
    }

    public function deleteAccount(): void
    {
        $this->authorize('delete', $this->deletingAccount);

        if (AccountDeletionService::hasUsers($this->deletingAccount)) {
            $this->refuseDeletion(__('base-tenant::accounts.cannot_delete_with_users'));

            return;
        }

        $blocking = AccountDeletionService::blockingTables($this->deletingAccount);

        if ($blocking !== []) {
            $this->refuseDeletion(__('base-tenant::accounts.cannot_delete_with_relations', [
                'tables' => implode(', ', $blocking),
            ]));

            return;
        }

        $this->deletingAccount->delete();

        $this->modal('delete-account-modal')->close();
        $this->deletingAccount = null;

        Flux::toast(
            variant: 'success',
            heading: __('base-tenant::accounts.account_removed'),
            text: __('base-tenant::accounts.removed_successfully'),
        );
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        // `users_count` entra porque el `withCount('users')` ya estaba en la
        // consulta antes de tocarla: ordenar por él no añade ninguna subconsulta
        // que no se estuviera pagando ya.
        return ['name', 'email', 'created_at', 'users_count'];
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->filterSubscription !== '';
    }

    public function updatedFilterSubscription(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('filterSubscription');

        $this->resetTableFilters();
    }

    protected function accounts(): LengthAwarePaginator
    {
        $user = Auth::user();

        $query = Account::query()
            ->unless($user->isSuperAdmin(), function (Builder $query) use ($user): void {
                $query->whereIn('id', $user->accounts()->pluck('accounts.id'));
            })
            ->withCount('users')
            // `owner` se precarga porque ahora es una columna: sin esto la
            // tabla dispara una consulta por fila para pintar el mismo dato.
            ->with(['subscriptions', 'owner'])
            ->when($this->search, function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            });

        $this->applySubscriptionFilter($query);

        return $this->applySort($query, 'name')->paginate($this->resolvedPerPage());
    }

    /**
     * El orden importa: el scope `active()` de Cashier también recoge las
     * suscripciones en prueba, así que «activa» tiene que excluirlas a mano o
     * las dos opciones devolverían lo mismo y el filtro no separaría nada.
     */
    protected function applySubscriptionFilter(Builder $query): void
    {
        $enPrueba = fn (Builder $query) => $query->onTrial();

        match ($this->filterSubscription) {
            'trialing' => $query->whereHas('subscriptions', $enPrueba),
            'active' => $query->whereHas('subscriptions', fn (Builder $query) => $query->active())
                ->whereDoesntHave('subscriptions', $enPrueba),
            'none' => $query->whereDoesntHave('subscriptions', fn (Builder $query) => $query->active()),
            default => null,
        };
    }

    protected function refuseDeletion(string $reason): void
    {
        Flux::toast(
            variant: 'danger',
            heading: __('base-tenant::accounts.error_deleting_account'),
            text: $reason,
        );

        $this->modal('delete-account-modal')->close();
    }
}
