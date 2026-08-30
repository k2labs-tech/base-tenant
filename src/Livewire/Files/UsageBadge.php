<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Files;

use Base\Tenant\Facades\Meter;
use Base\Tenant\Files\FileStore;
use Base\Tenant\Support\Module;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * How much of the storage allowance is gone, small enough to sit next to an
 * uploader.
 *
 * Reads the gauge, never the files table: the whole point of keeping a counter
 * is that this question costs one row instead of a scan over every file the
 * account has ever uploaded.
 */
class UsageBadge extends Component
{
    #[On('file-uploaded')]
    public function refresh(?string $fileId = null, ?string $collection = null): void
    {
        // Re-rendering is the work. The gauge is read again below.
    }

    public function render(): View
    {
        $enabled = Module::enabled(Module::METERING)
            && Meter::metrics()->has(FileStore::METRIC);

        return view('base-tenant::livewire.files.usage-badge', [
            'metered' => $enabled,
            'used' => $enabled ? Meter::current(FileStore::METRIC) : 0,
            'limit' => $enabled ? Meter::limit(FileStore::METRIC) : -1,
            'percentage' => $enabled ? Meter::percentage(FileStore::METRIC) : null,
        ]);
    }
}
