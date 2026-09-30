<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy;

use Base\Tenant\Models\Account;
use Base\Tenant\Tenancy\Contracts\TenantResolver;
use Base\Tenant\Tenancy\Events\TenancyBypassed;
use Base\Tenant\Tenancy\Events\TenantChanged;
use Base\Tenant\Tenancy\Exceptions\UnknownTenantException;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use WeakMap;

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

    /**
     * How many `runWithout()` frames are open. A counter and not a flag so an
     * inner frame closing cannot switch the bypass off for the outer one.
     */
    protected int $bypassDepth = 0;

    /** @var array<int, Closure> */
    protected array $changeListeners = [];

    /**
     * Context captured for jobs whose dispatch outlives the block that built
     * them. `runFor($a, fn () => Job::dispatch())` returns a PendingDispatch
     * that only pushes when it is destroyed -- after the block has already
     * restored the outer context. TenantAwareBusDispatcher puts this state
     * back around the real push. Weak, so a job that is never dispatched does
     * not stay pinned in memory.
     *
     * @var WeakMap<object, array{current: Account|null, resolved: bool, bypass: int}>
     */
    protected WeakMap $deferredDispatchStates;

    public function __construct(protected Container $container)
    {
        $this->deferredDispatchStates = new WeakMap;
    }

    /**
     * Whether strict tenancy (`base-tenant.tenancy.strict`) is on.
     */
    public static function strict(): bool
    {
        return (bool) config('base-tenant.tenancy.strict', false);
    }

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
     * Whether a `runWithout()` block is open, i.e. the scope was switched off
     * on purpose rather than because no account could be resolved.
     */
    public function isBypassed(): bool
    {
        return $this->bypassDepth > 0;
    }

    /**
     * Whether tenant-owned data may be touched with no account in context.
     *
     * The same answer the account scope gives for reads, so a write is allowed
     * exactly where the matching read would be unfiltered: inside
     * `runWithout()`, under `on_missing_tenant = allow`, and in console and
     * queue work under `auto`.
     */
    public function permitsUnscopedAccess(): bool
    {
        if ($this->isBypassed()) {
            return true;
        }

        return match (config('base-tenant.tenancy.on_missing_tenant', 'auto')) {
            'allow' => true,
            'deny', 'throw' => false,
            default => app()->runningInConsole(),
        };
    }

    /**
     * Put an account in context.
     *
     * Passing `null` pins "no account" for the rest of the unit of work: the
     * resolver chain does not run again until `reset()` or `clear()`. Use
     * `clear()` when the intent is to drop the account and let the next access
     * resolve a fresh one. Under strict tenancy `set(null)` behaves like
     * `clear()`, and an id that names no account throws instead of leaving
     * the context silently empty.
     *
     * @param  bool  $remember  Persist the choice in the session for later requests.
     *
     * @throws UnknownTenantException under strict tenancy, for an id with no account
     */
    public function set(Account|string|null $account, bool $remember = false): ?Account
    {
        if ($account === null && static::strict()) {
            $this->clear(alsoForgetSession: $remember);

            return null;
        }

        $id = is_string($account) ? $account : null;
        $account = $this->toAccount($account);

        if ($account === null && $id !== null && static::strict()) {
            throw UnknownTenantException::forId($id);
        }

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
     * Drop the account in context and let the next access resolve one again.
     *
     * The difference from `forget()` and `set(null)` is the resolver chain:
     * those pin "no account" until something resets the manager, so in a
     * long-lived process -- Octane, a queue worker, a test making several
     * requests -- the next request never resolves its own tenant. `clear()`
     * leaves the manager unresolved, so it does.
     *
     * @param  bool  $alsoForgetSession  Also drop the account remembered in the session.
     */
    public function clear(bool $alsoForgetSession = false): void
    {
        $changed = $this->current !== null;

        $this->current = null;
        $this->resolved = false;

        if ($alsoForgetSession && $this->hasSession()) {
            $this->container['session']->forget('current_account_id');
        }

        if ($changed) {
            $this->notifyChange(null);
        }
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
        $previousBypass = $this->bypassDepth;

        $this->set($account);
        $this->bypassDepth = 0;

        try {
            return $this->rememberDeferredDispatch($callback($this->current));
        } finally {
            $this->current = $previous;
            $this->resolved = $wasResolved;
            $this->bypassDepth = $previousBypass;
            $this->notifyChange($previous);
        }
    }

    /**
     * Execute the callback with no account in context, as an explicit bypass
     * of the account scope. Announced through TenancyBypassed so the host can
     * audit it.
     *
     * @param  string|null  $reason  Why the bypass is needed, carried on the event.
     */
    public function runWithout(Closure $callback, ?string $reason = null): mixed
    {
        $previous = $this->current;
        $wasResolved = $this->resolved;
        $previousBypass = $this->bypassDepth;

        if ($previousBypass === 0) {
            TenancyBypassed::record($previous?->getKey(), TenancyBypassed::SOURCE_RUN_WITHOUT, $reason);
        }

        $this->current = null;
        $this->resolved = true;
        $this->bypassDepth = $previousBypass + 1;
        $this->notifyChange(null);

        try {
            return $this->rememberDeferredDispatch($callback());
        } finally {
            $this->current = $previous;
            $this->resolved = $wasResolved;
            $this->bypassDepth = $previousBypass;
            $this->notifyChange($previous);
        }
    }

    /**
     * Snapshot the whole context: the account, whether it was resolved, and
     * whether a bypass is open.
     *
     * @return array{current: Account|null, resolved: bool, bypass: int}
     */
    public function captureState(): array
    {
        return [
            'current' => $this->current,
            'resolved' => $this->resolved,
            'bypass' => $this->bypassDepth,
        ];
    }

    /**
     * Run the callback under a captured state, restoring the present one
     * afterwards even if the callback throws.
     *
     * @param  array{current: Account|null, resolved: bool, bypass: int}  $state
     */
    public function runWithState(array $state, Closure $callback): mixed
    {
        $previous = $this->captureState();

        $this->restoreState($state);

        try {
            return $callback();
        } finally {
            $this->restoreState($previous);
        }
    }

    /**
     * Put a captured state back wholesale. For entry points whose unit of
     * work begins and ends at events rather than around a closure.
     *
     * @param  array{current: Account|null, resolved: bool, bypass: int}  $state
     */
    public function restoreState(array $state): void
    {
        $changed = $this->current?->getKey() !== $state['current']?->getKey();

        $this->current = $state['current'];
        $this->resolved = $state['resolved'];
        $this->bypassDepth = $state['bypass'];

        if ($changed) {
            $this->notifyChange($this->current);
        }
    }

    /**
     * Take back the state recorded for a job whose dispatch was deferred past
     * the `runFor()`/`runWithout()` block that built it, if there is one.
     *
     * @return array{current: Account|null, resolved: bool, bypass: int}|null
     */
    public function pullDeferredDispatchState(object $job): ?array
    {
        if (! isset($this->deferredDispatchStates[$job])) {
            return null;
        }

        $state = $this->deferredDispatchStates[$job];

        unset($this->deferredDispatchStates[$job]);

        return $state;
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

    /**
     * Record the context against a PendingDispatch a block returned, so the
     * job is pushed under the context it was built in. Anything else passes
     * through untouched.
     */
    protected function rememberDeferredDispatch(mixed $result): mixed
    {
        if ($result instanceof PendingDispatch && is_object($job = $result->getJob())) {
            $this->deferredDispatchStates[$job] = $this->captureState();
        }

        return $result;
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
