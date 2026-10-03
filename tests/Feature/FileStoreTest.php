<?php

declare(strict_types=1);

use Base\Tenant\Exceptions\ModuleDisabledException;
use Base\Tenant\Exceptions\UsageLimitExceededException;
use Base\Tenant\Facades\Feature;
use Base\Tenant\Facades\Meter;
use Base\Tenant\Files\FileCollection;
use Base\Tenant\Files\FileStore;
use Base\Tenant\Files\ImageVariants;
use Base\Tenant\Jobs\GenerateFileVariants;
use Base\Tenant\Metering\MetricRegistry;
use Base\Tenant\Models\File;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Un PNG de 1x1 de verdad: el olfateo de tipo lee los bytes, así que una
 * cadena inventada se identificaría como texto plano y no probaría nada.
 */
function pngDeUnPixel(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );
}

beforeEach(function () {
    Storage::fake('s3');

    config([
        'base-tenant.files.driver' => 'local',
        'base-tenant.files.disk' => 's3',
        'base-tenant.metering.metrics' => [
            'storage.bytes' => [
                'type' => 'gauge',
                'feature' => 'max_storage_gb',
                'scale' => 1073741824,
            ],
        ],
    ]);

    app(MetricRegistry::class)->flush();
});

/**
 * Deja bytes donde `sign()` los habría hecho aterrizar.
 */
function subirTemporal(string $contenido): string
{
    $clave = FileStore::TMP_PREFIX.'/'.Str::uuid();

    Storage::disk('s3')->put($clave, $contenido);

    return $clave;
}

test('firmar devuelve una clave temporal y a dónde mandar los bytes', function () {
    $cuenta = $this->createAccount();

    $firma = app(FileStore::class)->sign(
        FileCollection::make('library'),
        'informe.pdf',
        'application/pdf',
        1024,
        $cuenta,
    );

    expect($firma['key'])->toStartWith(FileStore::TMP_PREFIX.'/')
        ->and($firma['uuid'])->toBeString()
        ->and($firma['url'])->toContain('/files/upload/');
});

test('firmar rechaza un tipo que la colección no acepta', function () {
    $cuenta = $this->createAccount();

    expect(fn () => app(FileStore::class)->sign(
        FileCollection::images('fotos'),
        'virus.exe',
        'application/x-msdownload',
        10,
        $cuenta,
    ))->toThrow(InvalidArgumentException::class, 'fotos');
});

test('finalizar guarda el fichero, lo mueve a su sitio y lo mide', function () {
    $cuenta = $this->createAccount();
    $clave = subirTemporal(pngDeUnPixel());

    $fichero = app(FileStore::class)->finalize(
        key: $clave,
        collection: FileCollection::make('library'),
        name: 'foto.png',
        account: $cuenta,
    );

    expect($fichero->path)->toBe("accounts/{$cuenta->getKey()}/files/{$fichero->getKey()}/foto.png")
        ->and($fichero->mime_type)->toBe('image/png')
        ->and($fichero->size)->toBe(strlen(pngDeUnPixel()));

    Storage::disk('s3')->assertExists($fichero->path);
    Storage::disk('s3')->assertMissing($clave);

    expect(Meter::for($cuenta)->current('storage.bytes'))->toBe($fichero->size);
});

/**
 * El tamaño que se firma lo dice el navegador. Un cliente que anuncia 2 KB y
 * sube 2 GB solo se detecta después, mirando el objeto que existe.
 */
test('el tamaño se lee del objeto, no de lo que dijo quien subió', function () {
    $cuenta = $this->createAccount();
    $clave = subirTemporal(str_repeat('x', 5000));

    app(FileStore::class)->sign(FileCollection::make('library'), 'a.txt', 'text/plain', 10, $cuenta);

    $fichero = app(FileStore::class)->finalize(
        key: $clave,
        collection: FileCollection::make('library'),
        name: 'a.txt',
        account: $cuenta,
    );

    expect($fichero->size)->toBe(5000);
});

/**
 * El `Content-Type` del navegador es una afirmación. Sin olfatear los bytes,
 * un ejecutable anunciado como imagen se devolvería con esa cabecera a quien
 * lo abriera.
 */
test('el tipo sale de los bytes y una colección de imágenes rechaza lo que no lo es', function () {
    $cuenta = $this->createAccount();
    $clave = subirTemporal('MZ'.str_repeat("\0", 100));

    expect(fn () => app(FileStore::class)->finalize(
        key: $clave,
        collection: FileCollection::images('fotos'),
        name: 'inocente.png',
        account: $cuenta,
    ))->toThrow(InvalidArgumentException::class);

    // Y lo rechazado no se queda ocupando sitio del que nadie responde.
    Storage::disk('s3')->assertMissing($clave);
});

/**
 * `finalize()` mueve la clave que le den y es alcanzable por HTTP. Sin esta
 * guarda, quien llame podría nombrar cualquier objeto del disco -- el fichero
 * de otra cuenta incluido -- y hacer que se mueva al suyo.
 */
test('finalizar no acepta una clave de fuera del prefijo temporal', function () {
    $cuenta = $this->createAccount();
    $otra = $this->createAccount();

    Storage::disk('s3')->put("accounts/{$otra->getKey()}/files/secreto/nomina.pdf", 'ajeno');

    expect(fn () => app(FileStore::class)->finalize(
        key: "accounts/{$otra->getKey()}/files/secreto/nomina.pdf",
        collection: FileCollection::make('library'),
        name: 'robado.pdf',
        account: $cuenta,
    ))->toThrow(RuntimeException::class);

    Storage::disk('s3')->assertExists("accounts/{$otra->getKey()}/files/secreto/nomina.pdf");
});

test('finalizar no acepta una clave que sale del prefijo con puntos', function () {
    $cuenta = $this->createAccount();

    expect(fn () => app(FileStore::class)->finalize(
        key: FileStore::TMP_PREFIX.'/../accounts/otro/fichero.pdf',
        collection: FileCollection::make('library'),
        name: 'x.pdf',
        account: $cuenta,
    ))->toThrow(RuntimeException::class);
});

test('finalizar se niega cuando el fichero no cabe en el plan', function () {
    $cuenta = $this->createAccount();

    // Un byte de cuota, escrito en la unidad de la métrica.
    Feature::for($cuenta)->set('max_storage_gb', 0);

    $clave = subirTemporal(str_repeat('x', 100));

    expect(fn () => app(FileStore::class)->finalize(
        key: $clave,
        collection: FileCollection::make('library'),
        name: 'grande.txt',
        account: $cuenta,
    ))->toThrow(UsageLimitExceededException::class);

    expect(File::query()->forAccount($cuenta)->count())->toBe(0);
    Storage::disk('s3')->assertMissing($clave);
});

test('una colección de un solo fichero sustituye en lugar de acumular', function () {
    $cuenta = $this->createAccount();
    $coleccion = FileCollection::make('avatar', single: true);

    $primero = app(FileStore::class)->finalize(
        key: subirTemporal(pngDeUnPixel()),
        collection: $coleccion,
        name: 'uno.png',
        account: $cuenta,
    );

    $segundo = app(FileStore::class)->finalize(
        key: subirTemporal(pngDeUnPixel()),
        collection: $coleccion,
        name: 'dos.png',
        account: $cuenta,
    );

    expect(File::query()->forAccount($cuenta)->inCollection('avatar')->count())->toBe(1)
        ->and(File::query()->forAccount($cuenta)->inCollection('avatar')->first()->getKey())
        ->toBe($segundo->getKey());

    Storage::disk('s3')->assertMissing($primero->path);

    // Y el medidor cuenta uno, no dos.
    expect(Meter::for($cuenta)->current('storage.bytes'))->toBe($segundo->size);
});

test('borrar se lleva el directorio entero y devuelve el espacio', function () {
    $cuenta = $this->createAccount();

    $fichero = app(FileStore::class)->finalize(
        key: subirTemporal(pngDeUnPixel()),
        collection: FileCollection::make('library'),
        name: 'foto.png',
        account: $cuenta,
    );

    // Una miniatura escrita después, como haría el job de variantes.
    Storage::disk('s3')->put($fichero->directory().'/thumb.jpg', 'miniatura');

    app(FileStore::class)->delete($fichero);

    Storage::disk('s3')->assertMissing($fichero->path);
    Storage::disk('s3')->assertMissing($fichero->directory().'/thumb.jpg');

    expect(Meter::for($cuenta)->current('storage.bytes'))->toBe(0);
});

test('el nombre se queda reconocible pero deja de poder actuar como ruta', function () {
    $cuenta = $this->createAccount();

    $fichero = app(FileStore::class)->finalize(
        key: subirTemporal(pngDeUnPixel()),
        collection: FileCollection::make('library'),
        name: '../../etc/passwd.png',
        account: $cuenta,
    );

    expect($fichero->name)->toBe('passwd.png')
        ->and($fichero->path)->toContain("accounts/{$cuenta->getKey()}/files/");
});

test('los ficheros de una cuenta no se ven desde otra', function () {
    $una = $this->createAccount();
    $otra = $this->createAccount();

    app(FileStore::class)->finalize(
        key: subirTemporal(pngDeUnPixel()),
        collection: FileCollection::make('library'),
        name: 'privado.png',
        account: $una,
    );

    expect(File::query()->forAccount($otra)->count())->toBe(0)
        ->and(File::query()->forAccount($una)->count())->toBe(1);
});

test('las variantes se encolan solo para imágenes y solo si se declaran', function () {
    Bus::fake();

    $cuenta = $this->createAccount();

    app(FileStore::class)->finalize(
        key: subirTemporal('texto llano'),
        collection: FileCollection::make('library', variants: ['thumb' => ['width' => 10]]),
        name: 'notas.txt',
        account: $cuenta,
    );

    Bus::assertNotDispatched(GenerateFileVariants::class);

    app(FileStore::class)->finalize(
        key: subirTemporal(pngDeUnPixel()),
        collection: FileCollection::make('library', variants: ['thumb' => ['width' => 10]]),
        name: 'foto.png',
        account: $cuenta,
    );

    Bus::assertDispatched(GenerateFileVariants::class);
});

/**
 * Las miniaturas ocupan sitio. Contar solo los originales haría que el medidor
 * discrepara de la factura del almacén, y por un factor que crece con cada
 * variante que se añada.
 */
test('las variantes generadas también cuentan en el medidor', function () {
    $cuenta = $this->createAccount();

    $fichero = app(FileStore::class)->finalize(
        key: subirTemporal(pngDeUnPixel()),
        collection: FileCollection::make('library'),
        name: 'foto.png',
        account: $cuenta,
    );

    $original = Meter::for($cuenta)->current('storage.bytes');

    (new GenerateFileVariants($fichero->getKey(), ['thumb' => ['width' => 8, 'height' => 8, 'fit' => 'cover']]))
        ->handle(app(ImageVariants::class));

    $fichero->refresh();

    expect($fichero->variants)->toHaveKey('thumb');
    Storage::disk('s3')->assertExists($fichero->variants['thumb']);

    expect(Meter::for($cuenta)->current('storage.bytes'))->toBeGreaterThan($original);
})->skip(! extension_loaded('gd'), 'Requiere la extensión GD.');

test('con el módulo apagado no se puede subir nada', function () {
    config(['base-tenant.files.enabled' => false]);

    $cuenta = $this->createAccount();

    expect(fn () => app(FileStore::class)->sign(
        FileCollection::make('library'),
        'a.txt',
        'text/plain',
        10,
        $cuenta,
    ))->toThrow(ModuleDisabledException::class);
});

test('reconciliar cuadra el medidor con lo que hay en la tabla', function () {
    $cuenta = $this->createAccount();

    $fichero = app(FileStore::class)->finalize(
        key: subirTemporal(pngDeUnPixel()),
        collection: FileCollection::make('library'),
        name: 'foto.png',
        account: $cuenta,
    );

    // Una deriva como la que deja un finalize que muere entre el move y el
    // medidor.
    Meter::for($cuenta)->set('storage.bytes', 999999);

    $this->artisan('k2labs-base:reconcile-storage', ['--dry-run' => true])->assertSuccessful();

    expect(Meter::for($cuenta)->current('storage.bytes'))->toBe(999999);

    $this->artisan('k2labs-base:reconcile-storage')->assertSuccessful();

    expect(Meter::for($cuenta)->current('storage.bytes'))->toBe($fichero->size);
});

/**
 * Una cuenta que se ha quedado sin ficheros conserva su contador. Leer solo
 * las cuentas que tienen ficheros lo dejaría donde estaba, para siempre.
 */
test('reconciliar también baja a cero a quien ya no tiene ficheros', function () {
    $cuenta = $this->createAccount();

    $fichero = app(FileStore::class)->finalize(
        key: subirTemporal(pngDeUnPixel()),
        collection: FileCollection::make('library'),
        name: 'foto.png',
        account: $cuenta,
    );

    $fichero->forceDelete();

    $this->artisan('k2labs-base:reconcile-storage')->assertSuccessful();

    expect(Meter::for($cuenta)->current('storage.bytes'))->toBe(0);
});

// ------------------------------------------- Medidor: original y variantes

/**
 * Las variantes suman al medidor cuando se generan. Si el borrado solo resta
 * el original, la diferencia se queda en el contador hasta la reconciliación.
 */
test('borrar descuenta también lo que ocupaban las variantes', function () {
    $cuenta = $this->createAccount();

    $fichero = app(FileStore::class)->finalize(
        key: subirTemporal(pngDeUnPixel()),
        collection: FileCollection::make('library'),
        name: 'foto.png',
        account: $cuenta,
    );

    Storage::disk('s3')->put($fichero->directory().'/thumb.png', str_repeat('t', 300));
    Storage::disk('s3')->put($fichero->directory().'/preview.png', str_repeat('p', 700));

    $fichero->variants = [
        'thumb' => $fichero->directory().'/thumb.png',
        'preview' => $fichero->directory().'/preview.png',
    ];
    $fichero->saveQuietly();

    Meter::for($cuenta)->increment('storage.bytes', 1000);

    expect(Meter::for($cuenta)->current('storage.bytes'))->toBe($fichero->size + 1000);

    app(FileStore::class)->delete($fichero);

    expect(Meter::for($cuenta)->current('storage.bytes'))->toBe(0);
});

test('regenerar las variantes no las cuenta dos veces', function () {
    $cuenta = $this->createAccount();

    $fichero = app(FileStore::class)->finalize(
        key: subirTemporal(pngDeUnPixel()),
        collection: FileCollection::make('library'),
        name: 'foto.png',
        account: $cuenta,
    );

    $variantes = ['thumb' => ['width' => 8, 'height' => 8, 'fit' => 'cover']];

    (new GenerateFileVariants($fichero->getKey(), $variantes))->handle(app(ImageVariants::class));

    $trasLaPrimera = Meter::for($cuenta)->current('storage.bytes');

    (new GenerateFileVariants($fichero->getKey(), $variantes))->handle(app(ImageVariants::class));

    expect(Meter::for($cuenta)->current('storage.bytes'))->toBe($trasLaPrimera);

    // Y una variante que deja de declararse se borra y sale del medidor.
    (new GenerateFileVariants($fichero->getKey(), ['mini' => ['width' => 4, 'height' => 4, 'fit' => 'cover']]))
        ->handle(app(ImageVariants::class));

    $fichero->refresh();

    expect($fichero->variants)->toHaveKeys(['mini'])->not->toHaveKey('thumb')
        ->and(Meter::for($cuenta)->current('storage.bytes'))->toBe($fichero->size + $fichero->variantsSize());

    app(FileStore::class)->delete($fichero);

    expect(Meter::for($cuenta)->current('storage.bytes'))->toBe(0);
})->skip(! extension_loaded('gd'), 'Requiere la extensión GD.');

// ------------------------------------------ Bomba de descompresión

/**
 * La cabecera de un PNG que declara un lienzo enorme. Pesa unos bytes;
 * decodificarlo pediría a GD cuatro bytes por píxel declarado.
 */
function cabeceraPng(int $ancho, int $alto): string
{
    $ihdr = pack('NNCCCCC', $ancho, $alto, 8, 6, 0, 0, 0);

    return "\x89PNG\r\n\x1a\n"
        .pack('N', strlen($ihdr)).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr))
        .pack('N', 0).'IEND'.pack('N', crc32('IEND'));
}

test('una imagen con más píxeles de los permitidos no genera variantes y queda registrada', function () {
    Log::spy();

    $cuenta = $this->createAccount();

    $fichero = app(FileStore::class)->finalize(
        key: subirTemporal(cabeceraPng(50_000, 50_000)),
        collection: FileCollection::make('library'),
        name: 'bomba.png',
        account: $cuenta,
    );

    expect($fichero->mime_type)->toBe('image/png');

    $medidor = Meter::for($cuenta)->current('storage.bytes');

    (new GenerateFileVariants($fichero->getKey(), ['thumb' => ['width' => 8, 'height' => 8, 'fit' => 'cover']]))
        ->handle(app(ImageVariants::class));

    $fichero->refresh();

    expect($fichero->variants)->toBeNull()
        ->and(Meter::for($cuenta)->current('storage.bytes'))->toBe($medidor);

    // El original se conserva.
    Storage::disk('s3')->assertExists($fichero->path);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $mensaje, array $contexto) => str_contains($mensaje, 'max_image_pixels')
            && $contexto['file'] === $fichero->getKey()
            && $contexto['pixels'] === 2_500_000_000)
        ->once();
});

test('el límite de píxeles se lee de la configuración y se puede quitar', function () {
    expect(ImageVariants::maxPixels())->toBe(40_000_000);

    config(['base-tenant.files.max_image_pixels' => 100]);
    expect(ImageVariants::maxPixels())->toBe(100);

    config(['base-tenant.files.max_image_pixels' => null]);
    expect(ImageVariants::maxPixels())->toBeNull();

    config(['base-tenant.files.max_image_pixels' => 0]);
    expect(ImageVariants::maxPixels())->toBeNull();
});

test('una imagen dentro del límite sí genera sus variantes', function () {
    config(['base-tenant.files.max_image_pixels' => 1]);

    $cuenta = $this->createAccount();

    $fichero = app(FileStore::class)->finalize(
        key: subirTemporal(pngDeUnPixel()),
        collection: FileCollection::make('library'),
        name: 'foto.png',
        account: $cuenta,
    );

    (new GenerateFileVariants($fichero->getKey(), ['thumb' => ['width' => 8, 'height' => 8, 'fit' => 'cover']]))
        ->handle(app(ImageVariants::class));

    expect($fichero->refresh()->variants)->toHaveKey('thumb');
})->skip(! extension_loaded('gd'), 'Requiere la extensión GD.');

/**
 * Si la reconciliación solo contara los originales, quitaría las variantes
 * del medidor y cada borrado posterior lo dejaría por debajo de la realidad.
 */
test('reconciliar cuenta también las variantes que hay en el disco', function () {
    $cuenta = $this->createAccount();

    $fichero = app(FileStore::class)->finalize(
        key: subirTemporal(pngDeUnPixel()),
        collection: FileCollection::make('library'),
        name: 'foto.png',
        account: $cuenta,
    );

    Storage::disk('s3')->put($fichero->directory().'/thumb.png', str_repeat('t', 300));

    $fichero->variants = [
        'thumb' => $fichero->directory().'/thumb.png',
        'perdida' => $fichero->directory().'/no-existe.png',
    ];
    $fichero->saveQuietly();

    Meter::for($cuenta)->set('storage.bytes', 1);

    $this->artisan('k2labs-base:reconcile-storage')->assertSuccessful();

    expect(Meter::for($cuenta)->current('storage.bytes'))->toBe($fichero->size + 300);

    app(FileStore::class)->delete($fichero);

    expect(Meter::for($cuenta)->current('storage.bytes'))->toBe(0);
});
