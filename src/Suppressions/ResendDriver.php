<?php

declare(strict_types=1);

namespace Base\Tenant\Suppressions;

use Base\Tenant\Models\EmailSuppression;
use Illuminate\Http\Request;

/**
 * Resend's `email.bounced` and `email.complained` webhooks.
 *
 * Resend delivers through Svix, and the signature is Svix's: three headers,
 * `svix-id`, `svix-timestamp` and `svix-signature`, where the last one holds
 * one or more space-separated `v1,<base64>` entries, each an
 * HMAC-SHA256 of `{id}.{timestamp}.{raw body}` keyed with the base64 part of
 * the `whsec_...` signing secret. More than one entry appears while a secret
 * is being rotated, and any of them matching is enough.
 *
 * The raw body is what is signed, so it is read as bytes and never re-encoded
 * from the parsed input: a JSON round trip that reorders a key or escapes a
 * slash differently would fail every signature.
 */
class ResendDriver implements SuppressionDriver
{
    /**
     * How old a delivery may be and still be accepted -- Svix's own
     * tolerance, and the same window the Mailgun driver uses.
     */
    public const TOLERANCE_SECONDS = 300;

    public function verify(Request $request): bool
    {
        $secret = (string) config('base-tenant.suppressions.resend_signing_secret');

        if ($secret === '') {
            return false;
        }

        $key = base64_decode(str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret, true);

        if ($key === false || $key === '') {
            return false;
        }

        $id = (string) $request->header('svix-id');
        $timestamp = (string) $request->header('svix-timestamp');
        $signatures = (string) $request->header('svix-signature');

        if ($id === '' || $signatures === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$request->getContent(), $key, true));

        foreach (explode(' ', $signatures) as $entry) {
            [$version, $signature] = array_pad(explode(',', $entry, 2), 2, '');

            if ($version === 'v1' && hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function extract(Request $request): array
    {
        $type = $request->input('type');
        $data = $request->input('data', []);

        if (! is_array($data)) {
            return [];
        }

        $reason = match ($type) {
            'email.complained' => EmailSuppression::COMPLAINT,
            'email.bounced' => $this->isPermanent($data) ? EmailSuppression::BOUNCE : null,
            default => null,
        };

        if ($reason === null) {
            return [];
        }

        $entries = [];

        foreach ((array) ($data['to'] ?? []) as $recipient) {
            $email = is_string($recipient) ? $this->address($recipient) : '';

            if ($email === '') {
                continue;
            }

            $entries[] = [
                'email' => $email,
                'reason' => $reason,
                'metadata' => array_filter([
                    'event' => $type,
                    'email_id' => $data['email_id'] ?? null,
                    'bounce_type' => $data['bounce']['type'] ?? null,
                    'bounce_sub_type' => $data['bounce']['subType'] ?? null,
                ], fn (mixed $value): bool => $value !== null),
            ];
        }

        return $entries;
    }

    /**
     * Resend sends `email.bounced` for a permanent rejection, and newer
     * payloads say so in `bounce.type`. When that field is there and says
     * anything else, the bounce is not proof the address is gone.
     *
     * @param  array<string, mixed>  $data
     */
    protected function isPermanent(array $data): bool
    {
        $bounceType = $data['bounce']['type'] ?? null;

        return $bounceType === null || $bounceType === 'Permanent';
    }

    /**
     * `Ada <ada@example.test>` or a bare address.
     */
    protected function address(string $recipient): string
    {
        if (preg_match('/<([^>]+)>/', $recipient, $matches) === 1) {
            return trim($matches[1]);
        }

        return trim($recipient);
    }
}
