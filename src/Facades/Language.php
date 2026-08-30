<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Languages\LanguageManager;
use Base\Tenant\Models\Language as LanguageModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Collection<int, LanguageModel> enabled()
 * @method static Collection<int, LanguageModel> all()
 * @method static string default()
 * @method static list<string> codes()
 * @method static bool isEnabled(string $code)
 * @method static string resolve(string|null $code)
 * @method static LanguageModel enable(string $code)
 * @method static LanguageModel disable(string $code)
 * @method static LanguageModel setDefault(string $code)
 * @method static LanguageModel reorder(string $code, int $position)
 * @method static void flush()
 *
 * @see LanguageManager
 */
class Language extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LanguageManager::class;
    }
}
