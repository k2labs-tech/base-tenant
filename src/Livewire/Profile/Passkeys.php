<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Profile;

use Base\Tenant\Facades\Passkey as PasskeyFacade;
use Base\Tenant\Models\Passkey;
use Base\Tenant\Support\Module;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The passkeys this user has registered.
 *
 * Registration itself is a browser ceremony and happens over the JSON
 * endpoints; this screen owns the list, the naming and the removal.
 */
class Passkeys extends Component
{
    public function mount(): void
    {
        Module::ensure(Module::PASSWORDLESS);
    }

    public function render(): View
    {
        return view('base-tenant::livewire.profile.passkeys', [
            'passkeys' => $this->passkeys(),
            'enabled' => PasskeyFacade::enabled(),
            'hasTotp' => Auth::user()->hasTwoFactorEnabled(),
        ]);
    }

    public function rename(int $id, string $name): void
    {
        $name = trim($name);

        if ($name === '') {
            return;
        }

        $this->find($id)->forceFill(['name' => mb_substr($name, 0, 60)])->save();

        Flux::toast(text: __('base-tenant::passkeys.renamed'), variant: 'success');
    }

    public function remove(int $id): void
    {
        $this->find($id)->delete();

        Flux::toast(text: __('base-tenant::passkeys.removed'), variant: 'success');
    }

    /**
     * @return Collection<int, Passkey>
     */
    protected function passkeys(): Collection
    {
        return Passkey::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Scoped to the signed-in user by hand. `Passkey` carries no global scope
     * -- it belongs to a person, not to an account -- so this query is the
     * boundary that stops one user acting on another's credential.
     */
    protected function find(int $id): Passkey
    {
        return Passkey::query()
            ->where('user_id', Auth::id())
            ->findOrFail($id);
    }
}
