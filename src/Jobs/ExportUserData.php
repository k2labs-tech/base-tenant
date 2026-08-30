<?php

declare(strict_types=1);

namespace Base\Tenant\Jobs;

use Base\Tenant\Files\FileCollection;
use Base\Tenant\Files\FileStore;
use Base\Tenant\Gdpr\DataExportService;
use Base\Tenant\Models\User;
use Base\Tenant\Notifications\PersonalDataReady;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Build a personal data export and tell the person where to get it.
 *
 * Queued because the archive can take a while, and because a legal request
 * should not depend on the person keeping a browser tab open.
 */
class ExportUserData implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public readonly string $userId,
        public readonly ?string $accountId = null,
    ) {}

    public function handle(DataExportService $exports, FileStore $files): void
    {
        $model = config('base-tenant.models.user', User::class);

        $user = $model::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $path = $exports->build($user);

        try {
            $account = $this->accountId
                ? config('base-tenant.models.account')::find($this->accountId)
                : $user->accounts()->first();

            if (! $account) {
                return;
            }

            $key = FileStore::TMP_PREFIX.'/'.Str::uuid();

            $files->disk()->put($key, file_get_contents($path));

            $file = $files->finalize(
                key: $key,
                collection: FileCollection::make('gdpr-exports'),
                name: 'datos-'.$user->getKey().'.zip',
                account: $account,
                uploadedBy: $user->getKey(),
            );

            $user->notify(new PersonalDataReady($file));
        } finally {
            @unlink($path);
        }
    }
}
