<?php

declare(strict_types=1);

namespace Base\Tenant\Models;

use Base\Tenant\Database\Factories\AccountDomainFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A hostname a customer has pointed at the product.
 *
 * Deliberately NOT `BelongsToAccount`. This model is read while the tenant is
 * being resolved -- there is no account in context yet, and a global scope
 * would either return nothing or, with `on_missing_tenant=deny`, refuse the
 * query outright, so no custom domain would ever resolve. Every read from the
 * interface goes through `DomainManager`, which scopes explicitly.
 *
 * @property string $hostname
 * @property string $status
 * @property string $verification_token
 */
class AccountDomain extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_FAILED = 'failed';

    protected $guarded = ['id'];

    protected $casts = [
        'is_primary' => 'boolean',
        'verified_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    /** @var class-string<AccountDomainFactory> */
    protected static $factory = AccountDomainFactory::class;

    public function account(): BelongsTo
    {
        return $this->belongsTo(
            config('base-tenant.models.account', Account::class)
        );
    }

    /**
     * Only a verified domain may resolve a tenant.
     *
     * This scope is the guard. Without it a customer could claim any hostname
     * -- including a competitor's -- and be served as its owner the moment DNS
     * pointed here. `DomainsTest` fails if it stops being applied.
     *
     * @param  Builder<AccountDomain>  $query
     */
    public function scopeVerified(Builder $query): void
    {
        $query->where('status', self::STATUS_VERIFIED);
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    /**
     * The DNS record the customer has to publish, ready to be shown.
     *
     * @return array{host: string, type: string, value: string}
     */
    public function expectedRecord(): array
    {
        $prefix = config('base-tenant.domains.custom.verification.txt_prefix', '_base-tenant-verify');

        return [
            'host' => $prefix.'.'.$this->hostname,
            'type' => 'TXT',
            'value' => $this->verification_token,
        ];
    }
}
