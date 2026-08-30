<?php

declare(strict_types=1);

use Base\Tenant\Console\Commands\PublishAgentDocsCommand;
use Base\Tenant\Support\Module;
use Illuminate\Support\Facades\File;

/**
 * El fichero de cada módulo, si lo hay.
 *
 * @return array<string, string>
 */
function documentosDeModulo(): array
{
    return [
        Module::METERING => '10-metering.md',
        Module::FILES => '11-files.md',
        Module::TRANSFER => '12-transfer.md',
        Module::LANGUAGES => '20-languages.md',
        Module::SOCIAL => '21-social-login.md',
        Module::SEQUENCES => '22-sequences.md',
        Module::ONBOARDING => '23-onboarding.md',
        Module::SUPPRESSIONS => '24-suppressions.md',
        Module::GDPR => '25-gdpr.md',
        Module::PRESALE => '26-presale.md',
        Module::CONNECTIONS => '14-connections.md',
    ];
}

function rutaDeDocs(): string
{
    return dirname(__DIR__, 2).'/docs/agents';
}

/**
 * El test de manifiesto que pide el spec: un módulo encendido sin documentar es
 * una capacidad que ningún agente va a encontrar, y que por tanto va a
 * reimplementar.
 */
test('todo módulo activo tiene su documento', function () {
    $documentos = documentosDeModulo();

    foreach (Module::active() as $modulo) {
        // Los webhooks se documentan junto a las conexiones: son el mismo
        // módulo desde el punto de vista de quien lo va a usar.
        if ($modulo === Module::WEBHOOKS) {
            continue;
        }

        $fichero = $documentos[$modulo] ?? null;

        expect($fichero)->not->toBeNull("El módulo `{$modulo}` no tiene documento asignado en este test.");

        expect(File::exists(rutaDeDocs().'/'.$fichero))->toBeTrue(
            "El módulo `{$modulo}` está activo y no tiene `docs/agents/{$fichero}`."
        );
    }
});

/**
 * Un documento que existe pero no está en el índice es un documento que nadie
 * abre: el agente entra por el índice.
 */
test('todo documento aparece en el índice', function () {
    $indice = File::get(rutaDeDocs().'/00-index.md');

    foreach (File::files(rutaDeDocs()) as $fichero) {
        if ($fichero->getFilename() === '00-index.md') {
            continue;
        }

        expect(str_contains($indice, $fichero->getFilename()))->toBeTrue(
            "`{$fichero->getFilename()}` existe pero no aparece en 00-index.md."
        );
    }
});

/**
 * Y al revés: el índice no puede prometer un fichero que no está.
 */
test('el índice no enlaza documentos que no existen', function () {
    $indice = File::get(rutaDeDocs().'/00-index.md');

    preg_match_all('/\(([0-9]{2}-[a-z-]+\.md)\)/', $indice, $coincidencias);

    foreach (array_unique($coincidencias[1]) as $enlace) {
        expect(File::exists(rutaDeDocs().'/'.$enlace))->toBeTrue(
            "00-index.md enlaza `{$enlace}`, que no existe."
        );
    }
});

/**
 * El objetivo del spec es que cada fichero quepa en una ventana de contexto
 * junto al código de la tarea.
 */
test('ningún documento pasa de 400 líneas', function () {
    foreach (File::files(rutaDeDocs()) as $fichero) {
        $lineas = substr_count(File::get($fichero->getPathname()), "\n");

        expect($lineas)->toBeLessThanOrEqual(400, "`{$fichero->getFilename()}` tiene {$lineas} líneas.");
    }
});

/**
 * Las cuatro preguntas, en orden: cuándo usar esto, cuándo no, la API, y qué no
 * hacer. La tercera varía de nombre entre módulos, así que solo se exigen las
 * que son literales.
 */
test('cada documento de módulo dice cuándo usarlo y cuándo no', function () {
    foreach (File::files(rutaDeDocs()) as $fichero) {
        if (in_array($fichero->getFilename(), ['00-index.md'], true)) {
            continue;
        }

        $contenido = File::get($fichero->getPathname());

        expect($contenido)->toContain('## When to use this')
            ->and($contenido)->toContain('## When NOT to use this');
    }
});

test('publicar copia los documentos y crea el stub con marcadores', function () {
    $this->artisan('k2labs-base:publish-agent-docs')->assertSuccessful();

    expect(File::exists(base_path('docs/agents/base/00-index.md')))->toBeTrue()
        ->and(File::exists(base_path('CLAUDE.md')))->toBeTrue();

    $stub = File::get(base_path('CLAUDE.md'));

    expect($stub)->toContain(PublishAgentDocsCommand::BEGIN)
        ->and($stub)->toContain('docs/agents/base/00-index.md');
});

/**
 * Se relanza tras cada `composer update` del paquete, así que tiene que poder
 * ejecutarse muchas veces sin acumular secciones.
 */
test('publicar dos veces no duplica la sección', function () {
    $this->artisan('k2labs-base:publish-agent-docs')->assertSuccessful();
    $this->artisan('k2labs-base:publish-agent-docs')->assertSuccessful();

    $stub = File::get(base_path('CLAUDE.md'));

    expect(substr_count($stub, PublishAgentDocsCommand::BEGIN))->toBe(1)
        ->and(substr_count($stub, PublishAgentDocsCommand::END))->toBe(1);
});

/**
 * Lo que el proyecto escriba fuera de los marcadores es suyo. Pisarlo sería la
 * razón por la que nadie vuelve a ejecutar el comando.
 */
test('publicar respeta lo que el proyecto escribió alrededor', function () {
    File::put(base_path('CLAUDE.md'), "# Mi proyecto\n\nInstrucciones propias.\n");

    $this->artisan('k2labs-base:publish-agent-docs')->assertSuccessful();

    $stub = File::get(base_path('CLAUDE.md'));

    expect($stub)->toContain('Instrucciones propias.')
        ->and($stub)->toContain(PublishAgentDocsCommand::BEGIN);

    // Y una segunda pasada sigue sin tocarlas.
    File::put(base_path('CLAUDE.md'), str_replace('Instrucciones propias.', 'Instrucciones editadas.', $stub));

    $this->artisan('k2labs-base:publish-agent-docs')->assertSuccessful();

    expect(File::get(base_path('CLAUDE.md')))->toContain('Instrucciones editadas.');
});

test('publicar no pisa un documento que el proyecto ha editado', function () {
    $this->artisan('k2labs-base:publish-agent-docs')->assertSuccessful();

    File::put(base_path('docs/agents/base/00-index.md'), '# Editado por el proyecto');

    $this->artisan('k2labs-base:publish-agent-docs')->assertSuccessful();

    expect(File::get(base_path('docs/agents/base/00-index.md')))->toBe('# Editado por el proyecto');

    $this->artisan('k2labs-base:publish-agent-docs', ['--force' => true])->assertSuccessful();

    expect(File::get(base_path('docs/agents/base/00-index.md')))->toContain('Capability map');
});

afterEach(function () {
    foreach (['CLAUDE.md', 'AGENTS.md'] as $stub) {
        File::delete(base_path($stub));
    }

    File::deleteDirectory(base_path('docs/agents'));
});
