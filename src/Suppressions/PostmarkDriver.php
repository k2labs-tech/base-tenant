<?php

declare(strict_types=1);

namespace Base\Tenant\Suppressions;

use Base\Tenant\Models\EmailSuppression;
use Illuminate\Http\Request;

/**
 * Postmark's bounce, spam complaint and subscription change webhooks.
 *
 * Postmark does not sign its webhooks. What it documents instead is HTTP basic
 * authentication written into the webhook URL
 * (`https://user:secret@example.test/webhooks/suppressions/postmark`), with
 * its published IP ranges as an optional second fence. So "verify" here is
 * the credentials in the request, compared in constant time; without them
 * configured nothing is accepted, the same as Mailgun without its key.
 *
 * There is no timestamp to bound replays with. A captured request can be
 * replayed, but only to suppress an address Postmark already reported, which
 * is why basic auth over TLS is the level Postmark itself settles for.
 */
class PostmarkDriver implements SuppressionDriver
{
    /**
     * Bounce types that mean the address is gone, as opposed to a mailbox
     * that is full or a server that is down today.
     */
    public const PERMANENT_BOUNCES = ['HardBounce', 'BadEmailAddress'];

    public function verify(Request $request): bool
    {
        $username = (string) config('base-tenant.suppressions.postmark_webhook_username');
        $password = (string) config('base-tenant.suppressions.postmark_webhook_password');

        if ($username === '' || $password === '') {
            return false;
        }

        return hash_equals($username, (string) $request->getUser())
            && hash_equals($password, (string) $request->getPassword());
    }

    public function extract(Request $request): array
    {
        $recordType = $request->input('RecordType');

        return match ($recordType) {
            'Bounce' => $this->bounce($request),
            'SpamComplaint' => $this->entry($request->input('Email'), EmailSuppression::COMPLAINT, $request),
            'SubscriptionChange' => $this->subscriptionChange($request),
            default => [],
        };
    }

    /**
     * A soft bounce is a mailbox that was full this morning. Postmark marks
     * the address `Inactive` when it stops sending to it itself, and that is
     * the signal worth following; the type list covers a payload without it.
     *
     * @return list<array{email: string, reason: string, metadata?: array<string, mixed>}>
     */
    protected function bounce(Request $request): array
    {
        $permanent = in_array($request->input('Type'), self::PERMANENT_BOUNCES, true)
            || $request->input('Inactive') === true;

        return $permanent
            ? $this->entry($request->input('Email'), EmailSuppression::BOUNCE, $request)
            : [];
    }

    /**
     * Postmark's own suppression list changing. Only additions are followed:
     * a reactivation there is not a reason to release an address here, for
     * the same reason `release()` is manual only.
     *
     * @return list<array{email: string, reason: string, metadata?: array<string, mixed>}>
     */
    protected function subscriptionChange(Request $request): array
    {
        if ($request->input('SuppressSending') !== true) {
            return [];
        }

        $reason = match ($request->input('SuppressionReason')) {
            'HardBounce' => EmailSuppression::BOUNCE,
            'SpamComplaint' => EmailSuppression::COMPLAINT,
            'ManualSuppression' => EmailSuppression::UNSUBSCRIBE,
            default => null,
        };

        return $reason === null ? [] : $this->entry($request->input('Recipient'), $reason, $request);
    }

    /**
     * @return list<array{email: string, reason: string, metadata?: array<string, mixed>}>
     */
    protected function entry(mixed $email, string $reason, Request $request): array
    {
        if (! is_string($email) || $email === '') {
            return [];
        }

        return [[
            'email' => $email,
            'reason' => $reason,
            'metadata' => array_filter([
                'record_type' => $request->input('RecordType'),
                'type' => $request->input('Type'),
                'type_code' => $request->input('TypeCode'),
                'suppression_reason' => $request->input('SuppressionReason'),
                'message_id' => $request->input('MessageID'),
            ], fn (mixed $value): bool => $value !== null),
        ]];
    }
}
