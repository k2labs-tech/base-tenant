<?php

declare(strict_types=1);

use Base\Tenant\Languages\LangFileWriter;
use Base\Tenant\Languages\LangSyncerClient;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'base-tenant.languages.langsyncer' => [
            'url' => 'https://langsyncer.test',
            'key' => 'clave',
            'project' => 'proyecto',
            'webhook_secret' => 'secreto-compartido',
        ],
    ]);

    $this->raiz = sys_get_temp_dir().'/lang-'.uniqid();

    File::ensureDirectoryExists($this->raiz);
});

afterEach(function () {
    File::deleteDirectory($this->raiz);
});

test('sin credenciales la integración está apagada', function () {
    config(['base-tenant.languages.langsyncer.key' => null]);

    expect(app(LangSyncerClient::class)->configured())->toBeFalse();

    $this->artisan('k2labs-base:lang-push')->assertSuccessful();
    $this->artisan('k2labs-base:lang-pull')->assertSuccessful();
});

test('las claves se aplanan a fichero.clave.anidada', function () {
    File::ensureDirectoryExists($this->raiz.'/en');
    File::put($this->raiz.'/en/prueba.php', "<?php return ['titulo' => 'Title', 'campos' => ['nombre' => 'Name']];");

    $claves = (new LangFileWriter($this->raiz))->read('en');

    expect($claves)->toBe([
        'prueba.titulo' => 'Title',
        'prueba.campos.nombre' => 'Name',
    ]);
});

/**
 * Un locale que vuelve de traducción con dos tercios de sus claves no puede
 * borrar el tercio que el proyecto escribió a mano.
 */
test('escribir mezcla y no reemplaza', function () {
    File::ensureDirectoryExists($this->raiz.'/ca');
    File::put($this->raiz.'/ca/prueba.php', "<?php return ['titulo' => 'Vell', 'propia' => 'No la toques'];");

    $escritor = new LangFileWriter($this->raiz);

    $cambiadas = $escritor->write('ca', ['prueba.titulo' => 'Nou', 'prueba.nueva' => 'Afegida']);

    expect($cambiadas)->toBe(2);

    $resultado = $escritor->read('ca');

    expect($resultado['prueba.titulo'])->toBe('Nou')
        ->and($resultado['prueba.nueva'])->toBe('Afegida')
        ->and($resultado['prueba.propia'])->toBe('No la toques');
});

/**
 * Los traductores escriben apóstrofos constantemente. Escapar de más pondría
 * una barra invertida literal delante de caracteres que nunca la necesitaron.
 */
test('un apóstrofo en la traducción no rompe el fichero', function () {
    $escritor = new LangFileWriter($this->raiz);

    $escritor->write('ca', ['prueba.frase' => "L'usuari ha entrat", 'prueba.ruta' => 'C:\\datos']);

    $salida = [];
    $codigo = 0;
    exec('php -l '.escapeshellarg($this->raiz.'/ca/prueba.php').' 2>&1', $salida, $codigo);

    expect($codigo)->toBe(0, implode("\n", $salida));

    expect($escritor->read('ca')['prueba.frase'])->toBe("L'usuari ha entrat")
        ->and($escritor->read('ca')['prueba.ruta'])->toBe('C:\\datos');
});

test('una traducción vacía no cuenta como traducida', function () {
    $escritor = new LangFileWriter($this->raiz);

    $escritor->write('ca', ['prueba.a' => 'Bé', 'prueba.b' => '   ']);

    expect($escritor->read('ca'))->toBe(['prueba.a' => 'Bé']);
});

test('subir manda las claves del idioma de referencia', function () {
    Http::fake(['*' => Http::response(['created' => 3, 'updated' => 1])]);

    // Con claves de verdad delante: sin ellas el comando avisa y sale bien sin
    // mandar nada, y la comprobación pasaría sin haber probado el envío.
    File::ensureDirectoryExists($this->raiz.'/en');
    File::put($this->raiz.'/en/prueba.php', "<?php return ['titulo' => 'Title'];");

    $this->app->instance(LangFileWriter::class, new LangFileWriter($this->raiz));

    $this->artisan('k2labs-base:lang-push', ['--locale' => 'en'])
        ->assertSuccessful();

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), '/api/projects/proyecto/keys')
            && ($request->data()['keys']['prueba.titulo'] ?? null) === 'Title';
    });
});

/**
 * Sin claves no hay nada que mandar, y mandar un cuerpo vacío haría que el
 * servicio marcara como huérfanas todas las claves que ya tiene.
 */
test('sin claves no se manda una petición vacía', function () {
    Http::fake();

    $this->app->instance(LangFileWriter::class, new LangFileWriter($this->raiz));

    $this->artisan('k2labs-base:lang-push', ['--locale' => 'en'])->assertSuccessful();

    Http::assertNothingSent();
});

test('bajar escribe los ficheros del idioma', function () {
    Http::fake([
        '*/locales' => Http::response(['locales' => ['en', 'ca']]),
        '*/translations*' => Http::response(['translations' => ['prueba.titulo' => 'Títol']]),
    ]);

    $this->app->instance(LangFileWriter::class, new LangFileWriter($this->raiz));

    $this->artisan('k2labs-base:lang-pull')->assertSuccessful();

    expect(File::exists($this->raiz.'/ca/prueba.php'))->toBeTrue()
        // El de referencia es el original: sobrescribirlo con lo que vuelve de
        // traducción dejaría que un viaje de ida y vuelta reescriba el texto
        // del que todo lo demás se traduce.
        ->and(File::exists($this->raiz.'/en/prueba.php'))->toBeFalse();
});

/**
 * Un endpoint de webhook sin firma es un ejecutor de comandos sin autenticar.
 */
test('el webhook rechaza una firma que no cuadra', function () {
    Queue::fake();

    $this->postJson(route('base-tenant.webhooks.langsyncer'), ['locales' => ['ca']], [
        'X-LangSyncer-Signature' => 'me-lo-invento',
    ])->assertStatus(401);

    $this->postJson(route('base-tenant.webhooks.langsyncer'), ['locales' => ['ca']])
        ->assertStatus(401);
});

test('el webhook con firma buena encola la bajada', function () {
    Queue::fake();

    $cuerpo = json_encode(['locales' => ['ca']]);
    $firma = hash_hmac('sha256', $cuerpo, 'secreto-compartido');

    $this->call(
        'POST',
        route('base-tenant.webhooks.langsyncer'),
        content: $cuerpo,
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_LANGSYNCER_SIGNATURE' => $firma,
        ],
    )->assertStatus(202);
});

/**
 * La firma se comprueba contra el cuerpo en crudo: parsear y volver a
 * serializar no siempre da la misma cadena, y entonces ninguna entrega válida
 * pasaría.
 */
test('la firma se calcula sobre el cuerpo tal como llegó', function () {
    $cliente = app(LangSyncerClient::class);

    $cuerpo = '{"locales":["ca"],"espaciado":   true}';

    expect($cliente->verifySignature($cuerpo, hash_hmac('sha256', $cuerpo, 'secreto-compartido')))->toBeTrue()
        ->and($cliente->verifySignature($cuerpo, hash_hmac('sha256', '{"locales":["ca"]}', 'secreto-compartido')))->toBeFalse();
});

test('sin secreto configurado ninguna firma vale', function () {
    config(['base-tenant.languages.langsyncer.webhook_secret' => null]);

    expect(app(LangSyncerClient::class)->verifySignature('x', hash_hmac('sha256', 'x', '')))->toBeFalse();
});
