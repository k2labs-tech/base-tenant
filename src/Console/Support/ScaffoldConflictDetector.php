<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Support;

use Illuminate\Support\Facades\File;

/**
 * Finds destination files that already exist, so nothing of the
 * application's is overwritten without being asked about first.
 */
class ScaffoldConflictDetector
{
    public const OVERWRITE = 'overwrite';

    public const SKIP = 'skip';

    /**
     * @param  array<int, array{source: string, destination: string, relative: string, transform: bool, group: string}>  $files
     * @return array<int, array{source: string, destination: string, relative: string, transform: bool, group: string}>
     */
    public function conflicts(array $files): array
    {
        return array_values(array_filter(
            $files,
            static fn (array $file): bool => File::exists($file['destination'])
        ));
    }

    /**
     * A conflict only matters when the destination differs from what would be
     * written; re-running scaffold over identical output is not a conflict.
     *
     * @param  array<int, array{source: string, destination: string, relative: string, transform: bool, group: string}>  $files
     * @return array<int, array{source: string, destination: string, relative: string, transform: bool, group: string}>
     */
    public function meaningfulConflicts(array $files, CodeTransformer $transformer): array
    {
        return array_values(array_filter(
            $this->conflicts($files),
            static function (array $file) use ($transformer): bool {
                $incoming = File::get($file['source']);

                if ($file['transform']) {
                    $incoming = $transformer->transform($incoming);
                }

                return File::get($file['destination']) !== $incoming;
            }
        ));
    }
}
