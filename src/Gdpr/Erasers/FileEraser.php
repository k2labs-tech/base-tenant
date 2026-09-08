<?php

declare(strict_types=1);

namespace Base\Tenant\Gdpr\Erasers;

use Base\Tenant\Gdpr\GdprEraser;
use Base\Tenant\Models\File;
use Base\Tenant\Support\Module;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * What the person uploaded goes with them. The bytes first, then the row: the
 * other order leaves an object on the disk that nothing points at and nobody
 * bills.
 */
class FileEraser implements GdprEraser
{
    public function erase(Authenticatable $user): void
    {
        if (! Module::enabled(Module::FILES)) {
            return;
        }

        File::query()
            ->acrossAccounts()
            ->withTrashed()
            ->where('uploaded_by', $user->getAuthIdentifier())
            ->each(function (File $file): void {
                $file->storage()->deleteDirectory($file->directory());
                $file->forceDelete();
            });
    }
}
