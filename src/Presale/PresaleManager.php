<?php

declare(strict_types=1);

namespace Base\Tenant\Presale;

use Base\Tenant\Models\WaitlistSignup;
use Base\Tenant\Support\Module;

/**
 * Pre-sale mode: registration closed, a landing page instead, and a list of
 * people to invite when it opens.
 *
 *     Presale::isOpen();          // are we in pre-sale?
 *     Presale::seatsLeft();
 *     Presale::join($email);
 */
class PresaleManager
{
    /**
     * Is the product in pre-sale right now?
     */
    public function isOpen(): bool
    {
        return Module::enabled(Module::PRESALE);
    }

    /**
     * Should standard registration be refused?
     *
     * The same question as `isOpen()` today, kept separate because "we are
     * pre-selling" and "nobody may sign up" are two decisions that a product
     * eventually wants to make differently.
     */
    public function registrationClosed(): bool
    {
        return $this->isOpen();
    }

    public function seats(): int
    {
        return (int) config('base-tenant.presale.seats', 0);
    }

    /**
     * How many founding places are left.
     *
     * Counting sold seats rather than storing a counter: the two would
     * disagree the first time a checkout was refunded, and the number on a
     * landing page is a promise.
     */
    public function seatsLeft(): int
    {
        $sold = config('base-tenant.models.account')::query()
            ->whereNotNull('stripe_id')
            ->count();

        return max(0, $this->seats() - $sold);
    }

    public function soldOut(): bool
    {
        return $this->seats() > 0 && $this->seatsLeft() === 0;
    }

    /**
     * Add somebody to the waiting list.
     *
     * Idempotent: somebody who signs up twice has not done anything wrong, and
     * telling them their address is taken is a strange thing to say about a
     * waiting list.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function join(string $email, array $attributes = []): WaitlistSignup
    {
        return WaitlistSignup::updateOrCreate(
            ['email' => mb_strtolower(trim($email))],
            array_filter([
                'name' => $attributes['name'] ?? null,
                'source' => $attributes['source'] ?? null,
                'referrer' => $attributes['referrer'] ?? null,
                'metadata' => $attributes['metadata'] ?? null,
            ], fn ($value): bool => $value !== null),
        );
    }

    public function waiting(): int
    {
        return WaitlistSignup::query()->waiting()->count();
    }
}
