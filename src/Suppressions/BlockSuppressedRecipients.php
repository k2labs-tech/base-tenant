<?php

declare(strict_types=1);

namespace Base\Tenant\Suppressions;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;

/**
 * The global guard: nothing leaves for an address on the list.
 *
 * On `MessageSending` rather than inside a Mailable, so it covers the
 * package's own mail, the application's, and anything a library sends. A guard
 * that has to be remembered at each call site is a guard that will be
 * forgotten at one of them.
 */
class BlockSuppressedRecipients
{
    public function __construct(protected SuppressionManager $suppressions) {}

    /**
     * Returning false from a `MessageSending` listener cancels the send.
     */
    public function handle(MessageSending $event): bool
    {
        $blocked = [];

        foreach (['To', 'Cc', 'Bcc'] as $header) {
            foreach ($event->message->{'get'.$header}() ?? [] as $address) {
                if ($this->suppressions->isSuppressed($address->getAddress())) {
                    $blocked[] = $address->getAddress();
                }
            }
        }

        if ($blocked === []) {
            return true;
        }

        // Logged, because a message silently not arriving is the hardest kind
        // of support ticket: nobody knows whether it was sent.
        Log::info('Mail cancelled: suppressed recipients.', [
            'recipients' => $blocked,
            'subject' => $event->message->getSubject(),
        ]);

        return false;
    }
}
