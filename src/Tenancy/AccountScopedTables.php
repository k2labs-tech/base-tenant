<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Answers "does this table hold one account's rows" for the strict builder's
 * join constraint and for tenant-aware pivots.
 *
 * The schema decides, not a registry filled as models boot: a query can join
 * a table whose model has never been touched in this process, and whether it
 * leaked would then depend on what ran before it.
 *
 * Some tables carry `account_id` with another meaning -- `users.account_id` is
 * a primary account, a null `roles.account_id` is a global role, `sequences`
 * stores the word `global`. Constraining a join against those would drop
 * legitimate rows, so they are exempt; add the host's own to
 * `base-tenant.tenancy.join_exempt_tables`.
 *
 * Answers are memoised per container, so a request, job or command asks the
 * schema once per table.
 */
class AccountScopedTables
{
    /**
     * Package tables whose `account_id` does not mean "owned by this account".
     */
    public const PACKAGE_EXEMPT_TABLES = [
        'users',
        'roles',
        'role_user',
        'model_has_roles',
        'model_has_permissions',
        'menus',
        'menu_items',
        'sequences',
        'personal_access_tokens',
        'user_sessions',
    ];

    /** @var array<string, bool> */
    protected array $resolved = [];

    /**
     * The instance for the running container, created on first use.
     */
    public static function instance(): self
    {
        $app = app();

        if (! $app->bound(self::class)) {
            $app->instance(self::class, new self);
        }

        return $app->make(self::class);
    }

    /**
     * Whether the table carries the given owner column and is not exempt.
     */
    public function includes(string $table, string $column = 'account_id'): bool
    {
        $key = $table.'.'.$column;

        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        if (in_array($table, $this->exemptTables(), true)) {
            return $this->resolved[$key] = false;
        }

        try {
            $hasColumn = Schema::hasColumn($table, $column);
        } catch (Throwable) {
            // A missing table is already an SQL error; this layer should not
            // replace that message with its own.
            $hasColumn = false;
        }

        return $this->resolved[$key] = $hasColumn;
    }

    public function includesTableOf(Model $model): bool
    {
        $column = method_exists($model, 'getAccountIdColumn') ? $model->getAccountIdColumn() : 'account_id';

        return $this->includes($model->getTable(), $column);
    }

    /** @return array<int, string> */
    protected function exemptTables(): array
    {
        return array_values(array_unique([
            ...self::PACKAGE_EXEMPT_TABLES,
            ...(array) config('base-tenant.tenancy.join_exempt_tables', []),
        ]));
    }
}
