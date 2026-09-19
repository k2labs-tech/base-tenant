<?php

declare(strict_types=1);

namespace Base\Tenant\Exceptions;

use RuntimeException;

/**
 * A refusal that reaches the customer, so every message is translated: these
 * surface in the domain screen, not in a log.
 */
class DomainException extends RuntimeException
{
    public static function subdomainReserved(string $subdomain): self
    {
        return new self(__('base-tenant::domains.errors.reserved', ['subdomain' => $subdomain]));
    }

    public static function subdomainTaken(string $subdomain): self
    {
        return new self(__('base-tenant::domains.errors.subdomain_taken', ['subdomain' => $subdomain]));
    }

    public static function subdomainInvalid(): self
    {
        return new self(__('base-tenant::domains.errors.subdomain_invalid', [
            'min' => (int) config('base-tenant.domains.subdomains.min_length', 3),
            'max' => (int) config('base-tenant.domains.subdomains.max_length', 63),
        ]));
    }

    public static function hostnameInvalid(string $hostname): self
    {
        return new self(__('base-tenant::domains.errors.hostname_invalid', ['hostname' => $hostname]));
    }

    public static function hostnameTaken(string $hostname): self
    {
        return new self(__('base-tenant::domains.errors.hostname_taken', ['hostname' => $hostname]));
    }

    /**
     * Refusing a customer's own central domain matters: pointing
     * `app.tuproducto.com` at an account would shadow the product's own front
     * door for everybody.
     */
    public static function hostnameIsCentral(string $hostname): self
    {
        return new self(__('base-tenant::domains.errors.hostname_is_central', ['hostname' => $hostname]));
    }

    public static function tooManyDomains(int $max): self
    {
        return new self(__('base-tenant::domains.errors.too_many', ['max' => $max]));
    }

    public static function customDomainsDisabled(): self
    {
        return new self(__('base-tenant::domains.errors.custom_disabled'));
    }

    public static function subdomainsDisabled(): self
    {
        return new self(__('base-tenant::domains.errors.subdomains_disabled'));
    }
}
