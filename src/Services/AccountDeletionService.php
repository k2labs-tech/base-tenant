<?php

declare(strict_types=1);

namespace Base\Tenant\Services;

use Base\Tenant\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guards account deletion by looking for rows that still belong to it.
 *
 * The scan is driver-agnostic: it asks the schema builder which tables exist
 * and which of them carry an `account_id`, instead of querying the SQLite
 * catalogue directly.
 */
class AccountDeletionService
{
    /** @var array<int, string>|null */
    protected static ?array $tablesWithAccountId = null;

    /**
     * Tables that still hold rows for this account, and would be orphaned by
     * deleting it.
     *
     * @return array<int, string>
     */
    public static function blockingTables(Account $account): array
    {
        $blocking = [];

        foreach (static::tablesWithAccountId() as $table) {
            if (DB::table($table)->where('account_id', $account->getKey())->exists()) {
                $blocking[] = $table;
            }
        }

        return $blocking;
    }

    public static function hasUsers(Account $account): bool
    {
        return $account->users()->exists();
    }

    public static function canDelete(Account $account): bool
    {
        return ! static::hasUsers($account) && static::blockingTables($account) === [];
    }

    /**
     * Every table in the schema that has an `account_id` column, minus the
     * ones that model the account itself.
     *
     * @return array<int, string>
     */
    public static function tablesWithAccountId(): array
    {
        if (static::$tablesWithAccountId !== null) {
            return static::$tablesWithAccountId;
        }

        $ignored = static::ignoredTables();
        $tables = [];

        foreach (Schema::getTables() as $table) {
            $name = is_array($table) ? ($table['name'] ?? null) : $table;

            if (! $name || in_array($name, $ignored, true)) {
                continue;
            }

            if (in_array('account_id', Schema::getColumnListing($name), true)) {
                $tables[] = $name;
            }
        }

        return static::$tablesWithAccountId = $tables;
    }

    /** @return array<int, string> */
    protected static function ignoredTables(): array
    {
        return [
            ...[
                'accounts',
                'account_user',
                'users',
                'roles',
                'model_has_roles',
                'model_has_permissions',
                'role_user',
                'menus',
                'menu_items',
                'jobs',
                'job_batches',
                'failed_jobs',
                'cache',
                'cache_locks',
                'sessions',
                'migrations',
            ],
            ...config('base-tenant.account_deletion.ignore_tables', []),
        ];
    }

    public static function flushSchemaCache(): void
    {
        static::$tablesWithAccountId = null;
    }
}
