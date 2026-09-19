<?php

declare(strict_types=1);

namespace Base\Tenant\Gdpr\Exporters;

use Base\Tenant\Gdpr\GdprExporter;
use Base\Tenant\Models\UserSession;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Sessions hold an address, a device and a pattern of when somebody works.
 * That is personal data, so it belongs in a disclosure -- a domain that is not
 * listed as an exporter is data that quietly does not appear in one.
 */
class SessionExporter implements GdprExporter
{
    public function name(): string
    {
        return 'sessions';
    }

    public function export(Authenticatable $user): array
    {
        return UserSession::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_active_at')
            ->get()
            ->map(fn (UserSession $session): array => [
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'device' => $session->device,
                'browser' => $session->browser,
                'platform' => $session->platform,
                'last_active_at' => $session->last_active_at?->toIso8601String(),
                'revoked_at' => $session->revoked_at?->toIso8601String(),
                'created_at' => $session->created_at?->toIso8601String(),
                // Never the session id, not even hashed: it identifies a
                // credential, and an export is a file that travels.
            ])
            ->all();
    }
}
