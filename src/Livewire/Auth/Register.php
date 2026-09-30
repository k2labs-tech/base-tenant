<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Auth;

use Base\Tenant\Facades\Presale as PresaleFacade;
use Base\Tenant\Livewire\Attributes\GuestLayout;
use Base\Tenant\Models\User;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Services\InvitationService;
use Base\Tenant\Support\Home;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LogicException;

#[GuestLayout]
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
            $this->redirect(Home::routeOrHome('base-tenant.home'), navigate: false);
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.tenant_user_model()],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        // The person, their account and their owner role are one thing: a user
        // left without an account — because the account or the role failed —
        // could sign in and land on nothing, and could not register again
        // with the same address.
        $user = DB::transaction(function () use ($validated): User {
            $user = tenant_user_model()::create(Arr::only($validated, ['name', 'email', 'password']));

            $account = $user->createPrimaryAccountAndSetRole($validated['companyName']);

            // `addRole()` answers false instead of failing when the role is not
            // there (roles never synced, or renamed). The account would then
            // belong to somebody who cannot manage it.
            if ($user->rolesForAccount($account)->isEmpty()) {
                throw new LogicException(
                    'The owner role could not be assigned to the new account. Run `php artisan k2labs-base:sync-roles`.'
                );
            }

            return $user;
        });

        // After the commit, so a listener that sends the verification email
        // never writes to somebody whose registration was rolled back.
        event(new Registered($user));

        Auth::login($user);

        $this->redirect($this->destinationAfterRegistration(), navigate: false);
    }

    /**
     * The checkout when the installation sells plans through the package, and
     * home otherwise. With subscriptions switched off the checkout route does
     * not exist, and naming it failed the registration after the user and
     * their account had been created.
     */
    protected function destinationAfterRegistration(): string
    {
        if (config('base-tenant.subscription.enabled', true) && Route::has('base-tenant.checkout')) {
            return route('base-tenant.checkout');
        }

        return Home::url();
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'in:'.mb_strtolower($invite->email), 'unique:'.tenant_user_model()],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = tenant_user_model()::create($validated)));

        Auth::login($user);

        session()->flash('status', __('base-tenant::invitations.accepted'));

        $this->redirect(Home::url(), navigate: false);
    }

    public function render()
    {
        return view('base-tenant::livewire.auth.register', [
            'invited' => $this->invited,
            'invitedTo' => $this->invitedTo,
        ]);
    }
}
