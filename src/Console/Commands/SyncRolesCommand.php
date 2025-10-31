<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Traits\HasExtensibleRoles;
use Illuminate\Console\Command;

class SyncRolesCommand extends Command
{
    use HasExtensibleRoles;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'base-tenant:sync-roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync roles from configuration to database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Syncing roles from configuration...');

        $roles = static::getAllConfiguredRoles();

        $this->info("Found {$roles->count()} roles in configuration.");

        static::syncRolesToDatabase();

        $this->info('Roles synced successfully!');

        $this->table(
            ['Key', 'Name', 'System Role'],
            $roles->map(fn ($role) => [
                $role['key'],
                $role['name'],
                $role['is_system'] ? 'Yes' : 'No',
            ])->toArray()
        );

        return self::SUCCESS;
    }
}
