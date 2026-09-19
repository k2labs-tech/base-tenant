<?php

declare(strict_types=1);

namespace Base\Tenant\Gdpr;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * One domain of personal data, for the erasure a user is entitled to ask for.
 *
 * The counterpart of `GdprExporter`, and for the same reason: the package
 * cannot know what an application stores about a person. Every domain that
 * holds personal data registers one, and a hard delete is the sum of them.
 * A domain that forgets to is a row that still answers a query under the id
 * of somebody the product has reported gone.
 */
interface GdprEraser
{
    /**
     * Remove or anonymise whatever this domain holds about the user. Runs
     * while the user row still exists, just before it is destroyed.
     */
    public function erase(Authenticatable $user): void;
}
