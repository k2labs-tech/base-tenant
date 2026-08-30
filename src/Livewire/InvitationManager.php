<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Livewire\Concerns\InteractsWithTable;
use Base\Tenant\Models\Role;
use Base\Tenant\Models\User;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Services\InvitationService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('base-tenant::layouts.app')]
class InvitationManager extends Component
{
    // Alias porque `resetFilters()` se amplía aquí con el estado, y `parent::`
    // no serviría: un trait se aplana dentro de la clase.
    use InteractsWithTable {
        resetFilters as protected resetTableFilters;
    }

    /** Uno de `pending`, `expired` o `accepted`; otra cosa no filtra. */
    #[Url(except: '')]
    public string $filterStatus = '';

    public string $email = '';

    public string $selectedRole = '';

    public function mount(): void
    {
        $this->authorize('viewAny', UserInvite::class);
    }

    public function sendInvite(): void
    {
        $this->authorize('create', UserInvite::class);

        $this->validate([
            'email' => ['required', 'email'],
            'selectedRole' => ['required', 'exists:roles,id'],
        ]);

        $account = Tenant::current();

        if (! $account) {
            $this->addError('email', __('base-tenant::invitations.no_account'));

            return;
        }

        $alreadyMember = User::query()
            ->where('email', $this->email)
            ->inAccount($account)
            ->exists();

        if ($alreadyMember) {
            $this->addError('email', __('base-tenant::invitations.user_already_member'));

            return;
        }

        InvitationService::send($this->email, $account->getKey(), $this->selectedRole, auth()->user());

        $this->reset(['email', 'selectedRole']);

        Flux::toast(
            variant: 'success',
            heading: __('base-tenant::invitations.sent_title'),
            text: __('base-tenant::invitations.sent_text'),
        );
    }

    public function resendInvite(string $inviteId): void
    {
        $invite = UserInvite::findOrFail($inviteId);

        $this->authorize('resend', $invite);

        InvitationService::resend($invite);

        Flux::toast(
            variant: 'success',
            heading: __('base-tenant::invitations.resent_title'),
            text: __('base-tenant::invitations.resent_text'),
        );
    }

    public function revokeInvite(string $inviteId): void
    {
        $invite = UserInvite::findOrFail($inviteId);

        $this->authorize('delete', $invite);

        InvitationService::revoke($invite);

        Flux::toast(
            variant: 'success',
            heading: __('base-tenant::invitations.revoked_title'),
            text: __('base-tenant::invitations.revoked_text'),
        );
    }

    public function render(): View
    {
        $invites = $this->invites();

        return view('base-tenant::livewire.invitation-manager', [
            'invites' => $invites,
            'isEmptyTable' => $this->isEmptyResult($invites),
            'hasActiveFilters' => $this->hasActiveFilters(),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'roles' => Role::query()->assignable()->nonSystem()->orderBy('display_name')->get(),
            'density' => $this->resolvedDensity(),
            'rowPadding' => $this->densityClasses(),
            'summary' => $this->summary(),
        ]);
    }

    /**
     * Las cifras de la cabecera, contadas sobre el alcance de la pantalla y no
     * sobre la página. El alcance de cuenta lo pone el scope global del modelo,
     * igual que en el listado.
     *
     * @return array<string, int>
     */
    protected function summary(): array
    {
        return [
            'total' => UserInvite::query()->count(),
            'expired' => UserInvite::query()->expired()->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['email', 'expires_at', 'created_at'];
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->filterStatus !== '';
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('filterStatus');

        $this->resetTableFilters();
    }

    /**
     * Antes solo se listaban las pendientes. Ahora entran las tres, porque un
     * filtro de estado cuyo listado base ya excluye dos de sus tres opciones no
     * filtra nada, y el estado escondido no se vería en la URL.
     */
    protected function invites(): LengthAwarePaginator
    {
        $query = UserInvite::query()
            ->with(['role', 'invitedBy'])
            ->when($this->search, function (Builder $query): void {
                $query->where('email', 'like', "%{$this->search}%");
            });

        match ($this->filterStatus) {
            'pending' => $query->pending(),
            'expired' => $query->expired(),
            'accepted' => $query->accepted(),
            default => null,
        };

        return $this->applySort($query, 'created_at')->paginate($this->resolvedPerPage());
    }
}
