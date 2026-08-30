<?php

declare(strict_types=1);

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Services\AccountDeletionService;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    AccountDeletionService::flushSchemaCache();
});

test('the scan finds tables with an account_id through the schema builder', function () {
    $tables = AccountDeletionService::tablesWithAccountId();

    expect($tables)->toContain('user_invites', 'activity_log', 'features')
        ->and($tables)->not->toContain('accounts', 'account_user', 'users');
});

test('an account holding rows elsewhere is not deletable', function () {
    $account = $this->createAccount();

    Tenant::runFor($account, fn (): UserInvite => UserInvite::create([
        'email' => 'someone@example.com',
        'token' => Str::random(64),
        'expires_at' => now()->addWeek(),
    ]));

    expect(AccountDeletionService::blockingTables($account))->toContain('user_invites')
        ->and(AccountDeletionService::canDelete($account))->toBeFalse();
});

test('an empty account is deletable', function () {
    $account = $this->createAccount();

    expect(AccountDeletionService::blockingTables($account))->toBe([])
        ->and(AccountDeletionService::canDelete($account))->toBeTrue();
});

test('an account with users is not deletable', function () {
    $account = $this->createAccount();
    $this->createUser($account);

    expect(AccountDeletionService::hasUsers($account))->toBeTrue()
        ->and(AccountDeletionService::canDelete($account))->toBeFalse();
});

test('host application tables are picked up automatically', function () {
    Schema::create('invoices', function ($table): void {
        $table->uuid('id')->primary();
        $table->uuid('account_id');
    });

    AccountDeletionService::flushSchemaCache();

    $account = $this->createAccount();

    expect(AccountDeletionService::tablesWithAccountId())->toContain('invoices')
        ->and(AccountDeletionService::canDelete($account))->toBeTrue();

    DB::table('invoices')->insert(['id' => Str::uuid()->toString(), 'account_id' => $account->getKey()]);

    expect(AccountDeletionService::blockingTables($account))->toContain('invoices');
});

test('tables can be excluded from the scan by configuration', function () {
    config(['base-tenant.account_deletion.ignore_tables' => ['user_invites']]);

    AccountDeletionService::flushSchemaCache();

    expect(AccountDeletionService::tablesWithAccountId())->not->toContain('user_invites');
});
