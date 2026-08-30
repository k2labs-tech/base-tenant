<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy;

use Base\Tenant\Facades\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a tenant-owned model to the active account.
 */
class AccountScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $column = $builder->qualifyColumn($model->getAccountIdColumn());
        $accountId = Tenant::currentId();

        if ($accountId !== null) {
            $builder->where($column, $accountId);

            return;
        }

        if ($this->shouldDenyWithoutTenant()) {
            $builder->whereRaw('1 = 0');
        }
    }

    /**
     * With no tenant in context, decide whether queries run unfiltered or
     * return nothing. Console and queue work legitimately runs without a
     * tenant; a web request that got this far did not resolve one, and
     * returning every account's rows there would be a data leak.
     */
    protected function shouldDenyWithoutTenant(): bool
    {
        return match (config('base-tenant.tenancy.on_missing_tenant', 'auto')) {
            'deny' => true,
            'allow' => false,
            default => ! app()->runningInConsole(),
        };
    }
}
