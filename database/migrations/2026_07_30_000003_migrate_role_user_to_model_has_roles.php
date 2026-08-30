<?php

use Base\Tenant\Models\User;
use Base\Tenant\Tenancy\TenantManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves existing role assignments from the legacy `role_user` pivot into
 * spatie's `model_has_roles`, using `account_id` as the team.
 *
 * Assignments with no account -- staff users that predate account scoping --
 * land on the system team so they keep working outside any tenant.
 *
 * The legacy table is left in place: nothing writes to it any more, and
 * keeping it makes the upgrade reversible by hand if needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role_user')) {
            return;
        }

        $userModel = config('base-tenant.models.user', User::class);
        $morphClass = (new $userModel)->getMorphClass();
        $hasAccountColumn = Schema::hasColumn('role_user', 'account_id');

        DB::table('role_user')->orderBy('id')->chunk(500, function ($assignments) use ($morphClass, $hasAccountColumn): void {
            $rows = [];

            foreach ($assignments as $assignment) {
                $accountId = $hasAccountColumn ? $assignment->account_id : null;

                $rows[] = [
                    'role_id' => $assignment->role_id,
                    'model_type' => $morphClass,
                    'model_id' => $assignment->user_id,
                    'account_id' => $accountId ?: TenantManager::SYSTEM_TEAM_ID,
                ];
            }

            if ($rows === []) {
                return;
            }

            DB::table('model_has_roles')->insertOrIgnore($rows);
        });
    }
};
