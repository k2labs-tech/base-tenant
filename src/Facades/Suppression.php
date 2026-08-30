<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Models\EmailSuppression;
use Base\Tenant\Suppressions\SuppressionManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isSuppressed(string|null $email)
 * @method static EmailSuppression suppress(string $email, string $reason, string $source = 'ui', array $metadata = [])
 * @method static void release(string $email)
 * @method static void flush(string|null $email = null)
 *
 * @see SuppressionManager
 */
class Suppression extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SuppressionManager::class;
    }
}
