<?php

declare(strict_types=1);

use Base\Tenant\Facades\Meter;
use Base\Tenant\Metering\MetricRegistry;
use Base\Tenant\Models\UsageEvent;

beforeEach(function () {
    config([
        'base-tenant.metering.metrics' => [
            // Se factura: declara medidor de Stripe.
            'api.calls' => [
                'type' => 'counter',
                'reset' => 'day',
                'stripe_meter' => 'api_calls',
            ],
            // Se mide para el producto y nunca sale de la base de datos.
            'projects.created' => [
                'type' => 'counter',
            ],
        ],
    ]);

    app(MetricRegistry::class)->flush();
});

test('el ensayo enseña el delta y no marca nada como reportado', function () {
    $cuenta = $this->createAccount();

    Meter::for($cuenta)->increment('api.calls', 4);
    Meter::for($cuenta)->decrement('api.calls', 1);

    // En una sola comprobación: cada `expectsOutputToContain` consume la línea
    // que casa, y las dos cadenas viven en la misma.
    $this->artisan('k2labs-base:report-usage', ['--dry-run' => true])
        ->expectsOutputToContain('api.calls +3 (2 events)')
        ->assertSuccessful();

    expect(UsageEvent::whereNotNull('reported_at')->count())->toBe(0);
});

/**
 * Lo que no declara medidor de Stripe se mide para el producto y no se factura.
 * Mandarlo sería cobrar por algo que nadie puso en una tarifa.
 */
test('una métrica sin medidor de Stripe no se reporta nunca', function () {
    $cuenta = $this->createAccount();

    Meter::for($cuenta)->increment('projects.created', 5);

    $this->artisan('k2labs-base:report-usage')->assertSuccessful();

    expect(UsageEvent::where('metric', 'projects.created')->whereNotNull('reported_at')->count())
        ->toBe(0);
});

/**
 * Una cuenta sin cliente de Stripe no se está facturando: su consumo no tiene
 * dónde ir. Se marca igualmente, o sería un residente permanente de cada
 * ejecución.
 */
test('una cuenta sin cliente de Stripe se salta sin fallar y no se reintenta', function () {
    $cuenta = $this->createAccount();

    expect($cuenta->stripe_id)->toBeNull();

    Meter::for($cuenta)->increment('api.calls', 2);

    $this->artisan('k2labs-base:report-usage')->assertSuccessful();

    expect(UsageEvent::where('metric', 'api.calls')->whereNull('reported_at')->count())->toBe(0);
});

test('lo ya reportado no se vuelve a mandar', function () {
    $cuenta = $this->createAccount();

    Meter::for($cuenta)->increment('api.calls', 2);

    $this->artisan('k2labs-base:report-usage')->assertSuccessful();

    $this->artisan('k2labs-base:report-usage', ['--dry-run' => true])
        ->doesntExpectOutputToContain('api.calls')
        ->assertSuccessful();
});

test('con el módulo apagado el comando no toca nada', function () {
    $cuenta = $this->createAccount();

    Meter::for($cuenta)->increment('api.calls', 2);

    config(['base-tenant.metering.enabled' => false]);

    $this->artisan('k2labs-base:report-usage')->assertSuccessful();

    expect(UsageEvent::whereNull('reported_at')->count())->toBe(1);
});
