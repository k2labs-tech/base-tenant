<?php

declare(strict_types=1);

namespace Base\Tenant\Social;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * Something about this identity means the sign-in cannot go ahead. Every case
 * carries a message the user can act on, because "authentication failed" tells
 * them nothing about what to do next.
 */
class SocialAuthException extends RuntimeException
{
    public static function noEmail(string $provider): self
    {
        return new self(__('base-tenant::social.errors.no_email', [
            'provider' => SocialProviders::label($provider),
        ]));
    }

    public static function emailAlreadyRegistered(string $email, string $provider): self
    {
        return new self(__('base-tenant::social.errors.email_taken', [
            'email' => $email,
            'provider' => SocialProviders::label($provider),
        ]));
    }

    public static function domainNotAllowed(string $email): self
    {
        return new self(__('base-tenant::social.errors.domain_not_allowed', [
            'domain' => Str::after($email, '@'),
        ]));
    }

    public static function alreadyLinkedElsewhere(string $provider): self
    {
        return new self(__('base-tenant::social.errors.already_linked', [
            'provider' => SocialProviders::label($provider),
        ]));
    }

    public static function wouldLockOut(string $provider): self
    {
        return new self(__('base-tenant::social.errors.would_lock_out', [
            'provider' => SocialProviders::label($provider),
        ]));
    }

    public static function notConfigured(string $provider): self
    {
        return new self(__('base-tenant::social.errors.not_configured', [
            'provider' => SocialProviders::label($provider),
        ]));
    }
}
