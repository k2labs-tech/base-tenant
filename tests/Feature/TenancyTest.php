<?php

declare(strict_types=1);

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\ActivityLog;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Tenancy\TenantCache;
use Base\Tenant\Tests\Fixtures\RecordTenantJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

test('the manager holds one account at a time and restores it after runFor', function () {
    $first = $this->createAccount();
    $second = $this->createAccount();

    Tenant::set($first);

    $inner = Tenant::runFor($second, fn (): ?string => Tenant::currentId());

    expect($inner)->toBe($second->getKey())
        ->and(Tenant::currentId())->toBe($first->getKey());
});

test('runWithout clears the account and puts it back', function () {
    $account = $this->createAccount();

    Tenant::set($account);

    $inner = Tenant::runWithout(fn (): ?string => Tenant::currentId());

    expect($inner)->toBeNull()
        ->and(Tenant::currentId())->toBe($account->getKey());
});

test('the context is restored even when the callback throws', function () {
    $first = $this->createAccount();
    $second = $this->createAccount();

    Tenant::set($first);

    try {
        Tenant::runFor($second, function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(Tenant::currentId())->toBe($first->getKey());
});

test('invitations are isolated between accounts', function () {
    $this->assertTenantIsolated(UserInvite::class, fn (Account $account): UserInvite => UserInvite::create([
        'email' => fake()->unique()->safeEmail(),
        'account_id' => $account->getKey(),
        'token' => Str::random(64),
        'expires_at' => now()->addWeek(),
    ]));
});

test('activity log entries are isolated between accounts', function () {
    $this->assertTenantIsolated(ActivityLog::class, fn (Account $account): ActivityLog => ActivityLog::create([
        'action' => 'created',
        'description' => 'test',
    ]));
});

test('new records are stamped with the account in context', function () {
    $account = $this->createAccount();

    $invite = Tenant::runFor($account, fn (): UserInvite => UserInvite::create([
        'email' => 'someone@example.com',
        'token' => Str::random(64),
        'expires_at' => now()->addWeek(),
    ]));

    expect($invite->account_id)->toBe($account->getKey());
});

test('acrossAccounts opts out of the scope for maintenance work', function () {
    $first = $this->createAccount();
    $second = $this->createAccount();

    foreach ([$first, $second] as $account) {
        Tenant::runFor($account, fn (): UserInvite => UserInvite::create([
            'email' => fake()->unique()->safeEmail(),
            'token' => Str::random(64),
            'expires_at' => now()->addWeek(),
        ]));
    }

    Tenant::runFor($first, function (): void {
        expect(UserInvite::count())->toBe(1)
            ->and(UserInvite::query()->acrossAccounts()->count())->toBe(2);
    });
});

test('queued jobs carry the account that dispatched them', function () {
    $this->assertJobCarriesTenant(new RecordTenantJob);
});

test('a worker restores the account the job was dispatched with', function () {
    $account = $this->createAccount();

    config(['queue.default' => 'database']);

    Tenant::runFor($account, function (): void {
        RecordTenantJob::dispatch();
    });

    Tenant::forget();
    RecordTenantJob::$seenAccountId = null;

    expect(DB::table('jobs')->count())->toBe(1);

    $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true])->run();

    expect(RecordTenantJob::$seenAccountId)->toBe($account->getKey());
});

test('cache keys are namespaced per account', function () {
    $first = $this->createAccount();
    $second = $this->createAccount();

    $cache = new TenantCache(Cache::store());

    Tenant::runFor($first, fn () => $cache->put('greeting', 'hello'));
    Tenant::runFor($second, fn () => $cache->put('greeting', 'hola'));

    expect(Tenant::runFor($first, fn () => $cache->get('greeting')))->toBe('hello')
        ->and(Tenant::runFor($second, fn () => $cache->get('greeting')))->toBe('hola');
});

test('broadcast channels are namespaced per account', function () {
    $account = $this->createAccount();

    expect(Tenant::runFor($account, fn (): string => Tenant::channel('orders')))
        ->toBe("tenant.{$account->getKey()}.orders");
});

test('eachAccount sweeps every tenant with its own context', function () {
    $accounts = collect([$this->createAccount(), $this->createAccount(), $this->createAccount()]);

    $seen = [];

    Tenant::eachAccount(function () use (&$seen): void {
        $seen[] = Tenant::currentId();
    });

    expect($seen)->toHaveCount(3)
        ->and(collect($seen)->sort()->values()->all())
        ->toBe($accounts->pluck('id')->sort()->values()->all());
});

test('the session resolver refuses an account the user does not belong to', function () {
    $this->syncPermissions();

    $account = $this->createAccount();
    $foreign = $this->createAccount();
    $user = $this->createUser($account, 'customer-admin');

    $this->actingAs($user);

    session(['current_account_id' => $foreign->getKey()]);
    Tenant::reset();

    expect(Tenant::currentId())->toBe($account->getKey());
});
