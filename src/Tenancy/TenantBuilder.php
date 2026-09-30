<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Tenancy\Exceptions\CrossAccountWriteException;
use Base\Tenant\Tenancy\Exceptions\MissingTenantException;
use Base\Tenant\Tenancy\Exceptions\UnscopableQueryException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;

/**
 * The Eloquent builder a BelongsToAccount model gets under strict tenancy
 * (`base-tenant.tenancy.strict`).
 *
 * AccountScope covers every path that runs `applyScopes()`. This class covers
 * the ones that do not:
 *
 * - `forceDelete()` goes straight to the base query builder, so it ignores the
 *   scope: `File::onlyTrashed()->forceDelete()` would purge every account.
 *   Here the account constraint is added by hand -- only that one, so the
 *   soft-delete scope does not quietly skip trashed rows.
 * - `truncate()`, `updateOrInsert()` and `updateFrom()` are forwarded to the
 *   base builder too, and none of them can be given an account predicate.
 *   They are refused while the scope is active.
 * - `upsert()` looks scoped but is not: the INSERT grammar drops the WHERE,
 *   and the conflict target alone decides which row is rewritten. The
 *   account column must be part of it, and rows missing it are stamped with
 *   the account in context.
 * - A join brings in a second table with no constraint at all. Joins against
 *   a tenant table get the account predicate in their ON clause (so a left
 *   join stays a left join); cross joins, which have no ON, get it in WHERE.
 *   `joinSub()` and lateral joins build their table from an expression, so
 *   the sub-query has to scope itself.
 *
 * Removing the scope in writing -- `->acrossAccounts()`, `->forAccount()`,
 * `withoutGlobalScope(AccountScope::class)` -- or opening
 * `Tenant::runWithout()` switches every one of these guards off.
 *
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class TenantBuilder extends Builder
{
    /** @var array<string, true> */
    protected const REFUSED_METHODS = [
        'truncate' => true,
        'updateorinsert' => true,
        'updatefrom' => true,
    ];

    /** @var array<string, true> */
    protected const JOIN_METHODS = [
        'join' => true,
        'joinwhere' => true,
        'leftjoin' => true,
        'leftjoinwhere' => true,
        'rightjoin' => true,
        'rightjoinwhere' => true,
        'crossjoin' => true,
    ];

    /**
     * Permanently delete the matching rows of the account in context.
     *
     * @return mixed
     */
    public function forceDelete()
    {
        $scope = $this->scopes[AccountScope::class] ?? null;

        if ($scope instanceof AccountScope) {
            $scope->apply($this, $this->model);
        }

        return $this->query->delete();
    }

    /**
     * Insert or update rows without ever rewriting another account's row.
     *
     * @param  array<int|string, mixed>  $values
     * @param  array<int, string>|string  $uniqueBy
     * @param  array<int|string, string>|null  $update
     * @return int
     *
     * @throws MissingTenantException with no account in context where unscoped access is not allowed
     * @throws CrossAccountWriteException when a row names another account
     * @throws UnscopableQueryException when the conflict target omits the account column
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        if ($values === [] || ! $this->accountScopeIsActive() || ! $this->tableIsAccountScoped()) {
            return parent::upsert($values, $uniqueBy, $update);
        }

        $accountId = Tenant::currentId();

        if ($accountId === null) {
            if (! Tenant::permitsUnscopedAccess()) {
                throw MissingTenantException::forWrite($this->model::class, 'upsert');
            }

            return parent::upsert($values, $uniqueBy, $update);
        }

        $column = $this->model->getAccountIdColumn();
        $conflictTarget = array_values(array_map('strval', (array) $uniqueBy));

        if (! in_array($column, $conflictTarget, true)) {
            throw UnscopableQueryException::forUpsertConflictTarget($this->model::class, $column, $conflictTarget);
        }

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        foreach ($values as $index => $row) {
            $rowAccountId = $row[$column] ?? null;

            if ($rowAccountId === null) {
                $values[$index][$column] = $accountId;

                continue;
            }

            if ((string) $rowAccountId !== $accountId) {
                throw CrossAccountWriteException::foreignRow($this->model::class, 'upsert', (string) $rowAccountId, $accountId);
            }
        }

        return parent::upsert($values, $uniqueBy, $update);
    }

    /**
     * Intercept the calls Eloquent would forward to the base builder.
     *
     * @param  string  $method
     * @param  array<int, mixed>  $parameters
     * @return mixed
     *
     * @throws UnscopableQueryException for a method that cannot carry the account predicate
     */
    public function __call($method, $parameters)
    {
        $normalised = strtolower($method);

        if (isset(static::REFUSED_METHODS[$normalised]) && $this->accountScopeIsActive()) {
            throw UnscopableQueryException::forMethod($this->model::class, $method);
        }

        if (! isset(static::JOIN_METHODS[$normalised])) {
            return parent::__call($method, $parameters);
        }

        $joinsBefore = count($this->query->joins ?? []);

        $result = parent::__call($method, $parameters);

        $this->constrainJoinsAddedAfter($joinsBefore);

        return $result;
    }

    /**
     * Whether this builder still carries the account scope and no bypass is
     * open.
     */
    protected function accountScopeIsActive(): bool
    {
        return isset($this->scopes[AccountScope::class]) && ! Tenant::isBypassed();
    }

    protected function tableIsAccountScoped(): bool
    {
        return AccountScopedTables::instance()->includesTableOf($this->model);
    }

    /**
     * Add the account predicate to every join added since `$offset`.
     */
    protected function constrainJoinsAddedAfter(int $offset): void
    {
        if (! $this->accountScopeIsActive()) {
            return;
        }

        $accountId = Tenant::currentId();

        // Nothing to constrain with; the base table's scope decides what a
        // query with no account does.
        if ($accountId === null) {
            return;
        }

        $joins = $this->query->joins ?? [];

        for ($index = $offset, $total = count($joins); $index < $total; $index++) {
            $join = $joins[$index];

            if (! $join instanceof JoinClause) {
                continue;
            }

            $column = $this->accountColumnForJoin($join);

            if ($column === null) {
                continue;
            }

            if ($join->type === 'cross') {
                $this->query->where($column, '=', $accountId);

                continue;
            }

            $join->where($column, '=', $accountId);

            // The parent query copied the join's bindings when the join was
            // registered, before this predicate existed.
            $this->query->addBinding($accountId, 'join');
        }
    }

    /**
     * The qualified account column of a joined tenant table, or null for an
     * expression, an exempt table or one without the column.
     */
    protected function accountColumnForJoin(JoinClause $join): ?string
    {
        $table = $join->table;

        if (! is_string($table) || $table === '') {
            return null;
        }

        $segments = preg_split('/\s+as\s+/i', trim($table)) ?: [$table];
        $name = trim($segments[0]);
        $alias = isset($segments[1]) ? trim($segments[1]) : $name;

        if ($name === '' || str_contains($name, '(')) {
            return null;
        }

        if (! AccountScopedTables::instance()->includes($name)) {
            return null;
        }

        return $alias.'.account_id';
    }
}
