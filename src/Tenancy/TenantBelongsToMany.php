<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Tenancy\Exceptions\CrossAccountWriteException;
use Base\Tenant\Tenancy\Exceptions\MissingTenantException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The `belongsToMany` relation of a BelongsToAccount model under strict
 * tenancy, for pivots that carry `account_id`.
 *
 * A pivot has no model, so nothing in the tenancy layer sees it: `attach()`,
 * `detach()` and `sync()` write through the base query builder, and a lazy
 * load builds its query from the related model. So this relation:
 *
 * 1. refuses every pivot write whose parent does not belong to the account in
 *    context (or, with none, where unscoped access is not allowed);
 * 2. writes the parent's account on every attached row, over whatever the
 *    caller passed;
 * 3. constrains reads, detaches and updates of the pivot to that account.
 *
 * Pivots without the column, or listed in `join_exempt_tables`, are left
 * alone.
 *
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends BelongsToMany<TRelatedModel, TDeclaringModel, Pivot>
 */
class TenantBelongsToMany extends BelongsToMany
{
    protected const COLUMN = 'account_id';

    /**
     * @return $this
     */
    protected function addWhereConstraints()
    {
        parent::addWhereConstraints();

        $this->constrainPivotToAccount();

        return $this;
    }

    /**
     * An eager load builds the relation on a blank parent, so the account in
     * context is what bounds it -- the parents were read under it anyway.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        parent::addEagerConstraints($models);

        $this->constrainPivotToAccount();
    }

    /**
     * @param  mixed  $ids
     * @param  array<string, mixed>  $attributes
     * @param  bool  $touch
     * @return void
     */
    public function attach($ids, array $attributes = [], $touch = true)
    {
        $this->assertParentIsWritable('attach');

        parent::attach($ids, $attributes, $touch);
    }

    /**
     * @param  mixed  $ids
     * @param  bool  $touch
     * @return int
     */
    public function detach($ids = null, $touch = true)
    {
        $this->assertParentIsWritable('detach');

        return parent::detach($ids, $touch);
    }

    /**
     * @param  mixed  $ids
     * @param  bool  $detaching
     * @return array{attached: array<int, mixed>, detached: array<int, mixed>, updated: array<int, mixed>}
     */
    public function sync($ids, $detaching = true)
    {
        $this->assertParentIsWritable('sync');

        return parent::sync($ids, $detaching);
    }

    /**
     * @param  mixed  $id
     * @param  array<string, mixed>  $attributes
     * @param  bool  $touch
     * @return int
     */
    public function updateExistingPivot($id, array $attributes, $touch = true)
    {
        $this->assertParentIsWritable('updateExistingPivot');

        unset($attributes[static::COLUMN]);

        return parent::updateExistingPivot($id, $attributes, $touch);
    }

    /**
     * @param  mixed  $ids
     * @param  bool  $touch
     * @return array{attached: array<int, mixed>, detached: array<int, mixed>}
     */
    public function toggle($ids, $touch = true)
    {
        $this->assertParentIsWritable('toggle');

        return parent::toggle($ids, $touch);
    }

    /**
     * Every pivot read and write -- detach, updateExistingPivot, the lookup
     * inside sync -- constrained to the parent's account.
     *
     * @return QueryBuilder
     */
    public function newPivotQuery()
    {
        $query = parent::newPivotQuery();

        $accountId = $this->parentAccountId();

        if ($accountId !== null) {
            $query->where($this->table.'.'.static::COLUMN, $accountId);
        }

        return $query;
    }

    /**
     * Stamp the parent's account on every attached row, per-id attributes
     * included, so a caller cannot forge a row for another account.
     *
     * @param  array<int, mixed>  $ids
     * @param  array<string, mixed>  $attributes
     * @return array<int, array<string, mixed>>
     */
    protected function formatAttachRecords($ids, array $attributes)
    {
        $records = parent::formatAttachRecords($ids, $attributes);

        $accountId = $this->parentAccountId();

        if ($accountId === null) {
            return $records;
        }

        foreach ($records as $index => $record) {
            $records[$index][static::COLUMN] = $accountId;
        }

        return $records;
    }

    protected function pivotIsAccountScoped(): bool
    {
        return AccountScopedTables::instance()->includes($this->table, static::COLUMN);
    }

    /**
     * The parent's account, or null when the pivot is not tenant-owned.
     */
    protected function parentAccountId(): ?string
    {
        if (! $this->pivotIsAccountScoped()) {
            return null;
        }

        $accountId = $this->parent->getAttribute($this->parentAccountColumn());

        return $accountId === null ? null : (string) $accountId;
    }

    protected function parentAccountColumn(): string
    {
        return method_exists($this->parent, 'getAccountIdColumn')
            ? $this->parent->getAccountIdColumn()
            : static::COLUMN;
    }

    /**
     * The parent's account first; the one in context for the blank parent an
     * eager load uses. Nothing when a bypass is open: an invented constraint
     * would return an empty list that reads as "nothing attached".
     */
    protected function constrainPivotToAccount(): void
    {
        if (! $this->pivotIsAccountScoped() || Tenant::isBypassed()) {
            return;
        }

        $accountId = $this->parentAccountId() ?? Tenant::currentId();

        if ($accountId === null) {
            return;
        }

        $this->query->where($this->qualifyPivotColumn(static::COLUMN), $accountId);
    }

    /**
     * @throws CrossAccountWriteException when the parent belongs to another account
     * @throws MissingTenantException with no account in context where unscoped access is not allowed
     */
    protected function assertParentIsWritable(string $operation): void
    {
        if (! $this->pivotIsAccountScoped() || Tenant::isBypassed()) {
            return;
        }

        $parentAccountId = $this->parent->getAttribute($this->parentAccountColumn());
        $parentAccountId = $parentAccountId === null ? null : (string) $parentAccountId;
        $contextAccountId = Tenant::currentId();

        if ($contextAccountId === null) {
            if (Tenant::permitsUnscopedAccess()) {
                return;
            }

            throw MissingTenantException::forWrite($this->parent::class, $operation.' on '.$this->table);
        }

        if ($parentAccountId === $contextAccountId) {
            return;
        }

        throw CrossAccountWriteException::pivotParentMismatch(
            $this->parent::class,
            $this->table,
            $operation,
            $parentAccountId,
            $contextAccountId,
        );
    }
}
