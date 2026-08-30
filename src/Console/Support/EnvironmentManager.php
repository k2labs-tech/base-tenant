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
    public function addBaseTenantVariables(
        bool $multiTeam,
        bool $subscriptions,
        string $stripeKey = '',
        string $stripeSecret = '',
        string $stripeProduct = '',
        string $stripePrice = '',
        string $flareKey = '',
    ): void {
        $envPath = $this->basePath.'/.env';

        if (! File::exists($envPath)) {
            throw new \RuntimeException('.env file not found');
        }

        $content = File::get($envPath);

        // Don't add if already exists
        if ($this->hasBaseTenantVariables()) {
            // Still update individual keys if provided
            $content = $this->updateExistingKeys($content, $stripeKey, $stripeSecret, $stripeProduct, $stripePrice, $flareKey);
            File::put($envPath, $content);

            return;
        }

        $variables = $this->getVariablesBlock($multiTeam, $subscriptions, $stripeKey, $stripeSecret, $stripeProduct, $stripePrice, $flareKey);

        $content .= "\n".$variables;

        File::put($envPath, $content);
    }

    /**
     * Set or update arbitrary keys in `.env`, keeping the rest of the file as
     * the developer left it.
     *
     * @param  array<string, string>  $values
     */
    public function setValues(array $values): void
    {
        $envPath = $this->basePath.'/.env';

        if (! File::exists($envPath)) {
            throw new \RuntimeException('.env file not found');
        }

        $content = File::get($envPath);

        foreach ($values as $key => $value) {
            $content = $this->setEnvValue($content, $key, $this->quote($value));
        }

        File::put($envPath, $content);
    }

    /**
     * Values with spaces or characters that would end the line early have to be
     * quoted, or the rest of the value is silently lost.
     */
    protected function quote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.\/:@-]+$/', $value)) {
            return $value;
        }

        return '"'.str_replace('"', '\"', $value).'"';
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
    protected function getVariablesBlock(
        bool $multiTeam,
        bool $subscriptions,
        string $stripeKey,
        string $stripeSecret,
        string $stripeProduct,
        string $stripePrice,
        string $flareKey,
    ): string {
        $multiTeamValue = $multiTeam ? 'true' : 'false';
        $subscriptionsValue = $subscriptions ? 'true' : 'false';

        $block = <<<ENV

# Base Tenant Package Configuration
BASE_TENANT_MULTI_TEAM={$multiTeamValue}
BASE_TENANT_HOME_URL=base-tenant.dashboard
BASE_TENANT_SUBSCRIPTION_ENABLED={$subscriptionsValue}
BASE_TENANT_SUBSCRIPTION_DEFAULT_PRODUCT={$stripeProduct}
BASE_TENANT_SUBSCRIPTION_DEFAULT_PRICE={$stripePrice}
BASE_TENANT_SUBSCRIPTION_TRIAL_DAYS=14

# Stripe Configuration
STRIPE_KEY={$stripeKey}
STRIPE_SECRET={$stripeSecret}
STRIPE_WEBHOOK_SECRET=
ENV;

        if ($flareKey) {
            $block .= "\n\n# Flare Error Tracking\nFLARE_KEY={$flareKey}";
        }

        return $block;
    }

    /**
     * Update existing keys in .env content
     */
    protected function updateExistingKeys(
        string $content,
        string $stripeKey,
        string $stripeSecret,
        string $stripeProduct,
        string $stripePrice,
        string $flareKey,
    ): string {
        if ($stripeKey) {
            $content = $this->setEnvValue($content, 'STRIPE_KEY', $stripeKey);
        }

        if ($stripeSecret) {
            $content = $this->setEnvValue($content, 'STRIPE_SECRET', $stripeSecret);
        }

        if ($stripeProduct) {
            $content = $this->setEnvValue($content, 'BASE_TENANT_SUBSCRIPTION_DEFAULT_PRODUCT', $stripeProduct);
        }

        if ($stripePrice) {
            $content = $this->setEnvValue($content, 'BASE_TENANT_SUBSCRIPTION_DEFAULT_PRICE', $stripePrice);
        }

        if ($flareKey) {
            $content = $this->setEnvValue($content, 'FLARE_KEY', $flareKey);
        }

        return $content;
    }

    /**
     * Set or update a single .env variable
     */
    protected function setEnvValue(string $content, string $key, string $value): string
    {
        $pattern = "/^{$key}=.*/m";

        if (preg_match($pattern, $content)) {
            return preg_replace($pattern, "{$key}={$value}", $content);
        }

        return $content."\n{$key}={$value}";
    }
}
