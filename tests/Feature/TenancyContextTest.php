<?php

declare(strict_types=1);

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Tenancy\Exceptions\UnknownTenantException;
use Base\Tenant\Tenancy\QueueTenancy;
use Base\Tenant\Tenancy\TenantAwareBusDispatcher;
use Base\Tenant\Tests\Fixtures\RecordTenantJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
 * The set(null) trap: clearing the context marks the manager as resolved, so
 * in a long-lived process the next request never runs the resolver chain.
 */

beforeEach(function () {
    Route::get('/_tenancy/drop', fn (): string => (string) Tenant::set(null)?->getKey());
    Route::get('/_tenancy/clear', function (): string {
        Tenant::clear();

        return '';
    });
    Route::get('/_tenancy/current', fn (): string => (string) Tenant::currentId());
});

test('set(null) leaves the next request with no context (3.0 behaviour, reproduced)', function () {
    $account = $this->createAccount();
    $user = $this->createUser($account);

    $this->get('/_tenancy/drop')->assertOk();

    // Same application instance, as under Octane or in a test making several
    // requests: the resolver chain does not run again for this user.
    $this->actingAs($user)->get('/_tenancy/current')->assertContent('');
});

test('clear() lets the next request resolve its own account', function () {
    $account = $this->createAccount();
    $user = $this->createUser($account);

    $this->get('/_tenancy/clear')->assertOk();

    $this->actingAs($user)->get('/_tenancy/current')->assertContent($account->getKey());
});

test('under strict tenancy set(null) clears instead of pinning an empty context', function () {
    config(['base-tenant.tenancy.strict' => true]);

    $account = $this->createAccount();
    $user = $this->createUser($account);

    $this->get('/_tenancy/drop')->assertOk();

    $this->actingAs($user)->get('/_tenancy/current')->assertContent($account->getKey());
});

test('clear() can also drop the account remembered in the session', function () {
    $account = $this->createAccount();

    $this->startSession();
    Tenant::set($account, remember: true);

    expect(session('current_account_id'))->toBe($account->getKey());

    Tenant::clear(alsoForgetSession: true);

    expect(session('current_account_id'))->toBeNull();
});

test('forget() still pins no account, as documented', function () {
    $account = $this->createAccount();
    $this->actingAs($this->createUser($account));

    Tenant::forget();

    expect(Tenant::currentId())->toBeNull();
});

/*
 * Tenant::set() with an id that names no account.
 */

test('by default set() with an unknown id leaves the context empty', function () {
    expect(Tenant::set((string) Str::uuid()))->toBeNull()
        ->and(Tenant::currentId())->toBeNull();
});

test('under strict tenancy set() with an unknown id throws', function () {
    config(['base-tenant.tenancy.strict' => true]);

    Tenant::set((string) Str::uuid());
})->throws(UnknownTenantException::class);

test('under strict tenancy runFor() with an unknown id throws and leaves the context alone', function () {
    config(['base-tenant.tenancy.strict' => true]);

    $account = $this->createAccount();
    Tenant::set($account);

    expect(fn () => Tenant::runFor((string) Str::uuid(), fn () => null))->toThrow(UnknownTenantException::class)
        ->and(Tenant::currentId())->toBe($account->getKey());
});

/*
 * Deferred dispatches.
 */

function payloadAccountId(): ?string
{
    $payload = json_decode((string) DB::table('jobs')->value('payload'), true);

    return $payload[QueueTenancy::PAYLOAD_KEY] ?? null;
}

test('by default a dispatch returned from runFor is stamped with the outer account (3.0 behaviour)', function () {
    config(['queue.default' => 'database']);

    $outer = $this->createAccount();
    $inner = $this->createAccount();

    Tenant::set($outer);

    Tenant::runFor($inner, fn () => RecordTenantJob::dispatch());

    expect(payloadAccountId())->toBe($outer->getKey());
});

test('the tenant-aware dispatcher stamps a returned dispatch with the block\'s account', function () {
    config(['queue.default' => 'database']);
    TenantAwareBusDispatcher::register($this->app);

    $outer = $this->createAccount();
    $inner = $this->createAccount();

    Tenant::set($outer);

    Tenant::runFor($inner, fn () => RecordTenantJob::dispatch());

    expect(payloadAccountId())->toBe($inner->getKey())
        ->and(Tenant::currentId())->toBe($outer->getKey());
});

test('a dispatch returned from runWithout is pushed with no account', function () {
    config(['queue.default' => 'database']);
    TenantAwareBusDispatcher::register($this->app);

    Tenant::set($this->createAccount());

    Tenant::runWithout(fn () => RecordTenantJob::dispatch());

    expect(DB::table('jobs')->count())->toBe(1)
        ->and(payloadAccountId())->toBeNull();
});

test('an afterResponse job runs under the account it was dispatched with', function () {
    config(['base-tenant.tenancy.restore_dispatch_context' => true]);
    TenantAwareBusDispatcher::register($this->app);

    $outer = $this->createAccount();
    $inner = $this->createAccount();

    Tenant::set($outer);
    RecordTenantJob::$seenAccountId = null;

    Tenant::runFor($inner, fn () => RecordTenantJob::dispatch()->afterResponse());

    // The context has moved on by the time the application terminates.
    Tenant::set($this->createAccount());

    $this->app->terminate();

    expect(RecordTenantJob::$seenAccountId)->toBe($inner->getKey());
});

test('an afterResponse job built in the current context keeps it', function () {
    config(['base-tenant.tenancy.restore_dispatch_context' => true]);
    TenantAwareBusDispatcher::register($this->app);

    $account = $this->createAccount();

    Tenant::set($account);
    RecordTenantJob::$seenAccountId = null;

    RecordTenantJob::dispatch()->afterResponse();

    Tenant::forget();

    $this->app->terminate();

    expect(RecordTenantJob::$seenAccountId)->toBe($account->getKey())
        ->and(Tenant::currentId())->toBeNull();
});

test('by default a sync job inside runFor leaves the rest of the block unresolved (3.0 behaviour)', function () {
    config(['queue.default' => 'sync']);

    $account = $this->createAccount();

    $after = Tenant::runFor($account, function (): ?string {
        RecordTenantJob::dispatchSync();

        return Tenant::currentId();
    });

    expect($after)->toBeNull();
});

test('with restore_dispatch_context a sync job hands the block its account back', function () {
    config(['queue.default' => 'sync', 'base-tenant.tenancy.restore_dispatch_context' => true]);

    $account = $this->createAccount();

    $after = Tenant::runFor($account, function (): ?string {
        RecordTenantJob::dispatchSync();

        return Tenant::currentId();
    });

    expect($after)->toBe($account->getKey())
        ->and(RecordTenantJob::$seenAccountId)->toBe($account->getKey());
});

test('with restore_dispatch_context a failing sync job still restores the context', function () {
    config(['queue.default' => 'sync', 'base-tenant.tenancy.restore_dispatch_context' => true]);

    $account = $this->createAccount();

    $after = Tenant::runFor($account, function (): ?string {
        try {
            dispatch_sync(new class implements ShouldQueue
            {
                use Dispatchable;
                use InteractsWithQueue;
                use Queueable;

                public function handle(): void
                {
                    throw new RuntimeException('boom');
                }
            });
        } catch (RuntimeException) {
            // expected
        }

        return Tenant::currentId();
    });

    expect($after)->toBe($account->getKey());
});

test('a worker still starts every job from a clean context', function () {
    config(['queue.default' => 'database', 'base-tenant.tenancy.restore_dispatch_context' => true]);

    $account = $this->createAccount();

    Tenant::runFor($account, function (): void {
        RecordTenantJob::dispatch();
    });

    Tenant::forget();
    RecordTenantJob::$seenAccountId = null;

    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->run();

    expect(RecordTenantJob::$seenAccountId)->toBe($account->getKey())
        ->and(Tenant::currentId())->toBeNull();
});
