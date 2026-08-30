<?php

declare(strict_types=1);

namespace Base\Tenant\Gdpr\Exporters;

use Base\Tenant\Gdpr\GdprExporter;
use Base\Tenant\Models\ActivityLog;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * What this person did, not what was done to them.
 */
class ActivityExporter implements GdprExporter
{
    public function name(): string
    {
        return 'activity';
    }

    public function export(Authenticatable $user): array
    {
        return ActivityLog::query()
            ->acrossAccounts()
            ->where('causer_id', $user->getKey())
            ->orderBy('created_at')
            ->get()
            ->map(fn (ActivityLog $entry): array => [
                'action' => $entry->action,
                'description' => $entry->description,
                'created_at' => $entry->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
