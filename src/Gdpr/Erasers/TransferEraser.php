<?php

declare(strict_types=1);

namespace Base\Tenant\Gdpr\Erasers;

use Base\Tenant\Gdpr\GdprEraser;
use Base\Tenant\Support\Module;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * An import or export belongs to the account; who started it is the only
 * personal part, and that is what goes.
 */
class TransferEraser implements GdprEraser
{
    public function erase(Authenticatable $user): void
    {
        if (! Module::enabled(Module::TRANSFER)) {
            return;
        }

        DB::table('data_transfers')
            ->where('created_by', $user->getAuthIdentifier())
            ->update(['created_by' => null]);
    }
}
