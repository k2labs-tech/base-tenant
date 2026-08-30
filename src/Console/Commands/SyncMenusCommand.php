<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Facades\Menu;
use Illuminate\Console\Command;

class SyncMenusCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:sync-menus';

    protected $description = 'Write the menus declared in code to the database, keeping per-account overrides';

    public function handle(): int
    {
        $this->info('Syncing navigation menus...');

        $result = Menu::sync();

        $this->comment("Synced {$result['menus']} menus with {$result['items']} entries.");

        return self::SUCCESS;
    }
}
