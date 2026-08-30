<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy;

use Base\Tenant\Facades\Tenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Tells spatie/laravel-permission which team to scope roles to, reading the
 * answer from the tenant context rather than keeping a second copy of it.
 *
 * Users who operate outside any account -- platform staff -- fall back to a
 * fixed system team, so their roles have somewhere to live.
 */
class TenantTeamResolver implements PermissionsTeamResolver
{
    protected int|string|null $override = null;

    public function getPermissionsTeamId(): int|string|null
    {
        return $this->override ?? Tenant::currentId() ?? TenantManager::SYSTEM_TEAM_ID;
    }

    /**
     * Spatie sets this directly in a few places. Honour it as a temporary
     * override, and let a null reset it back to the tenant context.
     *
     * @param  int|string|Model|null  $id
     */
    public function setPermissionsTeamId($id): void
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        $this->override = $id;
    }
}
