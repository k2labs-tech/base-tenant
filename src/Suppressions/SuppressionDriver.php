<?php

declare(strict_types=1);

namespace Base\Tenant\Suppressions;

use Illuminate\Http\Request;

/**
 * How one mail provider reports bounces and complaints.
 *
 * An interface rather than a Mailgun-shaped controller, so adding SES or
 * Postmark is a class and a config line instead of a change to the core.
 */
interface SuppressionDriver
{
    /**
     * Is this request really from the provider?
     */
    public function verify(Request $request): bool;

    /**
     * The addresses to suppress, each with its reason.
     *
     * @return list<array{email: string, reason: string, metadata?: array<string, mixed>}>
     */
    public function extract(Request $request): array;
}
