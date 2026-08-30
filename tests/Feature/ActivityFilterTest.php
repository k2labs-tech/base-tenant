<?php

declare(strict_types=1);

use Base\Tenant\Facades\Tenant;
use Base\Tenant\Models\ActivityLog;
use Base\Tenant\Services\ActivityLogService;

/**
 * El registro de actividad guarda los valores viejo y nuevo de cada cambio.
 * Las columnas que llevan credenciales están cifradas en reposo, y que una de
 * ellas aterrice en claro en el rastro de auditoría deshace ese cifrado en la
 * primera edición.
 */
test('ningún campo sensible llega al registro de actividad', function () {
    $cuenta = $this->createAccount();

    $sensibles = [
        'password' => 'secreta',
        'remember_token' => 'recuerdame',
        'two_factor_secret' => 'ABC123',
        'two_factor_recovery_codes' => '["uno","dos"]',
        'credentials' => '{"token":"del-cliente"}',
        'secret' => 'firma-del-webhook',
        'token' => 'de-acceso',
        'refresh_token' => 'de-refresco',
    ];

    Tenant::runFor($cuenta, fn () => ActivityLogService::log(
        action: 'updated',
        oldValues: [...$sensibles, 'name' => 'Antes'],
        newValues: [...$sensibles, 'name' => 'Después'],
    ));

    $entrada = ActivityLog::query()->forAccount($cuenta)->first();

    // Sin la bandera, `json_encode` escapa los acentos y «Después» se
    // convierte en «Despu\u00e9s», que no casa con nada.
    $volcado = json_encode($entrada->properties, JSON_UNESCAPED_UNICODE);

    foreach (array_keys($sensibles) as $campo) {
        expect($volcado)->not->toContain($campo, "`{$campo}` ha llegado al registro.");
    }

    foreach (array_values($sensibles) as $valor) {
        expect($volcado)->not->toContain($valor);
    }

    // Y lo que no es sensible sí se guarda: un filtro que se lo lleva todo
    // dejaría el registro sin nada que auditar.
    expect($volcado)->toContain('Antes')->toContain('Después');
});

/**
 * La lista es la garantía; que esté escrita en algún sitio no lo es. Si alguien
 * la recorta, esto lo dice.
 */
test('la lista de campos filtrados cubre lo que guardan los módulos', function () {
    foreach (['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'credentials', 'secret', 'token', 'refresh_token'] as $campo) {
        expect(ActivityLogService::filterSensitive([$campo => 'x', 'otro' => 'y']))
            ->toBe(['otro' => 'y'], "`{$campo}` ya no se filtra.");
    }
});
