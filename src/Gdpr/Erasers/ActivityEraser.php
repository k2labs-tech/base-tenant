<?php

declare(strict_types=1);

namespace Base\Tenant\Gdpr\Erasers;

use Base\Tenant\Gdpr\GdprEraser;
use Base\Tenant\Models\ActivityLog;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The activity stays; the person is removed from it. An audit trail with the
 * description intact is still an audit trail, and deleting it would destroy
 * the record of what was done to other people's data.
 */
class ActivityEraser implements GdprEraser
{
    public function erase(Authenticatable $user): void
    {
        ActivityLog::query()
            ->acrossAccounts()
            ->where('causer_id', $user->getAuthIdentifier())
            ->update(['causer_id' => null, 'causer_type' => null]);
    }
}
