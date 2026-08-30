<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Models\Account;
use Base\Tenant\Sequences\SequenceManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string next(string $key, string|null $format = null, string $period = 'none', Account|string|null $account = null)
 * @method static string peek(string $key, string|null $format = null, string $period = 'none', Account|string|null $account = null)
 * @method static int current(string $key, string $period = 'none', Account|string|null $account = null)
 * @method static void setNext(string $key, int $value, string $period = 'none', Account|string|null $account = null)
 * @method static string render(string $format, int $number, string $period = '')
 *
 * @see SequenceManager
 */
class Sequence extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SequenceManager::class;
    }
}
