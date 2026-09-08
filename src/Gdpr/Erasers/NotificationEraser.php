<?php

declare(strict_types=1);

namespace Base\Tenant\Gdpr\Erasers;

use Base\Tenant\Gdpr\GdprEraser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Database notifications are addressed to the person and their payload is
 * whatever the notification chose to keep, which has included an initial
 * password.
 */
class NotificationEraser implements GdprEraser
{
    public function erase(Authenticatable $user): void
    {
        $type = $user instanceof Model ? $user->getMorphClass() : $user::class;

        DB::table('notifications')
            ->where('notifiable_type', $type)
            ->where('notifiable_id', $user->getAuthIdentifier())
            ->delete();
    }
}
