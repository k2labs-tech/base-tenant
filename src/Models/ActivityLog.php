<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use BelongsToAccount;
    use HasUuids;

    protected $table = 'activity_log';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    public function getOldAttribute(): array
    {
        return $this->properties['old'] ?? [];
    }

    public function getNewAttribute(): array
    {
        return $this->properties['new'] ?? [];
    }

    public function getChangedFieldsAttribute(): array
    {
        return array_keys($this->properties['new'] ?? []);
    }
}
