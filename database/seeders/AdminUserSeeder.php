<?php

declare(strict_types=1);

namespace Base\Tenant\Database\Seeders;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the platform administrator, so a freshly migrated database can be
 * signed into.
 *
 * Idempotent: running it again leaves an existing administrator alone rather
 * than resetting their password.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('base-tenant.admin.email', 'admin@example.com');
        $userModel = config('base-tenant.models.user', User::class);

        $admin = $userModel::where('email', $email)->first();

        if ($admin) {
            $this->command?->info("Administrator {$email} already exists, leaving their account alone.");
        } else {
            $admin = $userModel::create([
                'is_admin' => true,
                'account_id' => null,
                'name' => (string) config('base-tenant.admin.name', 'Administrator'),
                'email' => $email,
                'password' => Hash::make((string) config('base-tenant.admin.password', 'secret123')),
                'email_verified_at' => now(),
            ]);

            $this->command?->info("Administrator created: {$email}");
        }

        // Roles are granted every run, existing administrator or not. A
        // release that adds a system role would otherwise reach only
        // installations created after it, and the administrator who has been
        // there since the beginning would be the one missing it.
        $this->grantSystemRoles($admin);
    }

    /**
     * Give the platform administrator every system role there is.
     *
     * All of them rather than just `administrator`: the roles exist to be
     * assignable, and an administrator who holds none of the others cannot see
     * on screen what they are able to do -- the superadmin flag answers the
     * permission checks invisibly, which is not the same as holding the role.
     *
     * Staff operate outside any account, so this runs without a tenant and the
     * roles land on the system team, where the resolver looks for them when
     * nothing else is in context.
     */
    protected function grantSystemRoles(object $admin): void
    {
        $roles = collect(config('base-tenant.roles.system', []))
            ->pluck('key')
            ->filter()
            ->all();

        if ($roles === []) {
            return;
        }

        Tenant::runWithout(function () use ($admin, $roles): void {
            foreach ($roles as $role) {
                // `addRole` is idempotent, so re-running adds nothing twice.
                $admin->addRole($role);
            }
        });

        $this->command?->info('Granted system roles: '.implode(', ', $roles).'.');
    }
}
