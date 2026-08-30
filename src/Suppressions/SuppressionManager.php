<?php

declare(strict_types=1);

namespace Base\Tenant\Suppressions;

use Base\Tenant\Models\EmailSuppression;
use Base\Tenant\Support\Module;
use Illuminate\Support\Facades\Cache;

/**
 * The list of addresses this application must not write to.
 *
 *     Suppression::suppress('ada@example.test', 'bounce', 'mailgun');
 *     Suppression::isSuppressed($email);
 *     Suppression::release($email);
 */
class SuppressionManager
{
    public const CACHE_PREFIX = 'base-tenant:suppressed:';

    /**
     * Is this address on the list?
     *
     * Cached per address rather than as one list: the guard runs on every
     * outgoing message, and a product with fifty thousand suppressions should
     * not load fifty thousand rows to send one email.
     */
    public function isSuppressed(?string $email): bool
    {
        if (! Module::enabled(Module::SUPPRESSIONS) || blank($email)) {
            return false;
        }

        $email = mb_strtolower(trim($email));

        return Cache::remember(
            self::CACHE_PREFIX.md5($email),
            now()->addHour(),
            fn (): bool => EmailSuppression::query()->where('email', $email)->exists(),
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function suppress(string $email, string $reason, string $source = 'ui', array $metadata = []): EmailSuppression
    {
        Module::ensure(Module::SUPPRESSIONS);

        $email = mb_strtolower(trim($email));

        $record = EmailSuppression::updateOrCreate(
            ['email' => $email],
            [
                'reason' => $reason,
                'source' => $source,
                'metadata' => $metadata ?: null,
                'suppressed_at' => now(),
            ],
        );

        $this->flush($email);

        return $record;
    }

    /**
     * Take an address off the list.
     *
     * Deliberately manual only. An address that bounced once and comes back on
     * its own would put the sending reputation at the mercy of whatever
     * decided it had recovered.
     */
    public function release(string $email): void
    {
        Module::ensure(Module::SUPPRESSIONS);

        $email = mb_strtolower(trim($email));

        EmailSuppression::query()->where('email', $email)->delete();

        $this->flush($email);
    }

    public function flush(?string $email = null): void
    {
        if ($email === null) {
            return;
        }

        Cache::forget(self::CACHE_PREFIX.md5(mb_strtolower(trim($email))));
    }
}
