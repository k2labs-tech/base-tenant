<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Models\WaitlistSignup;
use Base\Tenant\Presale\PresaleManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isOpen()
 * @method static bool registrationClosed()
 * @method static int seats()
 * @method static int seatsLeft()
 * @method static bool soldOut()
 * @method static WaitlistSignup join(string $email, array $attributes = [])
 * @method static int waiting()
 *
 * @see PresaleManager
 */
class Presale extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PresaleManager::class;
    }
}
