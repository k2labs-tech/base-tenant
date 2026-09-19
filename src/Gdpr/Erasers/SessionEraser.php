<?php

declare(strict_types=1);

namespace Base\Tenant\Gdpr\Erasers;

use Base\Tenant\Gdpr\GdprEraser;
use Base\Tenant\Models\UserSession;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where the person was signed in: the package's own session list, and the
 * framework's session table when the host uses the database driver -- its
 * rows carry an address, a user agent and a serialised payload.
 */
class SessionEraser implements GdprEraser
{
    public function erase(Authenticatable $user): void
    {
        UserSession::query()->where('user_id', $user->getAuthIdentifier())->delete();

        // The one table the package does not own: it exists only when the host
        // chose the database session driver.
        $table = (string) config('session.table', 'sessions');

        if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
            DB::table($table)->where('user_id', $user->getAuthIdentifier())->delete();
        }
    }
}
