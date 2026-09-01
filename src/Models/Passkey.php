<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One passkey a user registered.
 *
 * @property string $credential_id
 * @property array $record
 */
class Passkey extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'record' => 'array',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('base-tenant.models.user', User::class));
    }

    /**
     * Base64url, which is what the browser sends and what the spec uses.
     * Plain base64 would arrive with characters that do not survive a URL.
     */
    public static function encodeId(string $rawId): string
    {
        return rtrim(strtr(base64_encode($rawId), '+/', '-_'), '=');
    }

    public static function decodeId(string $encoded): string
    {
        return (string) base64_decode(strtr($encoded, '-_', '+/'), true);
    }
}
