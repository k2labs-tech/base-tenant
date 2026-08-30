<?php

declare(strict_types=1);

namespace Base\Tenant\Social;

use Base\Tenant\Models\SocialAccount;
use Base\Tenant\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * What happens when someone comes back from Google, LinkedIn or Microsoft.
 *
 * Four outcomes, and which one applies is decided here rather than in the
 * controller, so the rule that matters most is written down once.
 */
class SocialLoginService
{
    /**
     * Sign in, or register, whoever just came back from the provider.
     *
     * @throws SocialAuthException when the identity cannot be used
     */
    public function authenticate(string $provider, SocialiteUser $identity): User
    {
        $existing = $this->findLink($provider, $identity->getId());

        if ($existing) {
            $this->refreshTokens($existing, $identity);

            return $existing->user;
        }

        $email = $identity->getEmail();

        if ($email === null) {
            throw SocialAuthException::noEmail($provider);
        }

        $claimant = $this->userByEmail($email);

        if ($claimant) {
            // The rule that matters. This address already belongs to somebody
            // who signs in with a password, and nobody has said the two are
            // the same person. Linking here would mean anyone who can create
            // an account at the provider with that address takes over this
            // one -- and at several providers, proving the address is not
            // required. Linking has to start from a session that is already
            // authenticated, which is what the profile screen is for.
            throw SocialAuthException::emailAlreadyRegistered($email, $provider);
        }

        if (! SocialProviders::allowsEmail($email)) {
            throw SocialAuthException::domainNotAllowed($email);
        }

        return $this->register($provider, $identity, $email);
    }

    /**
     * Attach a provider identity to the user who is already signed in.
     *
     * @throws SocialAuthException when the identity belongs to somebody else
     */
    public function link(Authenticatable&Model $user, string $provider, SocialiteUser $identity): SocialAccount
    {
        $existing = $this->findLink($provider, $identity->getId());

        if ($existing && $existing->user_id !== $user->getKey()) {
            throw SocialAuthException::alreadyLinkedElsewhere($provider);
        }

        if ($existing) {
            $this->refreshTokens($existing, $identity);

            return $existing;
        }

        return SocialAccount::create([
            'user_id' => $user->getKey(),
            'provider' => $provider,
            'provider_id' => $identity->getId(),
            'avatar' => $identity->getAvatar(),
            'token' => $identity->token ?? null,
            'refresh_token' => $identity->refreshToken ?? null,
            'expires_at' => isset($identity->expiresIn) ? now()->addSeconds((int) $identity->expiresIn) : null,
        ]);
    }

    /**
     * Detach a provider, unless it is the last way in.
     *
     * @throws SocialAuthException when it would lock the user out
     */
    public function unlink(Authenticatable&Model $user, string $provider): void
    {
        $links = SocialAccount::where('user_id', $user->getKey())->get();

        $target = $links->firstWhere('provider', $provider);

        if (! $target) {
            return;
        }

        // Removing the last provider from someone who never set a password
        // would leave an account nobody can open -- not even by resetting the
        // password, because a reset needs a password to reset.
        $hasPassword = ! empty($user->getAttribute('password'));

        if (! $hasPassword && $links->count() === 1) {
            throw SocialAuthException::wouldLockOut($provider);
        }

        $target->delete();
    }

    /**
     * @return list<string>
     */
    public function linkedProviders(Authenticatable&Model $user): array
    {
        return SocialAccount::where('user_id', $user->getKey())
            ->pluck('provider')
            ->all();
    }

    protected function register(string $provider, SocialiteUser $identity, string $email): User
    {
        $model = config('base-tenant.models.user', User::class);

        return DB::transaction(function () use ($model, $provider, $identity, $email): User {
            $user = $model::create([
                'name' => $identity->getName() ?: $identity->getNickname() ?: Str::before($email, '@'),
                'email' => $email,

                // No password at all rather than a random one. A random
                // password is a credential nobody knows and everybody has to
                // reason about; an empty one makes "this user signs in with
                // Google" a fact the code can read.
                'password' => null,

                // The provider has already proven the address. Asking the user
                // to prove it again by email would be asking them to do work
                // that has been done.
                'email_verified_at' => now(),
            ]);

            event(new Registered($user));

            // The same primary account and role the standard registration
            // creates, so a social sign-up is not a second kind of user.
            $user->createPrimaryAccountAndSetRole(
                $identity->getName() ?: Str::before($email, '@')
            );

            SocialAccount::create([
                'user_id' => $user->getKey(),
                'provider' => $provider,
                'provider_id' => $identity->getId(),
                'avatar' => $identity->getAvatar(),
                'token' => $identity->token ?? null,
                'refresh_token' => $identity->refreshToken ?? null,
                'expires_at' => isset($identity->expiresIn) ? now()->addSeconds((int) $identity->expiresIn) : null,
            ]);

            return $user;
        });
    }

    protected function findLink(string $provider, string $providerId): ?SocialAccount
    {
        return SocialAccount::with('user')
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();
    }

    protected function userByEmail(string $email): ?User
    {
        $model = config('base-tenant.models.user', User::class);

        return $model::where('email', $email)->first();
    }

    /**
     * Keep the stored grant current.
     *
     * A refresh token is only sent on the first consent at most providers, so
     * it is overwritten only when a new one actually arrives.
     */
    protected function refreshTokens(SocialAccount $link, SocialiteUser $identity): void
    {
        $link->fill([
            'avatar' => $identity->getAvatar() ?: $link->avatar,
            'token' => $identity->token ?? $link->token,
            'expires_at' => isset($identity->expiresIn)
                ? now()->addSeconds((int) $identity->expiresIn)
                : $link->expires_at,
        ]);

        if (! empty($identity->refreshToken)) {
            $link->refresh_token = $identity->refreshToken;
        }

        $link->save();
    }
}
