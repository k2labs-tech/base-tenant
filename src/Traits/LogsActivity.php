<?php

declare(strict_types=1);

namespace Base\Tenant\Traits;

use Base\Tenant\Models\ActivityLog;
use Base\Tenant\Services\ActivityLogService;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        if (! config('base-tenant.activity_log.enabled', true)) {
            return;
        }

        static::created(function ($model) {
            ActivityLogService::log($model, 'created', null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();

            if (empty($dirty)) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $dirty);
            ActivityLogService::log($model, 'updated', $old, $dirty);
        });

        static::deleted(function ($model) {
            ActivityLogService::log($model, 'deleted');
        });
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }
}
