<?php

declare(strict_types=1);

use Base\Tenant\Exceptions\ModuleDisabledException;
use Base\Tenant\Facades\Sequence;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\Sequence as SequenceModel;

test('la numeración empieza en uno y no se salta nada', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        expect(Sequence::next('bookings'))->toBe('00001')
            ->and(Sequence::next('bookings'))->toBe('00002')
            ->and(Sequence::next('bookings'))->toBe('00003');
    });
});

test('mirar no consume', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        expect(Sequence::peek('bookings'))->toBe('00001')
            ->and(Sequence::peek('bookings'))->toBe('00001')
            ->and(Sequence::next('bookings'))->toBe('00001')
            ->and(Sequence::peek('bookings'))->toBe('00002');
    });
});

test('dos claves distintas llevan cuentas distintas', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        Sequence::next('bookings');
        Sequence::next('bookings');

        expect(Sequence::next('invoices'))->toBe('00001')
            ->and(Sequence::next('bookings'))->toBe('00003');
    });
});

/**
 * La garantía que separa una secuencia multi-tenant de un contador global con
 * un nombre bonito: la reserva número 1 de una empresa no es la de otra.
 */
test('cada cuenta numera desde su propio uno', function () {
    $una = $this->createAccount();
    $otra = $this->createAccount();

    Tenant::runFor($una, fn () => Sequence::next('bookings'));
    Tenant::runFor($una, fn () => Sequence::next('bookings'));

    expect(Tenant::runFor($otra, fn () => Sequence::next('bookings')))->toBe('00001');
    expect(Tenant::runFor($una, fn () => Sequence::next('bookings')))->toBe('00003');
});

test('una secuencia global es la misma se mire desde donde se mire', function () {
    $una = $this->createAccount();
    $otra = $this->createAccount();

    expect(Tenant::runFor($una, fn () => Sequence::next('folio', account: SequenceModel::GLOBAL)))->toBe('00001');
    expect(Tenant::runFor($otra, fn () => Sequence::next('folio', account: SequenceModel::GLOBAL)))->toBe('00002');
});

test('el formato compone la referencia con el año y el número', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        $referencia = Sequence::next('references', format: 'R{year}-{number:5}', period: 'year');

        expect($referencia)->toBe('R'.now()->format('Y').'-00001');
    });
});

test('el número sin relleno también se puede pedir', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        expect(Sequence::next('libres', format: 'nº {number}'))->toBe('nº 1');
    });
});

/**
 * El reinicio anual ocurre solo, sin que nadie ejecute nada el día uno.
 */
test('una secuencia anual vuelve a empezar al cambiar de año', function () {
    $cuenta = $this->createAccount();

    $this->travelTo(now()->setDate(2026, 6, 1));

    Tenant::runFor($cuenta, function () {
        Sequence::next('invoices', period: 'year');
        expect(Sequence::next('invoices', period: 'year'))->toBe('00002');
    });

    $this->travelTo(now()->setDate(2027, 1, 1));

    Tenant::runFor($cuenta, function () {
        expect(Sequence::next('invoices', period: 'year'))->toBe('00001');
    });

    $this->travelBack();
});

/**
 * Un número tomado del contador de 2026 no puede imprimir 2027 porque se pidió
 * un minuto después de medianoche: la fecha sale del periodo al que pertenece.
 */
test('la fecha del formato sale del periodo, no del reloj', function () {
    $cuenta = $this->createAccount();

    $this->travelTo(now()->setDate(2026, 12, 31)->setTime(23, 59, 59));

    $referencia = Tenant::runFor($cuenta, fn () => Sequence::next(
        'invoices',
        format: 'F{year}-{number:4}',
        period: 'year',
    ));

    expect($referencia)->toBe('F2026-0001');

    $this->travelBack();
});

test('una secuencia sin periodo no se reinicia nunca', function () {
    $cuenta = $this->createAccount();

    $this->travelTo(now()->setDate(2026, 6, 1));
    Tenant::runFor($cuenta, fn () => Sequence::next('folio'));

    $this->travelTo(now()->setDate(2030, 6, 1));

    expect(Tenant::runFor($cuenta, fn () => Sequence::next('folio')))->toBe('00002');

    $this->travelBack();
});

test('un periodo inventado no se acepta en silencio', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        expect(fn () => Sequence::next('x', period: 'quincenal'))
            ->toThrow(InvalidArgumentException::class, 'quincenal');
    });
});

/**
 * Para migrar un producto cuya numeración tiene que continuar donde la dejó el
 * sistema anterior.
 */
test('el contador se puede colocar donde lo dejó el sistema viejo', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        Sequence::setNext('invoices', 4312);

        expect(Sequence::next('invoices'))->toBe('04312')
            ->and(Sequence::next('invoices'))->toBe('04313');
    });
});

test('el contador no se puede poner por debajo de uno', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        expect(fn () => Sequence::setNext('invoices', 0))->toThrow(InvalidArgumentException::class);
    });
});

/**
 * Cien tomas seguidas no repiten ni se saltan ninguna. No prueba la
 * concurrencia -- eso pide procesos de verdad -- pero sí que la transacción
 * lee y escribe la misma fila y no una copia en memoria.
 */
test('cien tomas seguidas dan cien números distintos y consecutivos', function () {
    $cuenta = $this->createAccount();

    $numeros = Tenant::runFor($cuenta, function (): array {
        $tomados = [];

        for ($i = 0; $i < 100; $i++) {
            $tomados[] = Sequence::next('carga', format: '{number}');
        }

        return $tomados;
    });

    expect($numeros)->toHaveCount(100)
        ->and(array_unique($numeros))->toHaveCount(100)
        ->and($numeros[0])->toBe('1')
        ->and($numeros[99])->toBe('100');
});

test('con el módulo apagado no se puede numerar', function () {
    config(['base-tenant.sequences.enabled' => false]);

    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        expect(fn () => Sequence::next('bookings'))
            ->toThrow(ModuleDisabledException::class);
    });
});
