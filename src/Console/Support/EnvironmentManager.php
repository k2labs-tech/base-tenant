<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Support;

use Illuminate\Support\Facades\File;

class EnvironmentManager
{
    protected string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Add BASE_TENANT variables to .env
     */
    public function addBaseTenantVariables(bool $multiTeam, bool $subscriptions): void
    {
        $envPath = $this->basePath.'/.env';

        if (! File::exists($envPath)) {
            throw new \RuntimeException('.env file not found');
        }

        $content = File::get($envPath);

        // Don't add if already exists
        if ($this->hasBaseTenantVariables()) {
            return;
        }

        $variables = $this->getVariablesBlock($multiTeam, $subscriptions);

        // Append to end of file
        $content .= "\n".$variables;

        File::put($envPath, $content);
    }

    /**
     * Check if .env already has BASE_TENANT variables
     */
    public function hasBaseTenantVariables(): bool
    {
        $envPath = $this->basePath.'/.env';

        if (! File::exists($envPath)) {
            return false;
        }

        $content = File::get($envPath);

        return str_contains($content, 'BASE_TENANT_');
    }

    /**
     * Get environment variables block
     */
    protected function getVariablesBlock(bool $multiTeam, bool $subscriptions): string
    {
        $multiTeamValue = $multiTeam ? 'true' : 'false';
        $subscriptionsValue = $subscriptions ? 'true' : 'false';

        return <<<ENV

# Base Tenant Package Configuration
BASE_TENANT_MULTI_TEAM={$multiTeamValue}
BASE_TENANT_HOME_URL=base-tenant.dashboard
BASE_TENANT_SUBSCRIPTION_ENABLED={$subscriptionsValue}
BASE_TENANT_SUBSCRIPTION_DEFAULT_PRODUCT=
BASE_TENANT_SUBSCRIPTION_DEFAULT_PRICE=
BASE_TENANT_SUBSCRIPTION_TRIAL_DAYS=14

# Stripe Configuration (when subscriptions enabled)
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
ENV;
    }
}
