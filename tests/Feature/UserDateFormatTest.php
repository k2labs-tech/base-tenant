<?php

declare(strict_types=1);

/**
 * El accesor leía `hour_format`, una columna que no existe. La concatenación
 * daba «d/m/Y » -- fecha, espacio, nada -- y el respaldo de detrás nunca
 * entraba, porque concatenar un null da cadena vacía y no null.
 */
test('la fecha con hora usa el formato del usuario', function () {
    $cuenta = $this->createAccount();

    $usuaria = $this->createUser($cuenta, null, [
        'date_format' => 'd/m/Y',
        'time_format' => 'H:i',
        'timezone' => 'UTC',
    ]);

    $salida = $usuaria->applyDateTimeZoneFormat('2026-08-18 14:30:00');

    expect($salida)->toBe('18/08/2026 14:30');
});

test('un formato propio se respeta tal cual', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta, null, ['timezone' => 'UTC']);

    expect($usuaria->applyDateTimeZoneFormat('2026-08-18 14:30:00', 'Y-m-d'))->toBe('2026-08-18');
});

/**
 * Sin preferencias guardadas tiene que salir algo legible, no una fecha
 * coja con un espacio al final.
 */
test('sin formatos guardados sale una fecha completa', function () {
    $cuenta = $this->createAccount();

    $usuaria = $this->createUser($cuenta, null, [
        'date_format' => '',
        'time_format' => '',
        'timezone' => 'UTC',
    ]);

    $salida = $usuaria->applyDateTimeZoneFormat('2026-08-18 14:30:00');

    expect($salida)->toBe('18/08/2026 14:30:00')
        ->and($salida)->not->toEndWith(' ');
});
