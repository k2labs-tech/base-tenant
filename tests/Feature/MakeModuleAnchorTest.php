<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * El generador edita `config/base-tenant.php`, que es un array PHP: escribir
 * el bloque donde no toca no lo deja desordenado, lo deja sin arrancar. Y lo
 * hacía anunciando que había ido bien.
 */
beforeEach(function () {
    $this->configPath = config_path('base-tenant.php');

    File::ensureDirectoryExists(dirname($this->configPath));
    File::copy(dirname(__DIR__, 2).'/config/base-tenant.php', $this->configPath);

    // El generador escribe en la aplicación, que aquí es el esqueleto de
    // Testbench: sin esta foto, un módulo generado en un test se queda en el
    // vendor y se lo encuentra el siguiente.
    $this->before = ficherosDeLaAplicacion();
    $this->routes = File::exists(base_path('routes/web.php'))
        ? File::get(base_path('routes/web.php'))
        : null;
});

afterEach(function () {
    File::delete($this->configPath);

    foreach (array_diff(ficherosDeLaAplicacion(), $this->before) as $nuevo) {
        File::delete($nuevo);
    }

    foreach (['app/Livewire/Booking', 'resources/views/livewire/bookings', 'docs/agents/app'] as $directorio) {
        if (File::isDirectory(base_path($directorio)) && File::files(base_path($directorio)) === []) {
            File::deleteDirectory(base_path($directorio));
        }
    }

    if ($this->routes !== null) {
        File::put(base_path('routes/web.php'), $this->routes);
    }
});

/**
 * @return array<int, string>
 */
function ficherosDeLaAplicacion(): array
{
    $ficheros = [];

    foreach (['app', 'database', 'docs', 'lang', 'resources', 'tests'] as $raiz) {
        if (! File::isDirectory(base_path($raiz))) {
            continue;
        }

        $ficheros = [...$ficheros, ...File::allFiles(base_path($raiz))];
    }

    return array_map(static fn ($fichero): string => (string) $fichero, $ficheros);
}

function esPhpValido(string $ruta): bool
{
    return str_contains((string) shell_exec('php -l '.escapeshellarg($ruta).' 2>&1'), 'No syntax errors');
}

test('el config publicado trae el ancla que el generador necesita', function () {
    expect(File::get($this->configPath))->toContain('// base-tenant:permissions');
});

test('el bloque de permisos cae dentro del array y el fichero sigue siendo válido', function () {
    $this->artisan('k2labs-base:make-module', [
        'name' => 'Booking',
        '--fields' => 'title:string',
    ])->assertSuccessful();

    $contenido = File::get($this->configPath);

    expect(esPhpValido($this->configPath))->toBeTrue()
        ->and($contenido)->toContain("'bookings' => [")
        ->and(mb_strpos($contenido, "'bookings' => ["))->toBeLessThan(mb_strpos($contenido, '// base-tenant:permissions'));

    $permisos = require $this->configPath;

    expect($permisos['permissions'])->toHaveKey('bookings');
})->skip(fn (): bool => ! is_dir(base_path('app')), 'Necesita un esqueleto de aplicación con app/.');

test('sin ancla el generador se niega a escribir en lugar de romper el fichero', function () {
    $original = File::get($this->configPath);

    File::put($this->configPath, str_replace('// base-tenant:permissions', '', $original));

    $this->artisan('k2labs-base:make-module', [
        'name' => 'Booking',
        '--fields' => 'title:string',
    ])->assertFailed();

    expect(esPhpValido($this->configPath))->toBeTrue()
        ->and(File::get($this->configPath))->not->toContain('bookings');
});
