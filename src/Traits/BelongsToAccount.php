<?php

declare(strict_types=1);

namespace Base\Tenant\Traits;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Tenancy\AccountScope;
use Base\Tenant\Tenancy\Events\TenancyBypassed;
use Base\Tenant\Tenancy\Exceptions\CrossAccountWriteException;
use Base\Tenant\Tenancy\Exceptions\MissingTenantException;
use Base\Tenant\Tenancy\TenantBelongsToMany;
use Base\Tenant\Tenancy\TenantBuilder;
use Base\Tenant\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Scopes a model to the active account and stamps new records with it.
 *
 * Add it to any model in the host application that holds tenant data:
 *
 *     class Invoice extends Model
 *     {
 *         use BelongsToAccount;
 *     }
 *
 * Under strict tenancy (`base-tenant.tenancy.strict`) it also:
 *
 * - refuses to create a record with no account (unless the model allows
 *   account-less records by overriding `allowsAccountlessRecords()`);
 * - refuses to change a record's account once created;
 * - refuses to update, delete or restore a record of another account;
 * - hands out TenantBuilder, which closes the query paths the global scope
 *   cannot see, and TenantBelongsToMany for pivots that carry `account_id`.
 *
 * The write checks run on model events, so `saveQuietly()` and
 * `Model::withoutEvents()` skip them like any other listener.
 */
trait BelongsToAccount
{
    public static function bootBelongsToAccount(): void
    {
        static::addGlobalScope(new AccountScope);

        static::creating(function (Model $model): void {
            $column = $model->getAccountIdColumn();

            if ($model->getAttribute($column) !== null) {
                return;
            }

            $accountId = Tenant::currentId();

            if ($accountId === null && TenantManager::strict() && ! $model->allowsAccountlessRecords()) {
                throw MissingTenantException::forCreate($model::class);
            }

            $model->setAttribute($column, $accountId);
        });

        static::updating(function (Model $model): void {
            if (! TenantManager::strict()) {
                return;
            }

            $model->assertAccountIdIsUnchanged();
            $model->assertWritableInCurrentContext('update');
        });

        static::deleting(function (Model $model): void {
            if (TenantManager::strict()) {
                $model->assertWritableInCurrentContext('delete');
            }
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            // registerModelEvent() rather than static::restoring(), which only
            // exists on models that soft delete.
            static::registerModelEvent('restoring', function (Model $model): void {
                if (TenantManager::strict()) {
                    $model->assertWritableInCurrentContext('restore');
                }
            });
        }
    }

    public function getAccountIdColumn(): string
    {
        return defined(static::class.'::ACCOUNT_ID') ? static::ACCOUNT_ID : 'account_id';
    }

    /**
     * Whether a record may be created with no account under strict tenancy.
     * Override it for tables where a null account means "platform-wide".
     */
    public function allowsAccountlessRecords(): bool
    {
        return false;
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            config('base-tenant.models.account', Account::class),
            $this->getAccountIdColumn()
        );
    }

    /**
     * Query across every account, bypassing the tenant scope. Reserve it for
     * superadmin tooling and maintenance commands. Announced through
     * TenancyBypassed so it can be audited.
     */
    public function scopeAcrossAccounts(Builder $query, ?string $reason = null): Builder
    {
        TenancyBypassed::record(Tenant::currentId(), TenancyBypassed::SOURCE_ACROSS_ACCOUNTS, $reason, static::class);

        return $query->withoutGlobalScope(AccountScope::class);
    }

    /**
     * Query a specific account regardless of the one in context. Announced
     * through TenancyBypassed when it is not the account in context.
     */
    public function scopeForAccount(Builder $query, Account|string $account): Builder
    {
        $accountId = $account instanceof Account ? $account->getKey() : $account;
        $currentId = Tenant::currentId();

        if ($currentId !== $accountId) {
            TenancyBypassed::record(
                $currentId,
                TenancyBypassed::SOURCE_FOR_ACCOUNT,
                subject: static::class,
                targetAccountId: $accountId,
            );
        }

        return $query->withoutGlobalScope(AccountScope::class)
            ->where($query->qualifyColumn($this->getAccountIdColumn()), $accountId);
    }

    /**
     * Under strict tenancy, TenantBuilder instead of Eloquent's own. A model
     * that declares its own builder must extend TenantBuilder, or the paths
     * the scope cannot see would stay open.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return Builder<*>
     */
    public function newEloquentBuilder($query)
    {
        if (! TenantManager::strict()) {
            return parent::newEloquentBuilder($query);
        }

        $builderClass = $this->resolveCustomBuilderClass();

        if (! is_string($builderClass)) {
            return new TenantBuilder($query);
        }

        if (! is_a($builderClass, TenantBuilder::class, true)) {
            throw new LogicException(sprintf(
                '[%s] is the query builder of [%s], which uses BelongsToAccount under strict tenancy, '
                .'but it does not extend [%s]: forceDelete(), truncate(), upsert() and joins would bypass the account scope.',
                $builderClass,
                static::class,
                TenantBuilder::class,
            ));
        }

        return new $builderClass($query);
    }

    /**
     * Under strict tenancy, pivots that carry `account_id` are written and
     * read through TenantBelongsToMany.
     *
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string|null  $relationName
     * @return BelongsToMany<Model, Model>
     */
    protected function newBelongsToMany(
        Builder $query,
        Model $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
    ) {
        if (! TenantManager::strict()) {
            return parent::newBelongsToMany($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);
        }

        return new TenantBelongsToMany($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);
    }

    /**
     * @throws CrossAccountWriteException
     */
    protected function assertAccountIdIsUnchanged(): void
    {
        $column = $this->getAccountIdColumn();

        if (! $this->exists || ! $this->isDirty($column)) {
            return;
        }

        $from = $this->getOriginal($column);
        $to = $this->getAttribute($column);

        throw CrossAccountWriteException::immutableAccountId(
            static::class,
            $from === null ? null : (string) $from,
            $to === null ? null : (string) $to,
        );
    }

    /**
     * A write reaches a record only when it belongs to the account in context
     * or, with none in context, where an unscoped read would be allowed too.
     *
     * @throws CrossAccountWriteException
     * @throws MissingTenantException
     */
    protected function assertWritableInCurrentContext(string $operation): void
    {
        if (Tenant::isBypassed()) {
            return;
        }

        $contextAccountId = Tenant::currentId();

        if ($contextAccountId === null) {
            if (Tenant::permitsUnscopedAccess()) {
                return;
            }

            throw MissingTenantException::forWrite(static::class, $operation);
        }

        $column = $this->getAccountIdColumn();
        $recordAccountId = $this->getOriginal($column) ?? $this->getAttribute($column);
        $recordAccountId = $recordAccountId === null ? null : (string) $recordAccountId;

        if ($recordAccountId === $contextAccountId) {
            return;
        }

        throw CrossAccountWriteException::contextMismatch(static::class, $operation, $recordAccountId, $contextAccountId);
    }
}
