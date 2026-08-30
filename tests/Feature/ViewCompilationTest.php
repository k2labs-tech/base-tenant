<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

/**
 * Every component tag is resolved while Blade compiles, so compiling the whole
 * view tree is what catches a tag no provider can answer -- the failure that
 * otherwise only shows up as a 500 on the page that happens to use it.
 */
test('every package view compiles', function () {
    $root = dirname(__DIR__, 2).'/resources/views';

    $failures = [];

    foreach (File::allFiles($root) as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        try {
            Blade::compileString(File::get($file->getPathname()));
        } catch (Throwable $e) {
            $failures[] = $file->getRelativePathname().': '.$e->getMessage();
        }
    }

    expect($failures)->toBe([]);
});
