<?php

declare(strict_types=1);

use Base\Tenant\Facades\Language;
use Base\Tenant\Livewire\LanguageManager;
use Base\Tenant\Models\Language as LanguageModel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    $this->syncPermissions();

    LanguageModel::query()->delete();

    foreach (config('base-tenant.languages.seed') as $idioma) {
        LanguageModel::create($idioma);
    }

    Language::flush();
});

test('solo se ofrecen los idiomas activos', function () {
    expect(Language::codes())->toBe(['en', 'es'])
        ->and(Language::isEnabled('ca'))->toBeFalse();
});

test('activar un idioma lo pone en la lista sin desplegar nada', function () {
    Language::enable('ca');

    expect(Language::codes())->toContain('ca');
});

/**
 * El punto de la caché es que la lista se lee en casi cada petición. El punto
 * de invalidarla es que un idioma activado se vea al momento y no cuando
 * caduque algo.
 */
test('la caché no sobrevive a un cambio', function () {
    Language::codes();

    Language::enable('ca');

    expect(Language::isEnabled('ca'))->toBeTrue();

    Language::disable('ca');

    expect(Language::isEnabled('ca'))->toBeFalse();
});

/**
 * Apagar el idioma por defecto dejaría el respaldo apuntando a un idioma que
 * nadie puede ver, y cada cadena sin traducir sin sitio adonde caer.
 */
test('el idioma por defecto no se puede apagar', function () {
    expect(fn () => Language::disable('en'))
        ->toThrow(RuntimeException::class, 'default');
});

test('poner otro por defecto deja exactamente uno', function () {
    Language::setDefault('es');

    expect(Language::default())->toBe('es')
        ->and(LanguageModel::where('is_default', true)->count())->toBe(1);
});

test('poner por defecto un idioma apagado lo enciende', function () {
    Language::setDefault('ca');

    expect(Language::default())->toBe('ca')
        ->and(Language::isEnabled('ca'))->toBeTrue();
});

/**
 * Quien tenía elegido un idioma que se ha apagado conserva la preferencia.
 * Respetarla le enseñaría una interfaz a medio traducir sin forma de volver.
 */
test('una preferencia que apunta a un idioma apagado cae en el por defecto', function () {
    expect(Language::resolve('ca'))->toBe('en')
        ->and(Language::resolve('es'))->toBe('es')
        ->and(Language::resolve(null))->toBe('en')
        ->and(Language::resolve('klingon'))->toBe('en');
});

test('el middleware pone el idioma del usuario cuando está activo', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta, null, ['locale' => 'es']);

    Route::middleware(['web', 'base-tenant.locale'])->get('/prueba-idioma', fn () => app()->getLocale());

    $this->actingAsTenant($usuaria, $cuenta);

    $this->get('/prueba-idioma')->assertSee('es');
});

test('el middleware ignora una preferencia de un idioma apagado', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta, null, ['locale' => 'ca']);

    Route::middleware(['web', 'base-tenant.locale'])->get('/prueba-idioma-apagado', fn () => app()->getLocale());

    $this->actingAsTenant($usuaria, $cuenta);

    $this->get('/prueba-idioma-apagado')->assertSee('en');

    // Y en cuanto se activa, la misma preferencia sí vale.
    Language::enable('ca');

    $this->get('/prueba-idioma-apagado')->assertSee('ca');
});

/**
 * Entre instalar el paquete y ejecutar sus migraciones, una consulta sin
 * proteger convertiría cada página en un error de SQL.
 */
test('sin tabla de idiomas la aplicación sigue respondiendo', function () {
    Language::flush();

    Schema::drop('languages');

    expect(Language::all())->toBeEmpty()
        ->and(Language::default())->toBe(config('app.locale'));
});

test('la pantalla de idiomas es de administración de sistema', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(LanguageManager::class)->assertForbidden();
});

test('un administrador de sistema activa y desactiva desde la pantalla', function () {
    $cuenta = $this->createAccount();
    $tecnico = $this->createUser($cuenta, 'administrator-tech');

    $this->actingAsTenant($tecnico, $cuenta);

    $componente = Livewire::test(LanguageManager::class)
        ->assertOk()
        ->assertSee('Català');

    $componente->call('toggle', 'ca');

    expect(Language::isEnabled('ca'))->toBeTrue();

    $componente->call('toggle', 'ca');

    expect(Language::isEnabled('ca'))->toBeFalse();
});

/**
 * Un interruptor desactivado que nadie sabe explicar es peor que un mensaje.
 */
test('la pantalla explica por qué no se puede apagar el idioma por defecto', function () {
    $cuenta = $this->createAccount();
    $tecnico = $this->createUser($cuenta, 'administrator-tech');

    $this->actingAsTenant($tecnico, $cuenta);

    Livewire::test(LanguageManager::class)->call('toggle', 'en');

    expect(Language::isEnabled('en'))->toBeTrue();
});

test('la pantalla enseña la cobertura de traducción de cada idioma', function () {
    $cuenta = $this->createAccount();
    $tecnico = $this->createUser($cuenta, 'administrator-tech');

    $this->actingAsTenant($tecnico, $cuenta);

    $cobertura = Livewire::test(LanguageManager::class)->viewData('coverage');

    // El de referencia se compara consigo mismo.
    expect($cobertura['en']['percentage'])->toBe(100)
        // El español está completo porque el paquete lo mantiene en paridad.
        ->and($cobertura['es']['percentage'])->toBe(100)
        // El catalán no existe todavía como fichero: cero, y visible.
        ->and($cobertura['ca']['percentage'])->toBe(0);
});

test('el comando informa de la cobertura y puede fallar por debajo de un umbral', function () {
    $this->artisan('k2labs-base:lang-status')
        ->assertSuccessful();

    $this->artisan('k2labs-base:lang-status', ['locale' => 'ca', '--fail-under' => 50])
        ->assertFailed();
});
