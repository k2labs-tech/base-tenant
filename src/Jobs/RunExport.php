<?php

declare(strict_types=1);

namespace Base\Tenant\Jobs;

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Files\FileCollection;
use Base\Tenant\Files\FileStore;
use Base\Tenant\Models\DataTransfer;
use Base\Tenant\Transfer\Csv;
use Base\Tenant\Transfer\Export;
use Base\Tenant\Transfer\TransferManager;
use Generator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Run an export's query in chunks and write the rows to a file.
 *
 * Queued rather than streamed from the request because an export that takes
 * four minutes is a request that times out, and because the produced file is
 * worth keeping: the user can close the tab and come back for it.
 */
class RunExport implements ShouldQueue
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

        $export = $transfers->exports()[$transfer->handler] ?? null;

        if (! $export) {
            $this->fail($transfer, "`{$transfer->handler}` is no longer a declared export handler.");

            return;
        }

        $transfer->update(['status' => DataTransfer::PROCESSING, 'started_at' => now()]);

        $path = tempnam(sys_get_temp_dir(), 'export').'.csv';

        try {
            $written = 0;

            // The query runs inside the transfer's account, so an export of a
            // tenant-scoped model returns that account's rows and no others.
            Tenant::runFor($transfer->account, function () use ($export, $transfer, $path, &$written): void {
                Csv::write($path, array_map(
                    fn (string $heading): string => __($heading),
                    $export->headings(),
                ), $this->rows($export, $transfer, $written));
            });

            $key = FileStore::TMP_PREFIX.'/'.Str::uuid();

            $files->disk()->put($key, file_get_contents($path));

            $file = $files->finalize(
                key: $key,
                collection: FileCollection::make('exports'),
                name: $transfer->name ?: $export->filename().'.csv',
                account: $transfer->account,
            );

            $transfer->update([
                'status' => DataTransfer::COMPLETED,
                'file_id' => $file->getKey(),
                'total_rows' => $written,
                'processed_rows' => $written,
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->fail($transfer, $exception->getMessage());

            throw $exception;
        } finally {
            @unlink($path);
        }
    }

    /**
     * Rows, in chunks, counting as it goes.
     *
     * `chunkById` and not `get()`: the whole point of exporting from a job is
     * that the table may not fit in memory.
     *
     * @return Generator<int, array<int, mixed>>
     */
    protected function rows(Export $export, DataTransfer $transfer, int &$written): Generator
    {
        $chunk = $export->chunkSize();
        $sinceUpdate = 0;

        foreach ($export->query()->lazyById($chunk) as $record) {
            $written++;
            $sinceUpdate++;

            if ($sinceUpdate >= $chunk) {
                $transfer->update(['processed_rows' => $written]);
                $sinceUpdate = 0;
            }

            yield $export->map($record);
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
