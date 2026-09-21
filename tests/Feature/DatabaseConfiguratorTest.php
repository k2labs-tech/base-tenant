<?php

declare(strict_types=1);

use Base\Tenant\Console\Support\DatabaseConfigurator;
use Base\Tenant\Console\Support\EnvironmentManager;

function configurador(string $base): DatabaseConfigurator
{
    return new DatabaseConfigurator($base, new EnvironmentManager($base));
}

test('una base sqlite que no existe no conecta, y basta con crear el fichero', function () {
    $base = sys_get_temp_dir().'/base-tenant-'.uniqid();
    $configurador = configurador($base);
    $ruta = $configurador->defaultSqlitePath();

    $ajustes = ['driver' => 'sqlite', 'database' => $ruta, 'host' => '', 'port' => '', 'username' => '', 'password' => ''];

    expect($configurador->test($ajustes))->toBeFalse()
        ->and($configurador->lastError())->toContain('does not exist');

    $configurador->ensureSqliteFileExists($ruta);

    expect(file_exists($ruta))->toBeTrue()
        ->and($configurador->test($ajustes))->toBeTrue();

    File::deleteDirectory($base);
});
