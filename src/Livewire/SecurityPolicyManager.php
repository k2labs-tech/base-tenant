<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire;

use Base\Tenant\Facades\Security;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Security\SecurityPolicySettings;
use Base\Tenant\Support\IpRange;
use Base\Tenant\Support\Module;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The account's own security rules.
 *
 * The screen's job is not only to save them: it is to stop an administrator
 * saving a rule that locks their account out. Both guards below exist because
 * the failure they prevent has no recovery path from inside the product.
 */
#[Layout('base-tenant::layouts.app')]
class SecurityPolicyManager extends Component
{
    public bool $requireTwoFactor = false;

    public int $twoFactorGraceHours = 72;

    /** One per line, as typed. */
    public string $allowedEmailDomains = '';

    public string $ipMode = SecurityPolicySettings::IP_OFF;

    /** One per line, as typed. */
    public string $ipAllowlist = '';

    public int $sessionTimeoutMinutes = 0;

    public function mount(): void
    {
        Module::ensure(Module::SECURITY);

        abort_unless(Auth::user()->hasPermission('security.view'), 403);

        $policy = Security::for($this->account());

        $this->requireTwoFactor = $policy->requireTwoFactor;
        $this->twoFactorGraceHours = $policy->twoFactorGraceHours;
        $this->allowedEmailDomains = implode("\n", $policy->allowedEmailDomains);
        $this->ipMode = $policy->ipMode;
        $this->ipAllowlist = implode("\n", $policy->ipAllowlist);
        $this->sessionTimeoutMinutes = $policy->sessionTimeoutMinutes;
    }

    public function render(): View
    {
        return view('base-tenant::livewire.security-policy-manager', [
            'canUpdate' => Auth::user()->hasPermission('security.update'),
            'currentIp' => request()->ip(),
            'membersWithoutTwoFactor' => $this->membersWithoutTwoFactor(),
        ]);
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasPermission('security.update'), 403);

        $domains = Security::normalizeDomains($this->lines($this->allowedEmailDomains));
        $allowlist = $this->lines($this->ipAllowlist);

        $this->resetValidation();

        $invalid = array_values(array_filter(
            $allowlist,
            fn (string $entry): bool => ! IpRange::isValidEntry($entry)
        ));

        if ($invalid !== []) {
            $this->addError('ipAllowlist', __('base-tenant::security.errors.invalid_entries', [
                'entries' => implode(', ', $invalid),
            ]));

            return;
        }

        // The guard that matters. Switching to `enforce` from an address the
        // list does not cover locks the account out of its own product, and
        // there is no way back from inside it.
        if ($this->ipMode === SecurityPolicySettings::IP_ENFORCE
            && $allowlist !== []
            && ! IpRange::matchesAny((string) request()->ip(), $allowlist)) {
            $this->addError('ipAllowlist', __('base-tenant::security.errors.would_lock_you_out', [
                'ip' => (string) request()->ip(),
            ]));

            return;
        }

        Security::update($this->account(), [
            'requireTwoFactor' => $this->requireTwoFactor,
            'twoFactorGraceHours' => max(0, $this->twoFactorGraceHours),
            'allowedEmailDomains' => $domains,
            'ipMode' => $this->ipMode,
            'ipAllowlist' => $allowlist,
            'sessionTimeoutMinutes' => max(0, $this->sessionTimeoutMinutes),
        ]);

        $this->allowedEmailDomains = implode("\n", $domains);
        $this->ipAllowlist = implode("\n", $allowlist);

        Flux::toast(text: __('base-tenant::security.saved'), variant: 'success');
    }

    /**
     * Add the address this request came from, so the usual way of filling the
     * list does not require the administrator to know their own public IP.
     */
    public function addCurrentIp(): void
    {
        abort_unless(Auth::user()->hasPermission('security.update'), 403);

        $entries = $this->lines($this->ipAllowlist);
        $ip = (string) request()->ip();

        if (! in_array($ip, $entries, true)) {
            $entries[] = $ip;
        }

        $this->ipAllowlist = implode("\n", $entries);
    }

    /**
     * How many people would be shut out the moment the grace period ends.
     * Showing the number before saving is the difference between a considered
     * decision and a surprise.
     */
    protected function membersWithoutTwoFactor(): int
    {
        return $this->account()
            ->users()
            ->whereNull('two_factor_confirmed_at')
            ->count();
    }

    /** @return array<int, string> */
    protected function lines(string $value): array
    {
        return array_values(array_filter(array_map(
            'trim',
            preg_split('/[\r\n,]+/', $value) ?: []
        ), fn (string $line): bool => $line !== ''));
    }

    protected function account(): Account
    {
        $account = Tenant::current();

        abort_if($account === null, 404);

        return $account;
    }
}
