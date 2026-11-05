<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class ActivityLog extends Model
{
    use BelongsToAccount;
    use HasUuids;

    protected $table = 'activity_log';

    protected $guarded = ['id'];

    /**
     * Some entries have no account to belong to: sign-in, second factor and
     * session events are about a person, recorded before or outside any
     * account context.
     */
    public function allowsAccountlessRecords(): bool
    {
        return true;
    }

    /**
     * An action in the reader's language: `base-tenant::activity.actions.{action}`
     * when there is a translation (the app adds its own actions there), else
     * the action as it was recorded.
     */
    public static function actionLabel(string $action): string
    {
        $key = "base-tenant::activity.actions.{$action}";

        return Lang::has($key) ? __($key) : Str::ucfirst($action);
    }

    public function getActionLabelAttribute(): string
    {
        return self::actionLabel((string) $this->action);
    }

    /**
     * What the entry is about, in the reader's language:
     * `base-tenant::activity.subjects.{ClassBasename}` when there is a
     * translation, else the class basename.
     */
    public function getSubjectLabelAttribute(): ?string
    {
        if (! $this->subject_type) {
            return null;
        }

        $name = class_basename(Relation::getMorphedModel($this->subject_type) ?? $this->subject_type);
        $key = "base-tenant::activity.subjects.{$name}";

        return Lang::has($key) ? __($key) : $name;
    }

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
