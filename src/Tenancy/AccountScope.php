<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Tenancy\Exceptions\MissingTenantException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a tenant-owned model to the active account.
 */
class AccountScope implements Scope
{
    /**
     * @throws MissingTenantException under `on_missing_tenant = throw`, with no
     *                                account in context and no bypass open
     */
    public function apply(Builder $builder, Model $model): void
    {
        $column = $builder->qualifyColumn($model->getAccountIdColumn());
        $accountId = Tenant::currentId();

        if ($accountId !== null) {
            $builder->where($column, $accountId);

            return;
        }

        if ($this->shouldThrowWithoutTenant()) {
            throw MissingTenantException::forQuery($model::class);
        }

        if ($this->shouldDenyWithoutTenant()) {
            $builder->whereRaw('1 = 0');
        }
    }

    /**
     * `throw` fails loudly instead of returning nothing. An open
     * `Tenant::runWithout()` is the explicit bypass, and runs unfiltered.
     */
    protected function shouldThrowWithoutTenant(): bool
    {
        return config('base-tenant.tenancy.on_missing_tenant', 'auto') === 'throw'
            && ! Tenant::isBypassed();
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
            'allow', 'throw' => false,
            default => ! app()->runningInConsole(),
        };
    }
}
