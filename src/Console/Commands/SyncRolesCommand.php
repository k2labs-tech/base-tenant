<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Models\Permission;
use Base\Tenant\Models\Role;
use Base\Tenant\Services\PermissionRegistry;
use Illuminate\Console\Command;

class SyncRolesCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:sync-roles {--show : List the resulting roles and their permissions}';

    protected $description = 'Write the configured permissions and global roles to the database';

    public function handle(): int
    {
        $this->info('Syncing permissions and roles from configuration...');

        $result = PermissionRegistry::sync();

        $this->comment("Synced {$result['permissions']} permissions and {$result['roles']} roles.");

        if ($this->option('show')) {
            $this->showRoles();
        }

        return self::SUCCESS;
    }

    protected function showRoles(): void
    {
        $rows = Role::query()
            ->whereNull('account_id')
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                $role->name,
                $role->display_name,
                $role->is_system ? 'yes' : 'no',
                $role->permissions->count(),
            ])
            ->all();

        $this->table(['Role', 'Label', 'System', 'Permissions'], $rows);

        $this->comment('Permission catalogue: '.Permission::query()->count().' entries.');
    }
}
