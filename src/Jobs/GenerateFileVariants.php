<?php

declare(strict_types=1);

namespace Base\Tenant\Jobs;

use Base\Tenant\Facades\Meter;
use Base\Tenant\Files\FileStore;
use Base\Tenant\Files\ImageVariants;
use Base\Tenant\Models\File;
use Base\Tenant\Support\Module;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Derive the renditions of an image after the upload has been recorded.
 *
 * Queued rather than inline because the uploader should return as soon as the
 * file exists: a gallery can show the original while the thumbnails catch up,
 * and a slow resize should not turn into a failed upload.
 *
 * Only the file id travels, not the model. The tenant is carried across the
 * queue boundary by QueueTenancy, so the scoped lookup here resolves the same
 * account it was dispatched from.
 */
class GenerateFileVariants implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, array{width?: int, height?: int, fit?: string}>  $variants
     */
    public function __construct(
        public readonly string $fileId,
        public readonly array $variants,
    ) {}

    public function handle(ImageVariants $renderer): void
    {
        $file = File::query()->acrossAccounts()->find($this->fileId);

        if (! $file) {
            return;
        }

        $written = $renderer->generate($file, $this->variants);

        if ($written === []) {
            return;
        }

        $file->variants = $written;
        $file->saveQuietly();

        $this->meterRenditions($file, $written);
    }

    /**
     * Renditions take space too. Counting only originals would make the gauge
     * disagree with the bill from the object store, and by a factor that grows
     * with every variant the product adds.
     *
     * @param  array<string, string>  $written
     */
    protected function meterRenditions(File $file, array $written): void
    {
        if (! Module::enabled(Module::METERING) || ! $file->account) {
            return;
        }

        $bytes = 0;

        foreach ($written as $path) {
            $bytes += (int) $file->storage()->size($path);
        }

        if ($bytes > 0) {
            Meter::for($file->account)->increment(FileStore::METRIC, $bytes, ['subject' => $file]);
        }
    }
}
