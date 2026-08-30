<?php

declare(strict_types=1);

use Base\Tenant\Exceptions\ModuleDisabledException;
use Base\Tenant\Exceptions\UsageLimitExceededException;
use Base\Tenant\Facades\Feature;
use Base\Tenant\Facades\Meter;
use Base\Tenant\Metering\MetricRegistry;
use Base\Tenant\Models\UsageEvent;
use Base\Tenant\Notifications\UsageThresholdReached;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config([
        'base-tenant.metering.metrics' => [
            // Un contador con tope de plan: `max_projects` vale 1 en el plan
            // gratuito, que es el que reciben las cuentas de prueba.
            'projects.created' => [
                'type' => 'counter',
                'reset' => 'month',
                'feature' => 'max_projects',
            ],
            // Un medidor sin tope: se mide, no se limita.
            'api.calls' => [
                'type' => 'counter',
                'reset' => 'day',
            ],
            // Un nivel en unidades distintas a las de su feature.
            'storage.bytes' => [
                'type' => 'gauge',
                'feature' => 'max_storage_gb',
                'scale' => 1073741824,
            ],
        ],
    ]);

    app(MetricRegistry::class)->flush();
});

test('incrementar suma y devuelve el total', function () {
    $cuenta = $this->createAccount();

    expect(Meter::for($cuenta)->increment('api.calls'))->toBe(1)
        ->and(Meter::for($cuenta)->increment('api.calls', 4))->toBe(5)
        ->and(Meter::for($cuenta)->current('api.calls'))->toBe(5);
});

test('decrementar resta y el trail sigue sumando al contador', function () {
    $cuenta = $this->createAccount();

    Meter::for($cuenta)->increment('api.calls', 10);
    Meter::for($cuenta)->decrement('api.calls', 3);

    expect(Meter::for($cuenta)->current('api.calls'))->toBe(7)
        ->and((int) UsageEvent::where('account_id', $cuenta->getKey())->sum('delta'))->toBe(7);
});

/**
 * Cada cuenta lleva su propio contador. Es la garantía que separa un medidor
 * multi-tenant de un contador global con un nombre bonito.
 */
test('el consumo de una cuenta no se ve desde otra', function () {
    $una = $this->createAccount();
    $otra = $this->createAccount();

    Meter::for($una)->increment('api.calls', 7);

    expect(Meter::for($otra)->current('api.calls'))->toBe(0);
});

test('un contador mensual separa los periodos', function () {
    $cuenta = $this->createAccount();

    $this->travelTo(now()->startOfMonth());
    Meter::for($cuenta)->increment('projects.created', 3);

    $this->travelTo(now()->addMonthNoOverflow()->startOfMonth());

    expect(Meter::for($cuenta)->current('projects.created'))->toBe(0);

    $this->travelBack();
});

/**
 * Un gauge que se reiniciara cada mes se vaciaría solo mientras los ficheros
 * que mide siguen en disco.
 */
test('un gauge no se reinicia con el calendario', function () {
    $cuenta = $this->createAccount();

    Meter::for($cuenta)->set('storage.bytes', 500);

    $this->travelTo(now()->addYear());

    expect(Meter::for($cuenta)->current('storage.bytes'))->toBe(500);

    $this->travelBack();
});

test('poner un valor exacto deja el trail cuadrado', function () {
    $cuenta = $this->createAccount();

    Meter::for($cuenta)->increment('api.calls', 10);
    Meter::for($cuenta)->set('api.calls', 4);

    expect(Meter::for($cuenta)->current('api.calls'))->toBe(4)
        ->and((int) UsageEvent::where('metric', 'api.calls')->sum('delta'))->toBe(4);
});

test('una métrica sin tope no tiene límite ni porcentaje', function () {
    $cuenta = $this->createAccount();

    expect(Meter::for($cuenta)->limit('api.calls'))->toBe(-1)
        ->and(Meter::for($cuenta)->remaining('api.calls'))->toBe(-1)
        ->and(Meter::for($cuenta)->percentage('api.calls'))->toBeNull()
        ->and(Meter::for($cuenta)->wouldExceed('api.calls', 1_000_000))->toBeFalse();
});

/**
 * La feature se escribe en gigabytes porque es lo que se lee bien en una
 * tarifa; la métrica cuenta bytes porque es lo que tiene a mano el código.
 * Comparar las dos sin escalar deja almacenar un gigabyte por cada byte.
 */
test('el tope se traduce a la unidad de la métrica', function () {
    $cuenta = $this->createAccount();

    // El plan gratuito incluye 1 GB.
    expect(Meter::for($cuenta)->limit('storage.bytes'))->toBe(1073741824);

    Meter::for($cuenta)->set('storage.bytes', 1073741824 - 10);

    expect(Meter::for($cuenta)->remaining('storage.bytes'))->toBe(10)
        ->and(Meter::for($cuenta)->wouldExceed('storage.bytes', 11))->toBeTrue()
        ->and(Meter::for($cuenta)->wouldExceed('storage.bytes', 10))->toBeFalse();
});

test('sin tope, escalar no convierte lo ilimitado en un número', function () {
    $cuenta = $this->createAccount();

    Feature::for($cuenta)->set('max_storage_gb', -1);

    expect(Meter::for($cuenta)->limit('storage.bytes'))->toBe(-1);
});

test('incrementOrFail consume hasta el tope y luego se niega', function () {
    $cuenta = $this->createAccount();

    // El plan gratuito incluye un proyecto.
    expect(Meter::for($cuenta)->incrementOrFail('projects.created'))->toBe(1);

    expect(fn () => Meter::for($cuenta)->incrementOrFail('projects.created'))
        ->toThrow(UsageLimitExceededException::class);

    // Lo rechazado no se cobra.
    expect(Meter::for($cuenta)->current('projects.created'))->toBe(1);
});

test('la excepción lleva la métrica, el consumo y el tope', function () {
    $cuenta = $this->createAccount();

    Meter::for($cuenta)->incrementOrFail('projects.created');

    try {
        Meter::for($cuenta)->incrementOrFail('projects.created');
    } catch (UsageLimitExceededException $excepcion) {
        expect($excepcion->metric->key)->toBe('projects.created')
            ->and($excepcion->current)->toBe(1)
            ->and($excepcion->limit)->toBe(1);

        return;
    }

    $this->fail('Debería haber lanzado UsageLimitExceededException.');
});

/**
 * Una clave sin declarar abriría un contador que nada limita, nada enseña y
 * nadie mira: la cuenta pasaría de largo su tope con el medidor a cero.
 */
test('una métrica sin declarar no se puede mover', function () {
    $cuenta = $this->createAccount();

    expect(fn () => Meter::for($cuenta)->increment('inventada'))
        ->toThrow(InvalidArgumentException::class, 'inventada');
});

test('el historial devuelve ceros donde no hubo movimiento', function () {
    $cuenta = $this->createAccount();

    Meter::for($cuenta)->increment('api.calls', 3);

    $historial = Meter::for($cuenta)->history('api.calls', 3);

    expect($historial)->toHaveCount(3)
        ->and(array_values($historial))->toBe([0, 0, 3]);
});

test('el historial de un gauge es un solo periodo', function () {
    $cuenta = $this->createAccount();

    Meter::for($cuenta)->set('storage.bytes', 12);

    expect(Meter::for($cuenta)->history('storage.bytes', 12))->toBe(['' => 12]);
});

test('el resumen trae todas las métricas declaradas, tengan movimiento o no', function () {
    $cuenta = $this->createAccount();

    $resumen = Meter::for($cuenta)->summary();

    expect($resumen)->toHaveCount(3)
        ->and(collect($resumen)->pluck('metric.key')->all())
        ->toBe(['projects.created', 'api.calls', 'storage.bytes']);
});

test('el aviso sale al cruzar el 80% y no se repite en cada incremento', function () {
    Notification::fake();

    $cuenta = $this->createAccount();
    $duena = $this->createUser($cuenta);
    $cuenta->update(['user_id' => $duena->getKey()]);
    $cuenta->refresh();

    Feature::for($cuenta)->set('max_projects', 10);

    Meter::for($cuenta)->increment('projects.created', 7);
    Notification::assertNothingSent();

    Meter::for($cuenta)->increment('projects.created');
    Notification::assertSentToTimes($duena, UsageThresholdReached::class, 1);

    // Sigue por encima del 80% pero no ha cruzado nada nuevo.
    Meter::for($cuenta)->increment('projects.created');
    Notification::assertSentToTimes($duena, UsageThresholdReached::class, 1);
});

test('llegar al 100% avisa una segunda vez', function () {
    Notification::fake();

    $cuenta = $this->createAccount();
    $duena = $this->createUser($cuenta);
    $cuenta->update(['user_id' => $duena->getKey()]);
    $cuenta->refresh();

    Feature::for($cuenta)->set('max_projects', 10);

    Meter::for($cuenta)->increment('projects.created', 8);
    Meter::for($cuenta)->increment('projects.created', 2);

    Notification::assertSentToTimes($duena, UsageThresholdReached::class, 2);
});

/**
 * Bajar del umbral rearma el aviso, pero no manda nada: nadie necesita un
 * correo para saber que su consumo ha bajado.
 */
test('bajar del umbral rearma el aviso sin mandar nada', function () {
    Notification::fake();

    $cuenta = $this->createAccount();
    $duena = $this->createUser($cuenta);
    $cuenta->update(['user_id' => $duena->getKey()]);
    $cuenta->refresh();

    Feature::for($cuenta)->set('max_projects', 10);

    Meter::for($cuenta)->increment('projects.created', 8);
    Notification::assertSentToTimes($duena, UsageThresholdReached::class, 1);

    Meter::for($cuenta)->decrement('projects.created', 5);
    Notification::assertSentToTimes($duena, UsageThresholdReached::class, 1);

    Meter::for($cuenta)->increment('projects.created', 5);
    Notification::assertSentToTimes($duena, UsageThresholdReached::class, 2);
});

/**
 * La integración que pide el spec: la feature sabe su métrica y la métrica sabe
 * leerse, así que no hay que pasarle el consumo desde fuera.
 */
test('withinLimit lee el medidor cuando la feature tiene métrica', function () {
    $cuenta = $this->createAccount();

    Meter::for($cuenta)->set('storage.bytes', 1073741823);

    expect(Feature::for($cuenta)->withinLimit('max_storage_gb'))->toBeTrue();

    Meter::for($cuenta)->set('storage.bytes', 1073741824);

    expect(Feature::for($cuenta)->withinLimit('max_storage_gb'))->toBeFalse();
});

test('withinLimit sigue aceptando un consumo a mano para lo que no se mide', function () {
    $cuenta = $this->createAccount();

    expect(Feature::for($cuenta)->withinLimit('max_users', 2))->toBeTrue()
        ->and(Feature::for($cuenta)->withinLimit('max_users', 3))->toBeFalse();
});

/**
 * Sin métrica y sin consumo no hay respuesta posible. Devolver `true` sería
 * abrir el tope de par en par por omisión.
 */
test('withinLimit sin métrica y sin consumo falla en vez de inventarse un sí', function () {
    $cuenta = $this->createAccount();

    expect(fn () => Feature::for($cuenta)->withinLimit('max_users'))
        ->toThrow(InvalidArgumentException::class, 'max_users');
});

test('con el módulo apagado el medidor no se deja usar', function () {
    config(['base-tenant.metering.enabled' => false]);

    $cuenta = $this->createAccount();

    expect(fn () => Meter::for($cuenta)->increment('api.calls'))
        ->toThrow(ModuleDisabledException::class);
});
