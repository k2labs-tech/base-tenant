<?php

declare(strict_types=1);

use Base\Tenant\Files\FileCollection;
use Base\Tenant\Files\FileStore;
use Base\Tenant\Http\Controllers\PublicFileController;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\File;
use Base\Tenant\Traits\HasFiles;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Un dueño con una colección pública declarada en el modelo, sobre una tabla
 * que ya existe.
 */
class CuentaConLogo extends Account
{
    use HasFiles;

    protected $table = 'accounts';

    public function fileCollections(): array
    {
        return [
            'logo' => FileCollection::images('logo', single: true, public: true),
            'contratos' => FileCollection::make('contratos'),
        ];
    }
}

beforeEach(function () {
    Storage::fake('s3');

    // Un disco local de verdad, sin `serve`: no sabe firmar, que es el caso
    // en el que `url()` tiene que decidir qué hacer.
    config([
        'filesystems.disks.sin-firma' => ['driver' => 'local', 'root' => sys_get_temp_dir().'/base-tenant-sin-firma'],
        'base-tenant.files.driver' => 'local',
        'base-tenant.files.disk' => 's3',
        'base-tenant.files.collections' => [
            'library' => ['accepts' => []],
            'marca' => ['accepts' => ['text/*'], 'public' => true],
        ],
    ]);

    // La ruta pública va fuera del grupo autenticado; el test la declara igual
    // que la declarará routes/web.php.
    if (! Route::has('base-tenant.files.public')) {
        Route::get('files/public/{file}', PublicFileController::class)->name('base-tenant.files.public');
        app('router')->getRoutes()->refreshNameLookups();
    }
});

function ficheroEn(string $coleccion, $cuenta, string $contenido = 'hola', $dueno = null): File
{
    $clave = FileStore::TMP_PREFIX.'/'.Str::uuid();

    Storage::disk('s3')->put($clave, $contenido);

    return app(FileStore::class)->finalize(
        key: $clave,
        collection: FileCollection::make($coleccion),
        name: 'logo.txt',
        fileable: $dueno,
        account: $cuenta,
    );
}

// ------------------------------------------------------ Enlace de streaming

test('un disco que firma da una URL con caducidad', function () {
    $fichero = ficheroEn('library', $this->createAccount());

    expect($fichero->url())->toContain('expiration=')
        ->and($fichero->temporaryUrlOrNull())->toContain('expiration=');
});

test('sin firma, por defecto se cae a la ruta de streaming como en 3.0', function () {
    $fichero = ficheroEn('library', $this->createAccount());
    $fichero->disk = 'sin-firma';

    expect($fichero->url())->toBe(route('base-tenant.files.show', ['file' => $fichero->getKey()]))
        ->and($fichero->temporaryUrlOrNull())->toBeNull();
});

test('con stream_fallback apagado no se entrega un enlace sin caducidad', function () {
    config(['base-tenant.files.stream_fallback' => false]);

    $fichero = ficheroEn('library', $this->createAccount());
    $fichero->disk = 'sin-firma';
    $fichero->variants = ['thumb' => dirname($fichero->path).'/thumb.txt'];

    expect(fn () => $fichero->url())->toThrow(RuntimeException::class, 'stream_fallback')
        ->and(fn () => $fichero->variantUrl('thumb'))->toThrow(RuntimeException::class)
        ->and($fichero->temporaryUrlOrNull())->toBeNull()
        ->and($fichero->temporaryUrlOrNull(variant: 'thumb'))->toBeNull();
});

test('una variante que no existe sigue siendo null', function () {
    $fichero = ficheroEn('library', $this->createAccount());

    expect($fichero->variantUrl('inventada'))->toBeNull()
        ->and($fichero->temporaryUrlOrNull(variant: 'inventada'))->toBeNull();
});

// --------------------------------------------------------- URL pública

test('un fichero de una colección privada no tiene URL pública', function () {
    $fichero = ficheroEn('library', $this->createAccount());

    expect($fichero->isPublic())->toBeFalse()
        ->and($fichero->publicUrl())->toBeNull();
});

test('un fichero de una colección pública tiene una URL estable que sirve sin sesión', function () {
    $fichero = ficheroEn('marca', $this->createAccount(), 'logotipo');

    $url = $fichero->publicUrl();

    expect($fichero->isPublic())->toBeTrue()
        ->and($url)->toBe(route('base-tenant.files.public', ['file' => $fichero->getKey()]))
        ->and($fichero->fresh()->publicUrl())->toBe($url);

    $respuesta = $this->get($url)->assertOk();

    expect($respuesta->streamedContent())->toBe('logotipo')
        ->and($respuesta->headers->get('Cache-Control'))->toContain('public');
});

/**
 * La ruta no guarda nada de la colección: si deja de ser pública, el enlace
 * que ya circulaba deja de servir.
 */
test('la ruta pública responde 404 a un fichero privado o a uno que dejó de ser público', function () {
    $cuenta = $this->createAccount();

    $privado = ficheroEn('library', $cuenta);
    $publico = ficheroEn('marca', $cuenta);
    $url = $publico->publicUrl();

    $this->get(route('base-tenant.files.public', ['file' => $privado->getKey()]))->assertNotFound();

    config(['base-tenant.files.collections.marca.public' => false]);

    $this->get($url)->assertNotFound();
});

test('la ruta pública no sirve un fichero borrado', function () {
    $fichero = ficheroEn('marca', $this->createAccount());
    $url = $fichero->publicUrl();

    app(FileStore::class)->delete($fichero);

    $this->get($url)->assertNotFound();
});

test('una colección declarada en el modelo también puede ser pública', function () {
    $coleccion = FileCollection::images('logo', single: true, public: true);

    expect($coleccion->public)->toBeTrue()
        ->and(FileCollection::make('otra')->public)->toBeFalse()
        ->and(FileCollection::fromConfig('x', ['public' => true])->public)->toBeTrue()
        ->and(FileCollection::fromConfig('x', [])->public)->toBeFalse();
});

test('la publicidad de un fichero con dueño la decide la colección del modelo', function () {
    $cuenta = $this->createAccount();
    $dueno = CuentaConLogo::query()->find($cuenta->getKey());

    $logo = ficheroEn('logo', $cuenta, 'png', $dueno);
    $contrato = ficheroEn('contratos', $cuenta, 'pdf', $dueno);
    $huerfano = ficheroEn('desaparecida', $cuenta, 'x', $dueno);

    expect($logo->isPublic())->toBeTrue()
        ->and($contrato->isPublic())->toBeFalse()
        ->and($huerfano->isPublic())->toBeFalse();

    $this->get($logo->publicUrl())->assertOk();
    $this->get(route('base-tenant.files.public', ['file' => $contrato->getKey()]))->assertNotFound();
});

// ------------------------------------------------- Contenido activo (SVG)

function svgConScript(): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>';
}

/**
 * Un SVG es un documento con script, no una imagen. Si `image/*` lo dejara
 * pasar, cualquier colección de imágenes pública sería un XSS almacenado.
 */
test('una colección de imágenes no acepta SVG salvo que lo pida', function () {
    expect(FileCollection::images('fotos')->accepts('image/svg+xml'))->toBeFalse()
        ->and(FileCollection::images('fotos')->accepts('image/png'))->toBeTrue()
        ->and(FileCollection::images('fotos', svg: true)->accepts('image/svg+xml'))->toBeTrue()
        ->and(FileCollection::make('x', accepts: ['image/svg+xml'])->accepts('image/svg+xml'))->toBeTrue();
});

test('un comodín nunca acepta HTML, XML ni scripts', function (string $tipo) {
    $coleccion = FileCollection::make('x', accepts: ['text/*', 'application/*', 'image/*']);

    expect($coleccion->accepts($tipo))->toBeFalse()
        ->and(FileCollection::make('x', accepts: [$tipo])->accepts($tipo))->toBeTrue();
})->with([
    'text/html',
    'application/xhtml+xml',
    'text/xml',
    'application/xml',
    'application/rss+xml',
    'text/javascript',
    'application/javascript',
]);

test('finalizar rechaza un SVG en una colección de imágenes', function () {
    $clave = FileStore::TMP_PREFIX.'/'.Str::uuid();
    Storage::disk('s3')->put($clave, svgConScript());

    expect(fn () => app(FileStore::class)->finalize(
        key: $clave,
        collection: FileCollection::images('logo', public: true),
        name: 'logo.svg',
        account: $this->createAccount(),
    ))->toThrow(InvalidArgumentException::class, 'image/svg+xml');
});

/**
 * Aunque llegue -- una colección sin restricciones, una aceptada a propósito
 * --, la ruta pública no lo sirve como documento del origen de la aplicación.
 */
test('la ruta pública sirve un SVG en sandbox y como descarga', function () {
    $fichero = ficheroEn('marca', $this->createAccount(), svgConScript());

    expect($fichero->mime_type)->toBe('image/svg+xml');

    $respuesta = $this->get($fichero->publicUrl())->assertOk();

    expect($respuesta->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; style-src 'unsafe-inline'; sandbox")
        ->and($respuesta->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($respuesta->headers->get('Content-Disposition'))->toStartWith('attachment');
});

test('la ruta pública sirve HTML en sandbox y como descarga', function () {
    $fichero = ficheroEn('marca', $this->createAccount(), '<!DOCTYPE html><html><script>alert(1)</script></html>');

    expect($fichero->mime_type)->toBe('text/html');

    $respuesta = $this->get($fichero->publicUrl())->assertOk();

    expect($respuesta->headers->get('Content-Security-Policy'))->toContain('sandbox')
        ->and($respuesta->headers->get('Content-Disposition'))->toStartWith('attachment');
});

test('una imagen corriente se sigue sirviendo en línea y sin política', function () {
    $fichero = ficheroEn('marca', $this->createAccount(), 'logotipo');

    $respuesta = $this->get($fichero->publicUrl())->assertOk();

    expect($respuesta->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($respuesta->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($respuesta->headers->has('Content-Security-Policy'))->toBeFalse();
});

test('la ruta de streaming también sirve el contenido activo en sandbox', function () {
    $cuenta = $this->createAccount();
    $this->syncPermissions();
    $usuaria = $this->createUser($cuenta, 'customer-admin');

    $svg = ficheroEn('library', $cuenta, svgConScript());
    $script = ficheroEn('library', $cuenta, 'alert(1)');
    $script->forceFill(['mime_type' => 'application/javascript'])->save();

    $this->actingAsTenant($usuaria, $cuenta);

    $respuesta = $this->get(route('base-tenant.files.show', ['file' => $svg->getKey()]))->assertOk();

    expect($respuesta->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; style-src 'unsafe-inline'; sandbox")
        ->and($respuesta->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($respuesta->headers->get('Content-Type'))->toStartWith('image/svg+xml');

    // Un script propio del origen podría cargarse con `<script src>` y saltarse
    // la CSP de la aplicación: se entrega como texto.
    $respuesta = $this->get(route('base-tenant.files.show', ['file' => $script->getKey()]))->assertOk();

    expect($respuesta->headers->get('Content-Type'))->toStartWith('text/plain')
        ->and($respuesta->headers->get('Content-Security-Policy'))->toContain('sandbox');
});
