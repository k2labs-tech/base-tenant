<?php

declare(strict_types=1);

namespace Base\Tenant\Gdpr;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * One domain of personal data, for the export a user is entitled to ask for.
 *
 * An interface because the package cannot know what an application stores
 * about a person. Every domain that holds personal data registers one, and
 * the export is the sum of them; a domain that forgets to is data that quietly
 * does not appear in a legal disclosure.
 */
interface GdprExporter
{
    /**
     * The file name inside the archive, without extension.
     */
    public function name(): string;

    /**
     * Whatever this domain holds about the user, as something JSON can carry.
     *
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public function export(Authenticatable $user): array;
}
