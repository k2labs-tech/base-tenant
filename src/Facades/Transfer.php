<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Models\DataTransfer;
use Base\Tenant\Models\File;
use Base\Tenant\Transfer\Export;
use Base\Tenant\Transfer\Import;
use Base\Tenant\Transfer\TransferManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array<string, Import> imports()
 * @method static array<string, Export> exports()
 * @method static DataTransfer import(string $handler, File $source, array $mapping, string|null $name = null)
 * @method static DataTransfer export(string $handler, array $options = [], string|null $name = null)
 * @method static array<string, string> guessMapping(Import $import, array $headings)
 * @method static string template(Import $import)
 *
 * @see TransferManager
 */
class Transfer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TransferManager::class;
    }
}
