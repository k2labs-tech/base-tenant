<?php

declare(strict_types=1);

namespace Base\Tenant\Traits;

use Base\Tenant\Gdpr\DataErasureService;
use Illuminate\Database\Eloquent\Model;

/**
 * Runs the registered GDPR erasers before a user row is destroyed for good.
 *
 * A trait with a `boot` method rather than `booted()` on the model: a host
 * subclass that declares its own `booted()` without calling the parent's
 * would silently switch the hook off, and the purge would go on reporting
 * people gone while their rows stayed. Trait boot methods are called for
 * every trait on the class and cannot be overridden by accident.
 */
trait PurgesPersonalData
{
    public static function bootPurgesPersonalData(): void
    {
        static::forceDeleting(function (Model $user): void {
            $user->purgePersonalData();
        });
    }

    /**
     * Remove or anonymise what every registered domain holds about this person.
     */
    public function purgePersonalData(): void
    {
        app(DataErasureService::class)->erase($this);
    }
}
