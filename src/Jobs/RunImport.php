<?php

declare(strict_types=1);

namespace Base\Tenant\Jobs;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Files\FileCollection;
use Base\Tenant\Files\FileStore;
use Base\Tenant\Models\DataTransfer;
use Base\Tenant\Models\File;
use Base\Tenant\Transfer\Csv;
use Base\Tenant\Transfer\Import;
use Base\Tenant\Transfer\TransferManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Read the uploaded file, validate every row, store the ones that pass and
 * write the ones that do not into a CSV the user can correct and re-upload.
 *
 * Partial success is the normal outcome of a real import. Refusing the whole
 * file because row 4,312 has a malformed phone number means the customer
 * re-uploads eleven times; letting the bad row through silently means they
 * find out months later.
 */
class RunImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public readonly string $transferId) {}

    public function handle(TransferManager $transfers, FileStore $files): void
    {
        $transfer = DataTransfer::query()->acrossAccounts()->find($this->transferId);

        if (! $transfer || $transfer->status !== DataTransfer::PENDING) {
            return;
        }

        $import = $transfers->imports()[$transfer->handler] ?? null;

        if (! $import) {
            $this->fail($transfer, "`{$transfer->handler}` is no longer a declared import handler.");

            return;
        }

        $source = $transfer->file;

        if (! $source) {
            $this->fail($transfer, 'The uploaded file is gone.');

            return;
        }

        $transfer->update([
            'status' => DataTransfer::PROCESSING,
            'started_at' => now(),
        ]);

        // The file lives on the object store; the parser needs a path. A
        // temporary local copy is the only point at which the whole file is on
        // this machine, and it is removed in the finally below.
        $local = tempnam(sys_get_temp_dir(), 'import');

        try {
            file_put_contents($local, $source->storage()->get($source->path));

            $transfer->update(['total_rows' => Csv::count($local)]);

            // Explicitly inside the transfer's account, not relying on what
            // the queue happened to propagate: a retried job, or one
            // dispatched from a command, would otherwise store every row
            // against no account at all.
            Tenant::runFor($transfer->account, function () use ($transfer, $import, $local, $files): void {
                [$processed, $failures] = $this->run($transfer, $import, $local);

                $errorFile = $failures === [] ? null : $this->writeErrors($transfer, $import, $failures, $files);

                $transfer->update([
                    'status' => DataTransfer::COMPLETED,
                    'processed_rows' => $processed,
                    'failed_rows' => count($failures),
                    'error_file_id' => $errorFile?->getKey(),
                    'finished_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            $this->fail($transfer, $exception->getMessage());

            throw $exception;
        } finally {
            @unlink($local);
        }
    }

    /**
     * @return array{0: int, 1: list<array{line: int, row: array<string, string>, errors: string}>}
     */
    protected function run(DataTransfer $transfer, Import $import, string $path): array
    {
        $mapping = array_filter($transfer->mapping ?? []);
        $rules = $import->rules();

        $processed = 0;
        $failures = [];
        $buffer = [];

        foreach (Csv::rows($path) as $line => $raw) {
            $row = [];

            foreach ($mapping as $field => $heading) {
                $row[$field] = $raw[$heading] ?? null;
            }

            $row = $import->prepare($row);

            $validator = Validator::make($row, $rules);

            if ($validator->fails()) {
                $failures[] = [
                    'line' => $line,
                    'row' => $raw,
                    'errors' => implode(' ', $validator->errors()->all()),
                ];

                continue;
            }

            $buffer[] = $validator->validated();

            if (count($buffer) >= $import->chunkSize()) {
                $processed += $this->persist($import, $buffer, $failures);
                $buffer = [];

                $transfer->update(['processed_rows' => $processed]);
            }
        }

        $processed += $this->persist($import, $buffer, $failures);

        return [$processed, $failures];
    }

    /**
     * Store a chunk in one transaction, and fall back to row by row if it
     * fails.
     *
     * The transaction is what makes the import fast. The fallback is what
     * keeps one bad row from discarding the 499 good ones next to it — the
     * whole chunk would otherwise roll back and the user would be told nothing
     * about which row caused it.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{line: int, row: array<string, string>, errors: string}>  $failures
     */
    protected function persist(Import $import, array $rows, array &$failures): int
    {
        if ($rows === []) {
            return 0;
        }

        try {
            DB::transaction(function () use ($import, $rows): void {
                foreach ($rows as $row) {
                    $import->persist($row);
                }
            });

            return count($rows);
        } catch (Throwable) {
            $stored = 0;

            foreach ($rows as $row) {
                try {
                    $import->persist($row);
                    $stored++;
                } catch (Throwable $exception) {
                    $failures[] = [
                        'line' => 0,
                        'row' => $row,
                        'errors' => $exception->getMessage(),
                    ];
                }
            }

            return $stored;
        }
    }

    /**
     * The rejected rows, as a file with their original columns plus a reason.
     *
     * Same columns as the source on purpose: the user fixes the reason column,
     * deletes it, and re-uploads the same file.
     *
     * @param  list<array{line: int, row: array<string, string>, errors: string}>  $failures
     */
    protected function writeErrors(DataTransfer $transfer, Import $import, array $failures, FileStore $files): ?File
    {
        $headings = [...array_keys($failures[0]['row']), __('base-tenant::transfer.error_column'), __('base-tenant::transfer.line_column')];

        $path = tempnam(sys_get_temp_dir(), 'errors').'.csv';

        Csv::write($path, $headings, array_map(
            fn (array $failure): array => [...array_values($failure['row']), $failure['errors'], $failure['line'] ?: ''],
            $failures,
        ));

        try {
            $key = FileStore::TMP_PREFIX.'/'.Str::uuid();

            $files->disk()->put($key, file_get_contents($path));

            return $files->finalize(
                key: $key,
                collection: FileCollection::make('transfer-errors'),
                name: 'errores-'.$transfer->getKey().'.csv',
                account: $transfer->account,
            );
        } finally {
            @unlink($path);
        }
    }

    protected function fail(DataTransfer $transfer, string $message): void
    {
        $transfer->update([
            'status' => DataTransfer::FAILED,
            'message' => $message,
            'finished_at' => now(),
        ]);
    }
}
