<?php

declare(strict_types=1);

namespace Base\Tenant\Tests\Fixtures;

use Base\Tenant\Facades\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Records the account it saw while running, so tests can prove the tenant
 * survives the trip through the queue.
 */
class RecordTenantJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public static ?string $seenAccountId = null;

    public function handle(): void
    {
        static::$seenAccountId = Tenant::currentId();
    }
}
