<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Profile;

use Base\Tenant\Facades\Sessions;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\User;
use Base\Tenant\Models\UserSession;
use Base\Tenant\Support\Module;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Where this user has a session open, and the button that ends one.
 *
 * Serves two callers with one component. Without `:user` it is the signed-in
 * person looking at their own sessions; with it, an administrator looking at a
 * member's, which needs `users.update` and is the answer to "somebody left the
 * company this morning".
 */
class ActiveSessions extends Component
{
    /**
     * Locked, and it has to be: a public property travels with every request,
     * and one the browser could rewrite would let anybody point this screen at
     * any user in the installation.
     */
    #[Locked]
    public ?string $userId = null;

    protected ?User $resolvedSubject = null;

    public function mount(?User $user = null): void
    {
        Module::ensure(Module::SECURITY);

        $this->userId = $user?->getKey();

        $this->subject();
    }

    public function render(): View
    {
        return view('base-tenant::livewire.profile.active-sessions', [
            'sessions' => $this->sessions(),
            'isSelf' => ! $this->isSomebodyElse(),
        ]);
    }

    public function revoke(int $id): void
    {
        $session = UserSession::query()
            ->where('user_id', $this->subject()->getKey())
            ->findOrFail($id);

        Sessions::revoke($session);

        Flux::toast(text: __('base-tenant::sessions.revoked'), variant: 'success');
    }

    /**
     * End every session but this one. For an administrator looking at somebody
     * else, there is no "this one" to keep, so every session goes.
     */
    public function revokeOthers(): void
    {
        $subject = $this->subject();

        $count = $this->isSomebodyElse()
            ? Sessions::revokeAll($subject)
            : Sessions::revokeOthers($subject);

        Flux::toast(
            text: trans_choice('base-tenant::sessions.revoked_many', $count, ['count' => $count]),
            variant: 'success',
        );
    }

    /**
     * @return Collection<int, UserSession>
     */
    protected function sessions(): Collection
    {
        return Sessions::forUser($this->subject());
    }

    /**
     * Whose sessions are on screen, authorised on every call and not only on
     * mount: a permission proved once is not a permission proved now, and the
     * account in context may have changed since.
     *
     * Looking at somebody else needs `users.update` in the current account and
     * the other person has to be a member of it. Without the second check an
     * administrator of one account could end the sessions of users in every
     * other account of the installation.
     */
    protected function subject(): User
    {
        if (! $this->isSomebodyElse()) {
            return Auth::user();
        }

        return $this->resolvedSubject ??= $this->authorizedSubject();
    }

    protected function authorizedSubject(): User
    {
        $viewer = Auth::user();

        abort_unless($viewer->hasPermission('users.update'), 403);

        $model = config('base-tenant.models.user', User::class);

        /** @var User $subject */
        $subject = $model::query()->findOrFail($this->userId);

        if ($viewer->isSuperAdmin()) {
            return $subject;
        }

        abort_unless($subject->belongsToAccount(Tenant::current()), 403);

        return $subject;
    }

    protected function isSomebodyElse(): bool
    {
        return $this->userId !== null && $this->userId !== Auth::id();
    }
}
