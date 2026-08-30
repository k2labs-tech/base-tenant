<?php

declare(strict_types=1);

namespace Base\Tenant\Gdpr;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Everything the application holds about one person, as a ZIP of JSON files.
 *
 * One file per domain rather than one big object: a person reading their own
 * export should be able to find the part they care about, and a domain that is
 * added later does not change the shape of everything else.
 */
class DataExportService
{
    /**
     * @return list<GdprExporter>
     */
    public function exporters(): array
    {
        $exporters = [];

        foreach (config('base-tenant.gdpr.exporters', []) as $class) {
            $exporter = app($class);

            if (! $exporter instanceof GdprExporter) {
                throw new RuntimeException("`{$class}` is not a ".GdprExporter::class.'.');
            }

            $exporters[] = $exporter;
        }

        return $exporters;
    }

    /**
     * Build the archive and return the path it was written to.
     */
    public function build(Authenticatable $user, ?string $path = null): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The zip extension is required to export personal data.');
        }

        $path ??= tempnam(sys_get_temp_dir(), 'gdpr').'.zip';

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create the archive at `{$path}`.");
        }

        // A manifest so the person can tell whether a domain was empty or was
        // never asked. Those are different answers to a legal request.
        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'subject' => $user->getAuthIdentifier(),
            'domains' => [],
        ];

        foreach ($this->exporters() as $exporter) {
            $data = $exporter->export($user);

            $zip->addFromString(
                $exporter->name().'.json',
                (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );

            $manifest['domains'][] = [
                'name' => $exporter->name(),
                'records' => is_array($data) && array_is_list($data) ? count($data) : 1,
            ];
        }

        $zip->addFromString(
            'manifest.json',
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        $zip->close();

        if (! File::exists($path)) {
            throw new RuntimeException('The archive was not written.');
        }

        return $path;
    }
}
