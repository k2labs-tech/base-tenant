<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The record of one import or export.
 */
class DataTransfer extends Model
{
    use BelongsToAccount;
    use HasUuids;

    public const IMPORT = 'import';

    public const EXPORT = 'export';

    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'options' => 'array',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'failed_rows' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function errorFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'error_file_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('base-tenant.models.user', User::class), 'created_by');
    }

    public function scopeImports(Builder $query): Builder
    {
        return $query->where('type', self::IMPORT);
    }

    public function scopeExports(Builder $query): Builder
    {
        return $query->where('type', self::EXPORT);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::COMPLETED, self::FAILED], true);
    }

    /**
     * How far along, as a percentage.
     *
     * Null rather than zero when the total is not known yet: a bar sitting at
     * 0% looks stuck, and a transfer that has not been counted is not stuck.
     */
    public function progress(): ?int
    {
        if ($this->total_rows === 0) {
            return $this->isFinished() ? 100 : null;
        }

        return (int) min(100, floor($this->processed_rows / $this->total_rows * 100));
    }

    /**
     * Partial success is the normal outcome of a real import: some rows land,
     * some do not, and the difference is what the error file is for.
     */
    public function hasErrors(): bool
    {
        return $this->failed_rows > 0;
    }
}
