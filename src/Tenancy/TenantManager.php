<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy;

use Base\Tenant\Models\Account;
use Base\Tenant\Tenancy\Contracts\TenantResolver;
use Base\Tenant\Tenancy\Events\TenantChanged;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Single source of truth for the currently active account.
 *
 * The session is only one of the drivers used to resolve the tenant, never
 * the place where the tenant lives. Anything that needs to know the current
 * account -- queries, jobs, cache, policies -- reads it from here.
 */
class TenantManager
{
    /**
     * Team id used for role assignments that live outside any account, such as
     * staff users who administer the whole platform.
     */
    public const SYSTEM_TEAM_ID = '00000000-0000-0000-0000-000000000000';

    protected ?Account $current = null;

    protected bool $resolved = false;

    /** @var array<int, Closure> */
    protected array $changeListeners = [];

    public function __construct(protected Container $container) {}

    /**
     * The account currently in context, resolving it on first access.
     */
    public function current(): ?Account
    {
        if (! $this->resolved) {
            $this->resolve();
        }

        return $this->current;
    }

    public function currentId(): ?string
    {
        return $this->current()?->getKey();
    }

    public function check(): bool
    {
        return $this->current() !== null;
    }

    /**
     * Put an account in context.
     *
     * @param  bool  $remember  Persist the choice in the session for later requests.
     */
    public function set(Account|string|null $account, bool $remember = false): ?Account
    {
        $account = $this->toAccount($account);

        $changed = $this->current?->getKey() !== $account?->getKey();

        $this->current = $account;
        $this->resolved = true;

        if ($remember) {
            $this->remember($account);
        }

        if ($changed) {
            $this->notifyChange($account);
        }

        return $account;
    }

    public function forget(bool $alsoForgetSession = false): void
    {
        $this->current = null;
        $this->resolved = true;

        if ($alsoForgetSession && $this->hasSession()) {
            $this->container['session']->forget('current_account_id');
        }

        $this->notifyChange(null);
    }

    /**
     * Reset the manager so the next access runs the resolver chain again.
     */
    public function reset(): void
    {
        $this->current = null;
        $this->resolved = false;
    }

    /**
     * Run the resolver chain and put the result in context.
     */
    public function resolve(): ?Account
    {
        $this->resolved = true;

        $request = $this->container->bound('request')
            ? $this->container->make('request')
            : Request::create('/');

        foreach ($this->resolvers() as $resolver) {
            $account = $resolver->resolve($request);

            if ($account) {
                return $this->set($account);
            }
        }

        return null;
    }

    /**
     * Execute the callback with the given account in context, restoring the
     * previous one afterwards even if the callback throws.
     */
    public function runFor(Account|string $account, Closure $callback): mixed
    {
        $previous = $this->current;
        $wasResolved = $this->resolved;

        $this->set($account);

        try {
            return $callback($this->current);
        } finally {
            $this->current = $previous;
            $this->resolved = $wasResolved;
            $this->notifyChange($previous);
        }
    }

    /**
     * Execute the callback with no account in context.
     */
    public function runWithout(Closure $callback): mixed
    {
        $previous = $this->current;
        $wasResolved = $this->resolved;

        $this->current = null;
        $this->resolved = true;
        $this->notifyChange(null);

        try {
            return $callback();
        } finally {
            $this->current = $previous;
            $this->resolved = $wasResolved;
            $this->notifyChange($previous);
        }
    }

    /**
     * Run the callback once per account, each with its own context. Meant for
     * scheduled commands that need to sweep every tenant.
     */
    public function eachAccount(Closure $callback, int $chunkSize = 100): void
    {
        Account::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $accounts) use ($callback): void {
                foreach ($accounts as $account) {
                    $this->runFor($account, $callback);
                }
            });
    }

    /**
     * Register a listener fired whenever the active account changes. Used
     * internally to keep the permission team id and cache prefix in sync.
     */
    public function onChange(Closure $callback): void
    {
        $this->changeListeners[] = $callback;
    }

    /**
     * Prefix a cache key with the active tenant so two accounts never share
     * an entry.
     */
    public function cacheKey(string $key): string
    {
        $id = $this->currentId();

        return $id === null ? $key : "tenant:{$id}:{$key}";
    }

    /**
     * Name a broadcast channel within the active tenant.
     */
    public function channel(string $name): string
    {
        $id = $this->currentId();

        return $id === null ? $name : "tenant.{$id}.{$name}";
    }

    /** @return Collection<int, TenantResolver> */
    protected function resolvers(): Collection
    {
        return collect(config('base-tenant.tenancy.resolvers', []))
            ->map(fn (string $class): TenantResolver => $this->container->make($class));
    }

    protected function toAccount(Account|string|null $account): ?Account
    {
        if ($account === null || $account instanceof Account) {
            return $account;
        }

        $model = config('base-tenant.models.account', Account::class);

        return $model::find($account);
    }

    protected function remember(?Account $account): void
    {
        if (! $this->hasSession()) {
            return;
        }

        $account === null
            ? $this->container['session']->forget('current_account_id')
            : $this->container['session']->put('current_account_id', $account->getKey());
    }

    protected function hasSession(): bool
    {
        return $this->container->bound('session')
            && $this->container['session']->isStarted();
    }

    protected function notifyChange(?Account $account): void
    {
        foreach ($this->changeListeners as $listener) {
            $listener($account);
        }

        if ($this->container->bound('events')) {
            $this->container['events']->dispatch(new TenantChanged($account));
        }
    }
}
