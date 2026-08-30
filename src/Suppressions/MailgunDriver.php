<?php

declare(strict_types=1);

namespace Base\Tenant\Suppressions;

use Base\Tenant\Models\EmailSuppression;
use Illuminate\Http\Request;

/**
 * Mailgun's webhook.
 *
 * The signature is `hmac_sha256(timestamp + token, signing_key)`, which is
 * documented and stable. The event shape below is the v3 payload.
 */
class MailgunDriver implements SuppressionDriver
{
    /**
     * How old a delivery may be and still be accepted.
     *
     * Without a window, a captured request stays valid forever and can be
     * replayed to suppress any address at any time.
     */
    public const TOLERANCE_SECONDS = 300;

    public function verify(Request $request): bool
    {
        $key = (string) config('base-tenant.suppressions.mailgun_signing_key');

        if ($key === '') {
            return false;
        }

        $signature = $request->input('signature', []);

        $timestamp = (string) ($signature['timestamp'] ?? '');
        $token = (string) ($signature['token'] ?? '');
        $given = (string) ($signature['signature'] ?? '');

        if ($timestamp === '' || $token === '' || $given === '') {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.$token, $key), $given);
    }

    public function extract(Request $request): array
    {
        $data = $request->input('event-data', []);

        $event = $data['event'] ?? null;
        $severity = $data['severity'] ?? null;
        $email = $data['recipient'] ?? null;

        if (! is_string($email) || $email === '') {
            return [];
        }

        // A temporary failure is a mailbox that was full this morning, not an
        // address that has gone. Suppressing on it would lose customers to a
        // full inbox.
        $reason = match (true) {
            $event === 'complained' => EmailSuppression::COMPLAINT,
            $event === 'unsubscribed' => EmailSuppression::UNSUBSCRIBE,
            $event === 'failed' && $severity === 'permanent' => EmailSuppression::BOUNCE,
            default => null,
        };

        if ($reason === null) {
            return [];
        }

        return [[
            'email' => $email,
            'reason' => $reason,
            'metadata' => [
                'event' => $event,
                'severity' => $severity,
                'code' => $data['delivery-status']['code'] ?? null,
            ],
        ]];
    }
}
