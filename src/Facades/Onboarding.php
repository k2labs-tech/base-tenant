<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Models\Account;
use Base\Tenant\Onboarding\OnboardingManager;
use Base\Tenant\Onboarding\OnboardingStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection<int, OnboardingStep> declared()
 * @method static Collection<int, array{step: OnboardingStep, complete: bool}> steps(Account|string|null $account = null)
 * @method static int progress(Account|string|null $account = null)
 * @method static bool isComplete(Account|string|null $account = null)
 * @method static bool shouldShow(Account|string|null $account = null)
 * @method static void dismiss(Account|string|null $account = null)
 * @method static void refresh(Account|string|null $account = null)
 *
 * @see OnboardingManager
 */
class Onboarding extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OnboardingManager::class;
    }
}
