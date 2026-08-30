<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Profile;

use Base\Tenant\Social\SocialAuthException;
use Base\Tenant\Social\SocialLoginService;
use Base\Tenant\Social\SocialProviders;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Connect and disconnect providers from the profile.
 *
 * This is also the only place a provider can be attached to an existing
 * account: doing it from the login screen would let anyone who can create an
 * identity at the provider with a known address take the account over.
 */
class ConnectedAccounts extends Component
{
    public function render(): View
    {
        return view('base-tenant::livewire.profile.connected-accounts', [
            'providers' => SocialProviders::enabled(),
            'linked' => app(SocialLoginService::class)->linkedProviders(Auth::user()),
            'hasPassword' => ! empty(Auth::user()->getAttribute('password')),
        ]);
    }

    public function disconnect(string $provider, SocialLoginService $social): void
    {
        try {
            $social->unlink(Auth::user(), $provider);
        } catch (SocialAuthException $exception) {
            Flux::toast(text: $exception->getMessage(), variant: 'danger');

            return;
        }

        Flux::toast(
            text: __('base-tenant::social.unlinked', ['provider' => SocialProviders::label($provider)]),
            variant: 'success',
        );
    }
}
