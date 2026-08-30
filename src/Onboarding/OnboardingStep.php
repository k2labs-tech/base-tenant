<?php

declare(strict_types=1);

namespace Base\Tenant\Onboarding;

use Base\Tenant\Models\Account;

/**
 * One thing an account is asked to do before it is properly set up.
 */
final class OnboardingStep
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $route = null,
        public readonly ?string $check = null,
        public readonly ?string $description = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $key, array $config): self
    {
        return new self(
            key: $key,
            label: $config['label'] ?? $key,
            route: $config['route'] ?? null,
            check: $config['completed'] ?? null,
            description: $config['description'] ?? null,
        );
    }

    /**
     * Has this account done it?
     *
     * A step with no check is never complete on its own: it is a link the user
     * ticks off by doing the thing, and claiming otherwise would hide work
     * that has not happened.
     */
    public function isComplete(Account $account): bool
    {
        if ($this->check === null || ! class_exists($this->check)) {
            return false;
        }

        return (bool) app($this->check)($account);
    }

    public function url(): ?string
    {
        return $this->route !== null && app('router')->has($this->route)
            ? route($this->route)
            : null;
    }
}
