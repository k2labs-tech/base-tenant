<?php

declare(strict_types=1);

namespace Base\Tenant\Transfer;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Jobs\RunExport;
use Base\Tenant\Jobs\RunImport;
use Base\Tenant\Models\DataTransfer;
use Base\Tenant\Models\File;
use Base\Tenant\Support\Module;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Starting imports and exports, and guessing how a file's columns line up with
 * a handler's fields.
 *
 *     Transfer::import('guests', $uploadedFile, $mapping);
 *     Transfer::export('guests');
 */
class TransferManager
{
    /**
     * The declared import handlers, keyed by the name used in a URL.
     *
     * @return array<string, Import>
     */
    public function imports(): array
    {
        return $this->resolveAll(config('base-tenant.transfer.imports', []), Import::class);
    }

    /**
     * @return array<string, Export>
     */
    public function exports(): array
    {
        return $this->resolveAll(config('base-tenant.transfer.exports', []), Export::class);
    }

    public function import(string $handler, File $source, array $mapping, ?string $name = null): DataTransfer
    {
        Module::ensure(Module::TRANSFER);

        $import = $this->imports()[$handler] ?? throw new InvalidArgumentException(
            "`{$handler}` is not a declared import handler."
        );

        // Las claves del mapeo son los campos; los valores son las columnas
        // del fichero. Comparar contra los valores compara nombres de campo
        // con nombres de columna, y no coincide nunca.
        $mapped = array_keys(array_filter($mapping, fn (?string $heading): bool => (string) $heading !== ''));

        $missing = array_diff($import->required(), $mapped);

        if ($missing !== []) {
            throw new InvalidArgumentException(
                'These fields have to be mapped before the import can run: '.implode(', ', $missing).'.'
            );
        }

        $transfer = DataTransfer::create([
            'type' => DataTransfer::IMPORT,
            'handler' => $handler,
            'status' => DataTransfer::PENDING,
            'name' => $name ?? $source->name,
            'file_id' => $source->getKey(),
            'mapping' => $mapping,
            'created_by' => Auth::id(),
        ]);

        RunImport::dispatch($transfer->getKey());

        return $transfer;
    }

    public function export(string $handler, array $options = [], ?string $name = null): DataTransfer
    {
        Module::ensure(Module::TRANSFER);

        $export = $this->exports()[$handler] ?? throw new InvalidArgumentException(
            "`{$handler}` is not a declared export handler."
        );

        $transfer = DataTransfer::create([
            'type' => DataTransfer::EXPORT,
            'handler' => $handler,
            'status' => DataTransfer::PENDING,
            'name' => $name ?? $export->filename().'.csv',
            'options' => $options ?: null,
            'created_by' => Auth::id(),
        ]);

        RunExport::dispatch($transfer->getKey());

        return $transfer;
    }

    /**
     * Line a file's headings up with a handler's fields.
     *
     * Exact match first, then a loose one that ignores case, accents and
     * punctuation, so `E-Mail`, `email` and `Correo electrónico` all find
     * `email` when the label says so. Anything it cannot place is left blank
     * for the user rather than guessed: a wrong guess that looks right is
     * worse than an obvious gap.
     *
     * @param  list<string>  $headings
     * @return array<string, string> field => heading
     */
    public function guessMapping(Import $import, array $headings): array
    {
        $normalised = [];

        foreach ($headings as $heading) {
            $normalised[$this->normalise($heading)] = $heading;
        }

        $mapping = [];

        foreach ($import->columns() as $field => $label) {
            $candidates = [
                $this->normalise($field),
                $this->normalise($label),
                $this->normalise(__($label)),
            ];

            foreach ($candidates as $candidate) {
                if (isset($normalised[$candidate])) {
                    $mapping[$field] = $normalised[$candidate];

                    continue 2;
                }
            }

            $mapping[$field] = '';
        }

        return $mapping;
    }

    /**
     * A file with only the headings, for someone who would rather start from
     * the right shape than guess it.
     */
    public function template(Import $import): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.csv';

        Csv::write($path, array_map(fn (string $label): string => __($label), $import->columns()), []);

        return $path;
    }

    /**
     * @param  array<string, class-string>  $declared
     * @return array<string, object>
     */
    protected function resolveAll(array $declared, string $contract): array
    {
        $handlers = [];

        foreach ($declared as $name => $class) {
            if (! is_subclass_of($class, $contract)) {
                throw new InvalidArgumentException("`{$class}` is not a {$contract}.");
            }

            $handlers[$name] = app($class);
        }

        return $handlers;
    }

    /**
     * Strip everything that varies between two spellings of the same heading.
     */
    protected function normalise(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($value))) ?? '';
    }

    /**
     * The account a transfer belongs to, for the jobs that run outside a
     * request.
     */
    public function accountFor(DataTransfer $transfer): ?string
    {
        return $transfer->account_id ?? Tenant::currentId();
    }
}
