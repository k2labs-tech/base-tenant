<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Livewire\Concerns\InteractsWithTable;
use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;
use Base\Tenant\Support\Search;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('base-tenant::layouts.app')]
class UserManager extends Component
{
    // El trait se aplica con alias porque `resetFilters()` se amplía aquí con
    // el rol, y `parent::` no serviría: un trait se aplana dentro de la clase.
    use InteractsWithTable {
        resetFilters as protected resetTableFilters;
    }

    public ?User $deletingUser = null;

    /**
     * A diferencia de `sortBy`, el rol no lleva lista blanca, y es a propósito:
     * va como valor enlazado a un `where`, así que no hay inyección, y filtrar
     * por un rol que no existe no devuelve a nadie. Lo único que se filtra es
     * que ese nombre de rol existe, y los nombres ya se ven en el editor de
     * usuarios. Antes de "arreglarlo", que conste que se miró.
     */
    #[Url(except: '')]
    public string $filterRole = '';

    public function mount(): void
    {
        $this->authorize('viewAny', $this->userModel());
    }

    public function render(): View
    {
        $users = $this->users();

        return view('base-tenant::livewire.user-manager', [
            'users' => $users,
            'isEmptyTable' => $this->isEmptyResult($users),
            'roles' => Role::query()->assignable()->orderBy('display_name')->get(),
            'isSystemAdmin' => Auth::user()->isSuperAdmin(),
            'canEdit' => Auth::user()->can('create', $this->userModel()),
            'hasActiveFilters' => $this->hasActiveFilters(),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'density' => $this->resolvedDensity(),
            'rowPadding' => $this->densityClasses(),
            'summary' => $this->summary(),
        ]);
    }

    /**
     * El modelo que la aplicación tiene configurado, no el del paquete.
     *
     * Las relaciones polimórficas guardan el nombre de la clase: los roles de
     * un usuario se escriben con el `model_type` del modelo configurado, así
     * que consultar la clase del paquete devuelve un morph distinto y la
     * relación no casa. Las políticas se registran contra la misma clase
     * configurada, de modo que autorizar contra otra tampoco encuentra la suya.
     *
     * @return class-string<User>
     */
    protected function userModel(): string
    {
        return config('base-tenant.models.user', User::class);
    }

    /**
     * Las cifras de la tira de contexto. Se calculan sobre el alcance de la
     * pantalla, no sobre la página: quien mira quiere saber cuántos hay, no
     * cuántos está viendo ahora mismo.
     *
     * @return array<string, int>
     */
    protected function summary(): array
    {
        $base = fn () => $this->userModel()::query()->unless(
            Auth::user()->isSuperAdmin(),
            fn (Builder $query): Builder => $query->inAccount(Tenant::current())
        );

        return [
            'total' => $base()->count(),
            'unverified' => $base()->whereNull('email_verified_at')->count(),
        ];
    }

    /** Decide cuál de los dos vacíos se muestra y si se ofrece limpiar. */
    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->filterRole !== '';
    }

    public function updatedFilterRole(): void
    {
        $this->resetPage();
    }

    /** El rol es un filtro más: `resetFilters()` del trait no lo conoce. */
    public function resetFilters(): void
    {
        $this->reset('filterRole');

        $this->resetTableFilters();
    }

    public function confirmDelete(User $user): void
    {
        $this->authorize('delete', $user);

        $this->deletingUser = $user;
        $this->modal('delete-user-modal')->show();
    }

    public function deleteUser(): void
    {
        $this->authorize('delete', $this->deletingUser);

        $this->deletingUser->delete();

        $this->modal('delete-user-modal')->close();
        $this->deletingUser = null;

        Flux::toast(
            variant: 'success',
            heading: __('base-tenant::users.user_removed'),
            text: __('base-tenant::users.removed_successfully'),
        );
    }

    public function impersonate(string $userId): mixed
    {
        $target = User::findOrFail($userId);

        $this->authorize('impersonate', $target);

        Auth::user()->impersonate($target);

        Flux::toast(
            variant: 'success',
            heading: __('base-tenant::users.impersonating'),
            text: __('base-tenant::users.impersonating_as', ['name' => $target->name]),
        );

        return redirect()->route('base-tenant.dashboard');
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'email', 'created_at'];
    }

    protected function users(): LengthAwarePaginator
    {
        $user = Auth::user();

        $query = $this->userModel()::query()
            ->unless(
                $user->isSuperAdmin(),
                fn (Builder $query): Builder => $query->inAccount(Tenant::current())
            )
            ->with(['roles', 'account'])
            ->when($this->search, function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('name', Search::operator(), "%{$this->search}%")
                        ->orWhere('email', Search::operator(), "%{$this->search}%");
                });
            })
            ->when($this->filterRole !== '', function (Builder $query): void {
                $query->whereHas('roles', function (Builder $query): void {
                    $query->where('name', $this->filterRole);
                });
            });

        return $this->applySort($query, 'name')->paginate($this->resolvedPerPage());
    }
}
