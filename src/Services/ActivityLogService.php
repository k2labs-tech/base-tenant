<?php

declare(strict_types=1);

namespace Base\Tenant\Services;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    /**
     * Attribute names never written to the activity log.
     *
     * The second group arrived with the modules that store third-party
     * credentials: a connection holds `credentials`, an outbound webhook holds
     * a `secret`, a linked social account holds `token` and `refresh_token`.
     * Those columns are encrypted at rest, and a change to one of them landing
     * in plain text in the audit trail would undo that on the first edit.
     */
    protected static array $sensitiveFields = [
        'password', 'remember_token', 'two_factor_secret',
        'two_factor_recovery_codes',
        'credentials', 'secret', 'token', 'refresh_token',
    ];

    public static function log(
        ?Model $subject = null,
        string $action = 'custom',
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
    ): ActivityLog {
        $properties = [];

        if ($oldValues !== null) {
            $properties['old'] = static::filterSensitive($oldValues);
        }

        if ($newValues !== null) {
            $properties['new'] = static::filterSensitive($newValues);
        }

        $causer = Auth::user();

        return ActivityLog::create([
            'account_id' => Tenant::currentId(),
            'causer_id' => $causer?->id,
            'causer_type' => $causer ? $causer->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'action' => $action,
            'description' => $description,
            'properties' => ! empty($properties) ? $properties : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    public static function filterSensitive(array $values): array
    {
        return array_diff_key($values, array_flip(static::$sensitiveFields));
    }

    public static function getForAccount(
        string $accountId,
        ?string $action = null,
        ?string $causerId = null,
        int $perPage = 25,
    ) {
        $query = ActivityLog::forAccount($accountId)
            ->with(['causer', 'subject'])
            ->latest();

        if ($action) {
            $query->byAction($action);
        }

        if ($causerId) {
            $query->where('causer_id', $causerId);
        }

        return $query->paginate($perPage);
    }

    public static function pruneOlderThan(int $days = 90): int
    {
        return ActivityLog::where('created_at', '<', now()->subDays($days))->delete();
    }
}
