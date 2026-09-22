<?php

declare(strict_types=1);

namespace Base\Tenant\Tests;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Services\PermissionRegistry;
use Base\Tenant\Tenancy\QueueTenancy;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Assertions a consuming application can run against its own models to prove
 * its tenancy holds. Isolation is the kind of property that has to be tested,
 * not read off the code.
 *
 * It also carries the four fixtures those assertions need — an account, a user
 * holding a role inside it, the permission catalogue and an authenticated
 * request with a tenant in context — because a test that cannot build a tenant
 * cannot test one. The modules `k2labs-base:make-module` generates arrive with
 * tests written against exactly these.
 */
trait TenancyAssertions
{
    /**
     * Write the configured permission catalogue and global roles. Nothing can
     * be authorised before this runs.
     */
    protected function syncPermissions(): void
    {
        PermissionRegistry::sync();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createAccount(array $attributes = []): Model
    {
        $model = config('base-tenant.models.account', Account::class);

        return $model::factory()->create($attributes);
    }

    /**
     * A user attached to an account, optionally holding a role inside it.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createUser(?Model $account = null, ?string $role = null, array $attributes = []): Model
    {
        $account ??= $this->createAccount();

        $model = config('auth.providers.users.model');

        $user = $model::factory()->create([
            'account_id' => $account->getKey(),
            ...$attributes,
        ]);

        $user->accounts()->syncWithoutDetaching([$account->getKey()]);

        if ($role !== null) {
            Tenant::runFor($account, fn () => $user->assignRole($role));
        }

        return $user;
    }

    /**
     * Authenticate as a user of the given account, with that account in
     * context, which is what a real request would look like.
     */
    protected function actingAsTenant(Model $user, ?Model $account = null): static
    {
        Tenant::set($account ?? $user->account);

        return $this->actingAs($user);
    }

    /**
     * Prove records of one account are invisible from another.
     *
     * @param  class-string<Model>  $modelClass
     * @param  Closure(Account): Model  $factory  Creates one record for the given account.
     */
    protected function assertTenantIsolated(string $modelClass, Closure $factory): void
    {
        $first = Account::factory()->create();
        $second = Account::factory()->create();

        $recordOfFirst = Tenant::runFor($first, fn (): Model => $factory($first));
        $recordOfSecond = Tenant::runFor($second, fn (): Model => $factory($second));

        Tenant::runFor($first, function () use ($modelClass, $recordOfFirst, $recordOfSecond): void {
            $visible = $modelClass::query()->pluck('id')->all();

            $this->assertContains(
                $recordOfFirst->getKey(),
                $visible,
                $modelClass.' does not return the records of its own account.'
            );

            $this->assertNotContains(
                $recordOfSecond->getKey(),
                $visible,
                $modelClass.' leaks records from another account.'
            );
        });

        Tenant::runFor($second, function () use ($modelClass, $recordOfFirst): void {
            $this->assertNull(
                $modelClass::query()->find($recordOfFirst->getKey()),
                $modelClass.' can be fetched by id from another account.'
            );
        });
    }

    /**
     * Prove a queued job is stamped with the account that dispatched it.
     *
     * The job is pushed to the database queue so the real payload can be read
     * back, rather than trusting a fake.
     */
    protected function assertJobCarriesTenant(object $job): void
    {
        $account = Account::factory()->create();

        $payload = Tenant::runFor($account, function () use ($job): array {
            DB::table('jobs')->delete();

            Queue::connection('database')->push($job);

            $row = DB::table('jobs')->orderByDesc('id')->first();

            return json_decode($row->payload, true);
        });

        $this->assertSame(
            $account->getKey(),
            $payload[QueueTenancy::PAYLOAD_KEY] ?? null,
            'The queued job does not carry the account that dispatched it.'
        );
    }

    /**
     * Prove a permission answers the same inside and outside a request, which
     * is what breaks when roles are cached in the session.
     */
    protected function assertPermissionIsAccountScoped(
        Account $account,
        Account $otherAccount,
        object $user,
        string $permission
    ): void {
        $this->assertTrue(
            $user->hasPermissionInAccount($account, $permission),
            "User should hold {$permission} in its own account."
        );

        $this->assertFalse(
            $user->hasPermissionInAccount($otherAccount, $permission),
            "User should not hold {$permission} in an account it has no role in."
        );
    }
}
