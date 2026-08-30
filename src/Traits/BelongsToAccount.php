<?php

declare(strict_types=1);

namespace Base\Tenant\Traits;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Tenancy\AccountScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the active account and stamps new records with it.
 *
 * Add it to any model in the host application that holds tenant data:
 *
 *     class Invoice extends Model
 *     {
 *         use BelongsToAccount;
 *     }
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

            $model->setAttribute($column, Tenant::currentId());
        });
    }

    public function getAccountIdColumn(): string
    {
        return defined(static::class.'::ACCOUNT_ID') ? static::ACCOUNT_ID : 'account_id';
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
     * superadmin tooling and maintenance commands.
     */
    public function scopeAcrossAccounts(Builder $query): Builder
    {
        return $query->withoutGlobalScope(AccountScope::class);
    }

    /**
     * Query a specific account regardless of the one in context.
     */
    public function scopeForAccount(Builder $query, Account|string $account): Builder
    {
        $accountId = $account instanceof Account ? $account->getKey() : $account;

        return $query->withoutGlobalScope(AccountScope::class)
            ->where($query->qualifyColumn($this->getAccountIdColumn()), $accountId);
    }
}
