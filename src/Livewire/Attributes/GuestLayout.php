<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Attributes;

use Attribute;
use Livewire\Features\SupportPageComponents\BaseLayout;

/**
 * The layout of the screens a person sees before they are in: login,
 * registration, password reset, verification, second factor.
 *
 * `#[Layout('base-tenant::layouts.guest')]` fixed it at compile time, and an
 * attribute argument cannot read configuration, so `layouts.guest` in the
 * config was never honoured. Livewire builds its attributes when it renders,
 * after the configuration is loaded, which is when this one reads it.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class GuestLayout extends BaseLayout
{
    public const DEFAULT = 'base-tenant::layouts.guest';

    public function __construct(array $params = [])
    {
        parent::__construct(self::view(), $params);
    }

    public static function view(): string
    {
        $layout = config('base-tenant.layouts.guest', self::DEFAULT);

        return is_string($layout) && $layout !== '' ? $layout : self::DEFAULT;
    }
}
