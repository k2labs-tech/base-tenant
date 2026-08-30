<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Features\FeatureManager;
use Base\Tenant\Features\FeatureSet;
use Base\Tenant\Models\Account;
use Illuminate\Support\Facades\Facade;

/**
 * @method static FeatureSet for(Account|string|null $account)
 * @method static FeatureSet current()
 * @method static bool active(string $feature)
 * @method static bool inactive(string $feature)
 * @method static int limit(string $feature)
 * @method static bool withinLimit(string $feature, int|null $currentUsage = null)
 * @method static array<string, mixed> all()
 *
 * @see FeatureManager
 */
class Feature extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FeatureManager::class;
    }
}
