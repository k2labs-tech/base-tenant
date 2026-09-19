<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Auth;

use Base\Tenant\Facades\Presale as PresaleFacade;
use Base\Tenant\Models\User;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Services\InvitationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('base-tenant::layouts.guest')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $companyName = '';

    public string $password_confirmation = '';

    /**
     * Set when the person arrived from an invitation link. They are joining
     * an account, not founding one: no company to name, and the address is
     * the one the invitation was sent to.
     */
    #[Locked]
    public bool $invited = false;

    #[Locked]
    public string $invitedTo = '';

    public function mount(): void
    {
        // Durante la pre-venta el registro estándar está cerrado: dejar el
        // formulario en pie y rechazar al enviarlo desperdicia el rato que la
        // persona ha pasado rellenándolo.
        if (PresaleFacade::registrationClosed()) {
            $this->redirect(route('base-tenant.home'), navigate: false);
        }

        $invite = InvitationService::remembered();

        if ($invite !== null) {
            $this->invited = true;
            $this->invitedTo = (string) $invite->account?->name;
            $this->email = $invite->email;
        }
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        // Otra vez aquí y no solo en `mount()`: una petición POST no pasa por
        // el montaje de la pantalla, y sin esto el registro seguiría abierto
        // para cualquiera que lo llame directamente.
        abort_if(PresaleFacade::registrationClosed(), 403);

        // Read from the session again rather than trusted from the screen: the
        // invitation may have been revoked or accepted since the form loaded.
        $invite = InvitationService::remembered();

        if ($invite !== null) {
            $this->registerInvited($invite);

            return;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'companyName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        $user->createPrimaryAccountAndSetRole($validated['companyName']);

        Auth::login($user);

        $this->redirect(route('base-tenant.checkout'), navigate: false);
    }

    /**
     * Somebody invited into an existing account. No company is created and no
     * checkout follows: the account they are joining already has both. The
     * invitation itself is accepted by the login listener, which also gives
     * them the invited role inside that account.
     */
    protected function registerInvited(UserInvite $invite): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'in:'.mb_strtolower($invite->email), 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        session()->flash('status', __('base-tenant::invitations.accepted'));

        $this->redirect(route('base-tenant.dashboard'), navigate: false);
    }

    public function render()
    {
        return view('base-tenant::livewire.auth.register', [
            'invited' => $this->invited,
            'invitedTo' => $this->invitedTo,
        ]);
    }
}
