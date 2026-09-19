<?php

declare(strict_types=1);

namespace Base\Tenant\Gdpr;

use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

/**
 * Everything the application holds about one person, removed or anonymised.
 *
 * Mirrors `DataExportService`: one eraser per domain, registered in
 * `base-tenant.gdpr.erasers`, so the list of what is destroyed is the same
 * list of what is disclosed, and a new module adds itself to both rather than
 * editing the user model.
 */
class DataErasureService
{
    /**
     * @return list<GdprEraser>
     */
    public function erasers(): array
    {
        $erasers = [];

        foreach (config('base-tenant.gdpr.erasers', []) as $class) {
            $eraser = app($class);

            if (! $eraser instanceof GdprEraser) {
                throw new RuntimeException("`{$class}` is not a ".GdprEraser::class.'.');
            }

            $erasers[] = $eraser;
        }

        return $erasers;
    }

    public function erase(Authenticatable $user): void
    {
        foreach ($this->erasers() as $eraser) {
            $eraser->erase($user);
        }
    }
}
