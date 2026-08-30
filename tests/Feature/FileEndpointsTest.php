<?php

declare(strict_types=1);

use Base\Tenant\Files\FileCollection;
use Base\Tenant\Files\FileStore;
use Base\Tenant\Livewire\FileLibrary;
use Base\Tenant\Livewire\Files\Uploader;
use Base\Tenant\Metering\MetricRegistry;
use Base\Tenant\Models\File;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('s3');

    $this->syncPermissions();

    config([
        'base-tenant.files.driver' => 'local',
        'base-tenant.files.disk' => 's3',
        'base-tenant.files.collections' => [
            'library' => ['accepts' => [], 'max_size' => 1024 * 1024],
        ],
        'base-tenant.metering.metrics' => [
            'storage.bytes' => ['type' => 'gauge', 'feature' => 'max_storage_gb', 'scale' => 1073741824],
        ],
    ]);

    app(MetricRegistry::class)->flush();
});

/**
 * El recorrido entero tal como lo hace el navegador: pedir sitio, mandar los
 * bytes, y contar lo que llegó.
 */
test('los tres pasos de la subida funcionan de punta a punta', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($usuaria, $cuenta);

    $firma = $this->postJson(route('base-tenant.files.sign'), [
        'name' => 'notas.txt',
        'content_type' => 'text/plain',
        'size' => 11,
        'collection' => 'library',
    ])->assertOk()->json();

    $this->call('PUT', $firma['url'], content: 'hola mundo!')->assertOk();

    $creado = $this->postJson(route('base-tenant.files.finalize'), [
        'key' => $firma['key'],
        'name' => 'notas.txt',
        'collection' => 'library',
    ])->assertCreated()->json();

    $fichero = File::query()->find($creado['id']);

    expect($fichero)->not->toBeNull()
        ->and($fichero->size)->toBe(11)
        ->and($fichero->uploaded_by)->toBe($usuaria->getKey());

    Storage::disk('s3')->assertExists($fichero->path);
});

test('una colección que no está declarada no se puede usar', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($usuaria, $cuenta);

    $this->postJson(route('base-tenant.files.sign'), [
        'name' => 'x.txt',
        'content_type' => 'text/plain',
        'size' => 1,
        'collection' => 'inventada',
    ])->assertStatus(422);
});

/**
 * La clave la genera el servidor y el navegador la devuelve. Si el endpoint
 * aceptara cualquier cosa como clave, quien llame elegiría dónde escribir en
 * un disco que no es suyo.
 */
test('el endpoint local no acepta una clave que no sea un uuid', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($usuaria, $cuenta);

    // Sin barras, o el enrutador lo rechazaría antes de llegar aquí y el test
    // pasaría sin haber comprobado la guarda.
    $this->call('PUT', route('base-tenant.files.upload', ['key' => 'no-es-un-uuid']), content: 'x')
        ->assertStatus(422);
});

test('el endpoint local no existe con el driver de Vapor', function () {
    config(['base-tenant.files.driver' => 'vapor']);

    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($usuaria, $cuenta);

    $this->call('PUT', route('base-tenant.files.upload', ['key' => (string) Str::uuid()]), content: 'x')
        ->assertNotFound();
});

test('descargar un fichero de otra cuenta no se puede', function () {
    $una = $this->createAccount();
    $otra = $this->createAccount();
    $intrusa = $this->createUser($otra, 'customer-admin');

    $fichero = app(FileStore::class)->finalize(
        key: (function () {
            $clave = FileStore::TMP_PREFIX.'/'.Str::uuid();
            Storage::disk('s3')->put($clave, 'privado');

            return $clave;
        })(),
        collection: FileCollection::make('library'),
        name: 'nomina.txt',
        account: $una,
    );

    $this->actingAsTenant($intrusa, $otra);

    // El ámbito de tenant ya lo esconde de la consulta, así que ni siquiera
    // llega a la comprobación de propiedad: 404 antes que 403.
    $this->get(route('base-tenant.files.show', ['file' => $fichero->getKey()]))
        ->assertNotFound();
});

test('la biblioteca solo entra con permiso', function () {
    $cuenta = $this->createAccount();
    $mirona = $this->createUser($cuenta, 'customer-viewer');

    $this->actingAsTenant($mirona, $cuenta);

    Livewire::test(FileLibrary::class)->assertForbidden();
});

test('borrar desde la biblioteca pasa por el modal y nombra el fichero', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    $fichero = File::create([
        'collection' => 'library',
        'disk' => 's3',
        'path' => "accounts/{$cuenta->getKey()}/files/x/informe.pdf",
        'name' => 'informe.pdf',
        'mime_type' => 'application/pdf',
        'size' => 100,
    ]);

    $componente = Livewire::test(FileLibrary::class)
        ->call('confirmDelete', $fichero->getKey())
        ->assertSet('showDeleteModal', true)
        ->assertSee('informe.pdf');

    $componente->call('remove');

    expect(File::query()->count())->toBe(0);
});

/**
 * El subidor empuja su JavaScript a la pila `scripts`. Si el armazón no la
 * tiene, el `@push` se descarta sin avisar y la zona de soltar queda inerte:
 * se ve bien y no hace nada.
 */
test('los armazones tienen la pila donde los componentes empujan su javascript', function () {
    foreach (['app', 'guest'] as $armazon) {
        $contenido = Filesystem::get(dirname(__DIR__, 2)."/resources/views/layouts/{$armazon}.blade.php");

        // `toContain` recibe varios términos a buscar, no un mensaje: el
        // mensaje va en el `expect` de un booleano.
        expect(str_contains($contenido, "@stack('scripts')"))->toBeTrue(
            "El armazón `{$armazon}` no tiene la pila `scripts`: un @push se descarta sin avisar."
        );
    }
});

test('el subidor pinta su zona y su javascript', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($usuaria, $cuenta);

    Livewire::test(Uploader::class, ['collection' => 'library'])
        ->assertOk()
        ->assertSee(__('base-tenant::files.drop_here'));
});

/**
 * El id que manda el navegador es una afirmación sobre un registro que puede
 * no ser suyo: se busca, no se cree.
 */
test('el subidor no avisa de un fichero que no existe en la cuenta', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($usuaria, $cuenta);

    Livewire::test(Uploader::class, ['collection' => 'library'])
        ->call('uploaded', (string) Str::uuid())
        ->assertNotDispatched('file-uploaded');
});
