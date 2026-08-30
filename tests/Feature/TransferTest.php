<?php

declare(strict_types=1);

use Base\Tenant\Exceptions\ModuleDisabledException;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Facades\Transfer;
use Base\Tenant\Files\FileCollection;
use Base\Tenant\Files\FileStore;
use Base\Tenant\Jobs\RunExport;
use Base\Tenant\Jobs\RunImport;
use Base\Tenant\Metering\MetricRegistry;
use Base\Tenant\Models\DataTransfer;
use Base\Tenant\Models\File;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Transfer\Csv;
use Base\Tenant\Transfer\Export;
use Base\Tenant\Transfer\Import;
use Base\Tenant\Transfer\TransferManager;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Un import y un export de verdad sobre una tabla que ya existe y lleva
 * `BelongsToAccount`: las invitaciones. Probar contra un modelo inventado
 * dejaría sin comprobar justo lo que importa, que el aislamiento se mantiene.
 */
class InvitacionImport extends Import
{
    public function columns(): array
    {
        return ['email' => 'Correo', 'token' => 'Token'];
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'token' => ['nullable', 'string'],
        ];
    }

    public function persist(array $row): void
    {
        UserInvite::create([
            'email' => $row['email'],
            'token' => $row['token'] ?: Str::random(64),
            'expires_at' => now()->addWeek(),
        ]);
    }

    public function chunkSize(): int
    {
        return 2;
    }
}

class InvitacionExport extends Export
{
    public function query(): Builder
    {
        return UserInvite::query()->orderBy('email');
    }

    public function headings(): array
    {
        return ['Correo', 'Caduca'];
    }

    public function map(Model $record): array
    {
        return [$record->email, $record->expires_at];
    }

    public function chunkSize(): int
    {
        return 2;
    }
}

beforeEach(function () {
    Storage::fake('s3');

    config([
        'base-tenant.files.driver' => 'local',
        'base-tenant.files.disk' => 's3',
        'base-tenant.files.collections' => ['library' => ['accepts' => []]],
        'base-tenant.transfer.imports' => ['invitaciones' => InvitacionImport::class],
        'base-tenant.transfer.exports' => ['invitaciones' => InvitacionExport::class],
        'base-tenant.metering.metrics' => [
            'storage.bytes' => ['type' => 'gauge', 'feature' => 'max_storage_gb', 'scale' => 1073741824],
        ],
    ]);

    app(MetricRegistry::class)->flush();
});

/**
 * Deja un CSV en el almacén y devuelve la fila de fichero.
 */
function ficheroCsv(string $contenido, $cuenta): File
{
    $clave = FileStore::TMP_PREFIX.'/'.Str::uuid();

    Storage::disk('s3')->put($clave, $contenido);

    return app(FileStore::class)->finalize(
        key: $clave,
        collection: FileCollection::make('library'),
        name: 'datos.csv',
        account: $cuenta,
    );
}

test('el olfateo distingue la coma del punto y coma', function () {
    $coma = tempnam(sys_get_temp_dir(), 'c').'.csv';
    $puntoYComa = tempnam(sys_get_temp_dir(), 'p').'.csv';

    file_put_contents($coma, "a,b,c\n1,2,3\n");
    file_put_contents($puntoYComa, "a;b;c\n1;2;3\n");

    expect(Csv::sniff($coma))->toBe(',')
        ->and(Csv::sniff($puntoYComa))->toBe(';')
        ->and(Csv::headers($puntoYComa))->toBe(['a', 'b', 'c']);

    @unlink($coma);
    @unlink($puntoYComa);
});

/**
 * Un BOM sin quitar convierte la primera cabecera en "\u{FEFF}email", que
 * después no casa con nada al mapear columnas y se lee como un fichero al que
 * le falta la primera columna.
 */
test('la marca de orden de bytes no se cuela en la primera cabecera', function () {
    $ruta = tempnam(sys_get_temp_dir(), 'bom').'.csv';

    file_put_contents($ruta, "\xEF\xBB\xBFemail,name\nada@ejemplo.test,Ada\n");

    expect(Csv::headers($ruta))->toBe(['email', 'name']);

    @unlink($ruta);
});

test('una fila con menos columnas no rompe la lectura', function () {
    $ruta = tempnam(sys_get_temp_dir(), 'rag').'.csv';

    file_put_contents($ruta, "a,b,c\n1,2\n1,2,3,4\n");

    $filas = iterator_to_array(Csv::rows($ruta));

    expect($filas)->toHaveCount(2)
        ->and(array_values($filas)[0])->toBe(['a' => '1', 'b' => '2', 'c' => ''])
        ->and(array_values($filas)[1])->toBe(['a' => '1', 'b' => '2', 'c' => '3']);

    @unlink($ruta);
});

test('el mapeo automático encuentra la columna aunque cambie la forma de escribirla', function () {
    $mapeo = Transfer::guessMapping(new InvitacionImport, ['E-Mail', 'TOKEN']);

    expect($mapeo['email'])->toBe('E-Mail')
        ->and($mapeo['token'])->toBe('TOKEN');
});

/**
 * Una adivinanza equivocada que parece correcta es peor que un hueco evidente.
 */
test('lo que no se puede colocar se deja en blanco en vez de adivinarse', function () {
    $mapeo = Transfer::guessMapping(new InvitacionImport, ['columna_rara']);

    expect($mapeo['email'])->toBe('')
        ->and($mapeo['token'])->toBe('');
});

test('un campo obligatorio sin mapear impide arrancar', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () use ($cuenta) {
        $fichero = ficheroCsv("a,b\n1,2\n", $cuenta);

        expect(fn () => Transfer::import('invitaciones', $fichero, ['email' => '', 'token' => 'b']))
            ->toThrow(InvalidArgumentException::class, 'email');
    });
});

test('un import completo guarda las filas dentro de su cuenta', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () use ($cuenta) {
        $fichero = ficheroCsv("Correo,Token\nuna@ejemplo.test,\notra@ejemplo.test,\n", $cuenta);

        $traspaso = Transfer::import('invitaciones', $fichero, ['email' => 'Correo', 'token' => 'Token']);

        (new RunImport($traspaso->getKey()))->handle(app(TransferManager::class), app(FileStore::class));

        $traspaso->refresh();

        expect($traspaso->status)->toBe(DataTransfer::COMPLETED)
            ->and($traspaso->total_rows)->toBe(2)
            ->and($traspaso->processed_rows)->toBe(2)
            ->and($traspaso->failed_rows)->toBe(0);

        expect(UserInvite::count())->toBe(2);
    });
});

/**
 * El resultado normal de un import de verdad: unas filas entran y otras no.
 * Rechazar el fichero entero por una fila mala obliga a subirlo once veces.
 */
test('las filas malas se rechazan sin llevarse por delante a las buenas', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () use ($cuenta) {
        $fichero = ficheroCsv(
            "Correo,Token\nbuena@ejemplo.test,\nesto-no-es-un-correo,\notra@ejemplo.test,\n",
            $cuenta,
        );

        $traspaso = Transfer::import('invitaciones', $fichero, ['email' => 'Correo', 'token' => 'Token']);

        (new RunImport($traspaso->getKey()))->handle(app(TransferManager::class), app(FileStore::class));

        $traspaso->refresh();

        expect($traspaso->status)->toBe(DataTransfer::COMPLETED)
            ->and($traspaso->processed_rows)->toBe(2)
            ->and($traspaso->failed_rows)->toBe(1)
            ->and($traspaso->hasErrors())->toBeTrue();

        expect(UserInvite::count())->toBe(2);
    });
});

/**
 * Las filas rechazadas vuelven como fichero con sus columnas originales más el
 * motivo: se corrige, se borra la columna y se vuelve a subir el mismo fichero.
 */
test('las filas rechazadas vuelven en un fichero con el motivo', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () use ($cuenta) {
        $fichero = ficheroCsv("Correo,Token\nno-es-un-correo,\n", $cuenta);

        $traspaso = Transfer::import('invitaciones', $fichero, ['email' => 'Correo', 'token' => 'Token']);

        (new RunImport($traspaso->getKey()))->handle(app(TransferManager::class), app(FileStore::class));

        $traspaso->refresh();

        expect($traspaso->error_file_id)->not->toBeNull();

        $errores = $traspaso->errorFile;

        $contenido = Storage::disk('s3')->get($errores->path);

        expect($contenido)->toContain('no-es-un-correo')
            ->and($contenido)->toContain(__('base-tenant::transfer.error_column'));
    });
});

/**
 * Lo que separa un import multi-tenant de un `foreach` con un `create`: las
 * filas tienen que aterrizar en la cuenta del traspaso, no en la que estuviera
 * en contexto cuando el worker cogió el job.
 */
test('las filas aterrizan en la cuenta del traspaso, no en la que hubiera en contexto', function () {
    $cuenta = $this->createAccount();
    $otra = $this->createAccount();

    $traspaso = Tenant::runFor($cuenta, function () use ($cuenta) {
        $fichero = ficheroCsv("Correo,Token\nuna@ejemplo.test,\n", $cuenta);

        return Transfer::import('invitaciones', $fichero, ['email' => 'Correo', 'token' => 'Token']);
    });

    // El worker arranca sin contexto, o con el de otra cuenta.
    Tenant::set($otra);

    (new RunImport($traspaso->getKey()))->handle(app(TransferManager::class), app(FileStore::class));

    expect(UserInvite::query()->forAccount($cuenta)->count())->toBe(1)
        ->and(UserInvite::query()->forAccount($otra)->count())->toBe(0);

    Tenant::forget();
});

test('un export escribe las filas de su cuenta y solo las suyas', function () {
    $cuenta = $this->createAccount();
    $otra = $this->createAccount();

    Tenant::runFor($cuenta, fn () => UserInvite::create([
        'email' => 'mia@ejemplo.test', 'token' => Str::random(64), 'expires_at' => now()->addWeek(),
    ]));

    Tenant::runFor($otra, fn () => UserInvite::create([
        'email' => 'ajena@ejemplo.test', 'token' => Str::random(64), 'expires_at' => now()->addWeek(),
    ]));

    $traspaso = Tenant::runFor($cuenta, fn () => Transfer::export('invitaciones'));

    Tenant::forget();

    (new RunExport($traspaso->getKey()))->handle(app(TransferManager::class), app(FileStore::class));

    $traspaso->refresh();

    expect($traspaso->status)->toBe(DataTransfer::COMPLETED)
        ->and($traspaso->total_rows)->toBe(1)
        ->and($traspaso->file_id)->not->toBeNull();

    $contenido = Storage::disk('s3')->get($traspaso->file->path);

    expect($contenido)->toContain('mia@ejemplo.test')
        ->and($contenido)->not->toContain('ajena@ejemplo.test');
});

/**
 * Sin la marca, Excel abre un CSV en UTF-8 como Latin-1 y todos los nombres
 * con acento salen mal.
 */
test('el export lleva la marca de orden de bytes para Excel', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, fn () => UserInvite::create([
        'email' => 'jose@ejemplo.test', 'token' => Str::random(64), 'expires_at' => now()->addWeek(),
    ]));

    $traspaso = Tenant::runFor($cuenta, fn () => Transfer::export('invitaciones'));

    (new RunExport($traspaso->getKey()))->handle(app(TransferManager::class), app(FileStore::class));

    $contenido = Storage::disk('s3')->get($traspaso->refresh()->file->path);

    expect(str_starts_with($contenido, "\xEF\xBB\xBF"))->toBeTrue();
});

test('un manejador que no está declarado no se puede lanzar', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        expect(fn () => Transfer::export('inventado'))->toThrow(InvalidArgumentException::class);
    });
});

test('con el módulo apagado no se puede traspasar nada', function () {
    config(['base-tenant.transfer.enabled' => false]);

    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        expect(fn () => Transfer::export('invitaciones'))
            ->toThrow(ModuleDisabledException::class);
    });
});
