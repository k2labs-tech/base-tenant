<?php

declare(strict_types=1);

namespace Base\Tenant\Gdpr\Exporters;

use Base\Tenant\Gdpr\GdprExporter;
use Illuminate\Contracts\Auth\Authenticatable;

class ProfileExporter implements GdprExporter
{
    public function name(): string
    {
        return 'profile';
    }

    public function export(Authenticatable $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale ?? null,
            'created_at' => $user->created_at?->toIso8601String(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'terms_accepted_at' => $user->terms_accepted_at?->toIso8601String(),
            'terms_version' => $user->terms_version,
            // Never the password hash, never the two-factor secret: a hash is
            // still a credential, and an export is a file that travels.
        ];
    }
}
