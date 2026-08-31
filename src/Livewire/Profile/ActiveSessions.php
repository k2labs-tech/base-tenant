<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Profile;

use Base\Tenant\Facades\Sessions;
use Base\Tenant\Models\User;
use Base\Tenant\Models\UserSession;
use Base\Tenant\Support\Module;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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
    public ?string $userId = null;

    public function mount(?User $user = null): void
    {
        Module::ensure(Module::SECURITY);

        $this->userId = $user?->getKey();

        if ($this->isSomebodyElse()) {
            abort_unless(Auth::user()->hasPermission('users.update'), 403);
        }
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
        $this->authorizeAction();

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
        $this->authorizeAction();

        $count = $this->isSomebodyElse()
            ? Sessions::revokeAll($this->subject())
            : Sessions::revokeOthers($this->subject());

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

    protected function subject(): User
    {
        if ($this->userId === null) {
            return Auth::user();
        }

        $model = config('base-tenant.models.user', User::class);

        return $model::query()->findOrFail($this->userId);
    }

    protected function isSomebodyElse(): bool
    {
        return $this->userId !== null && $this->userId !== Auth::id();
    }

    /**
     * Re-checked on every action and not only on mount: a Livewire component's
     * public state travels with the request, so a permission proved once at
     * mount is not a permission proved now.
     */
    protected function authorizeAction(): void
    {
        if ($this->isSomebodyElse()) {
            abort_unless(Auth::user()->hasPermission('users.update'), 403);
        }
    }
}
