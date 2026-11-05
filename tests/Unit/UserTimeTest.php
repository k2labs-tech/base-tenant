<?php

declare(strict_types=1);

use Carbon\Carbon;
use Carbon\CarbonImmutable;

afterEach(function () {
    config(['app.timezone' => 'UTC', 'base-tenant.display_timezone' => null]);
});

test('una fecha se lee en la zona horaria de quien la mira', function () {
    // La hora repetida del cambio de otoño: en UTC no es ambigua, y a la vista
    // se lee con su desfase (las 02:30 de después del cambio, +01:00).
    $moment = CarbonImmutable::parse('2026-10-25 01:30:00', 'UTC');
    $persona = (object) ['timezone' => 'Europe/Madrid'];

    expect($moment->userTime($persona)->format('Y-m-d H:i P'))->toBe('2026-10-25 02:30 +01:00')
        ->and($moment->userTime($persona)->getTimezone()->getName())->toBe('Europe/Madrid')
        ->and($moment->getTimezone()->getName())->toBe('UTC');
});

test('sin zona propia se lee en la de visualización y, sin ella, en la de la aplicación', function () {
    $moment = Carbon::parse('2026-07-01 10:00:00', 'UTC');

    config(['base-tenant.display_timezone' => 'America/New_York']);

    expect($moment->userTime((object) ['timezone' => ''])->format('H:i'))->toBe('06:00')
        ->and($moment->userTime()->format('H:i'))->toBe('06:00');

    config(['base-tenant.display_timezone' => null, 'app.timezone' => 'Europe/Madrid']);

    expect($moment->userTime()->format('H:i'))->toBe('12:00');
});

test('una zona que php no conoce no rompe la página', function () {
    $moment = CarbonImmutable::parse('2026-07-01 10:00:00', 'UTC');

    config(['base-tenant.display_timezone' => 'Europe/Madrid']);

    expect($moment->userTime((object) ['timezone' => 'Mars/Olympus'])->format('H:i'))->toBe('12:00');
});

test('no cambia la fecha original, aunque sea mutable', function () {
    $moment = Carbon::parse('2026-07-01 10:00:00', 'UTC');

    $moment->userTime((object) ['timezone' => 'Asia/Tokyo']);

    expect($moment->format('H:i e'))->toBe('10:00 UTC');
});
