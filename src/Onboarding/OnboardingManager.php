<?php

declare(strict_types=1);

namespace Base\Tenant\Onboarding;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Account;
use Base\Tenant\Support\Module;
use Illuminate\Support\Collection;

/**
 * The checklist an account works through when it starts.
 *
 *     Onboarding::steps();          // with each one's state
 *     Onboarding::progress();       // 0-100
 *     Onboarding::refresh($account);
 */
class OnboardingManager
{
    /** @var array<string, array<string, bool>> */
    protected array $memo = [];

    /**
     * @return Collection<int, OnboardingStep>
     */
    public function declared(): Collection
    {
        return collect(config('base-tenant.onboarding.steps', []))
            ->map(fn (array $config, string $key): OnboardingStep => OnboardingStep::fromConfig($key, $config))
            ->values();
    }

    /**
     * Every step with whether this account has done it.
     *
     * @return Collection<int, array{step: OnboardingStep, complete: bool}>
     */
    public function steps(Account|string|null $account = null): Collection
    {
        $account = $this->account($account);

        if (! $account) {
            return new Collection;
        }

        $state = $this->state($account);

        return $this->declared()->map(fn (OnboardingStep $step): array => [
            'step' => $step,
            'complete' => $state[$step->key] ?? false,
        ]);
    }

    public function progress(Account|string|null $account = null): int
    {
        $steps = $this->steps($account);

        if ($steps->isEmpty()) {
            return 100;
        }

        return (int) floor($steps->where('complete', true)->count() / $steps->count() * 100);
    }

    public function isComplete(Account|string|null $account = null): bool
    {
        return $this->progress($account) >= 100;
    }

    /**
     * Should the checklist be on screen at all?
     */
    public function shouldShow(Account|string|null $account = null): bool
    {
        if (! Module::enabled(Module::ONBOARDING)) {
            return false;
        }

        $account = $this->account($account);

        // `onboarded_at` is both "finished" and "dismissed": once it is
        // stamped the widget is gone for good, and the two cases do not need
        // to be told apart after the fact.
        if (! $account || $account->onboarded_at !== null) {
            return false;
        }

        return $this->declared()->isNotEmpty();
    }

    /**
     * Stamp the account as done, either because it finished or because the
     * user said they had had enough.
     */
    public function dismiss(Account|string|null $account = null): void
    {
        $account = $this->account($account);

        $account?->forceFill(['onboarded_at' => now()])->save();
    }

    /**
     * Re-run the checks for an account, forgetting what was memoised.
     */
    public function refresh(Account|string|null $account = null): void
    {
        $account = $this->account($account);

        if ($account) {
            unset($this->memo[$account->getKey()]);
        }
    }

    /**
     * The checks, run once per account per request.
     *
     * A check usually costs a query, the widget asks for every one of them,
     * and several of them appear twice on a dashboard. Without the memo the
     * checklist is the most expensive thing on the page.
     *
     * @return array<string, bool>
     */
    protected function state(Account $account): array
    {
        return $this->memo[$account->getKey()] ??= $this->declared()
            ->mapWithKeys(fn (OnboardingStep $step): array => [
                $step->key => $step->isComplete($account),
            ])
            ->all();
    }

    protected function account(Account|string|null $account): ?Account
    {
        if ($account instanceof Account) {
            return $account;
        }

        if (is_string($account)) {
            $model = config('base-tenant.models.account', Account::class);

            return $model::find($account);
        }

        return Tenant::current();
    }
}
