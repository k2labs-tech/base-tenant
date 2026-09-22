<?php

declare(strict_types=1);

use Base\Tenant\Support\ScheduledTasks;
use Illuminate\Console\Scheduling\Schedule;

test('la tabla de tareas programa el mantenimiento sin repetir ninguna', function () {
    $schedule = new Schedule;

    ScheduledTasks::register($schedule);

    $comandos = collect($schedule->events())
        ->map(fn ($evento): string => (string) $evento->command)
        ->filter(fn (string $comando): bool => str_contains($comando, 'k2labs-base:'));

    expect($comandos)->not->toBeEmpty()
        ->and($comandos->unique()->count())->toBe($comandos->count())
        ->and($comandos->implode(' '))->toContain('k2labs-base:prune-activity-log');
});

/**
 * Con el código copiado en la aplicación, el provider generado es quien
 * programa: si el del paquete lo hiciera también —y lo hacía, porque la
 * llamada estaba antes del guard— cada tarea correría dos veces.
 */
test('el paquete deja de programar en cuanto la aplicación se queda el código', function () {
    $fuente = file_get_contents(dirname(__DIR__, 2).'/src/BaseTenantServiceProvider.php');

    $guard = mb_strpos($fuente, 'if ($this->hasBeenScaffolded()) {');
    $llamada = mb_strpos($fuente, '$this->registerSchedule();');

    expect($guard)->not->toBeFalse()
        ->and($llamada)->not->toBeFalse()
        ->and($llamada)->toBeGreaterThan($guard);
});
