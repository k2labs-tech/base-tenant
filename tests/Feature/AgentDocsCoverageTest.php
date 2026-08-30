<?php

declare(strict_types=1);

use Base\Tenant\BaseTenantServiceProvider;
use Base\Tenant\Support\Module;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Cobertura documental, comprobada y no afirmada.
 *
 * `AgentDocsTest` vigila la forma de los documentos -- que existan, que estén
 * en el índice, que no pasen de 400 líneas. Esto vigila lo contrario: que no
 * haya nada público del paquete que no aparezca en ellos.
 *
 * Sin esta prueba, «el 100% está documentado» es una frase que envejece en
 * cuanto alguien añade un facade. Con ella, añadirlo sin documentarlo rompe la
 * suite en el mismo commit.
 */
function textoDeLosDocumentos(): string
{
    static $texto = null;

    if ($texto !== null) {
        return $texto;
    }

    $texto = '';

    foreach (File::files(dirname(__DIR__, 2).'/docs/agents') as $fichero) {
        if ($fichero->getExtension() === 'md') {
            $texto .= File::get($fichero->getPathname())."\n";
        }
    }

    return $texto;
}

/**
 * @param  list<string>  $items  término a buscar => cómo llamarlo en el fallo
 */
function exigirCobertura(array $items, string $que): void
{
    $faltan = array_values(array_filter(
        $items,
        fn (string $item): bool => ! str_contains(textoDeLosDocumentos(), $item),
    ));

    expect($faltan)->toBe([], "Sin documentar en docs/agents/ ({$que}): ".implode(', ', $faltan));
}

test('todo facade aparece en la documentación de agentes', function () {
    $facades = collect(File::files(dirname(__DIR__, 2).'/src/Facades'))
        ->map(fn ($f): string => $f->getFilenameWithoutExtension().'::')
        ->all();

    expect($facades)->not->toBeEmpty();

    exigirCobertura($facades, 'facades');
});

/**
 * Los traits son la superficie que una aplicación anfitriona pone en sus
 * propios modelos: si uno no está documentado, nadie lo usa y se reimplementa.
 */
test('todo trait aparece en la documentación de agentes', function () {
    $traits = collect(File::files(dirname(__DIR__, 2).'/src/Traits'))
        ->map(fn ($f): string => $f->getFilenameWithoutExtension())
        ->all();

    expect($traits)->not->toBeEmpty();

    exigirCobertura($traits, 'traits');
});

/**
 * Cada interfaz es un punto de extensión: la forma en que el anfitrión añade
 * un proveedor, un exportador o un resolutor.
 */
test('toda interfaz de extensión aparece en la documentación', function () {
    $interfaces = [];

    foreach (File::allFiles(dirname(__DIR__, 2).'/src') as $fichero) {
        if ($fichero->getExtension() !== 'php') {
            continue;
        }

        if (preg_match('/^interface (\w+)/m', File::get($fichero->getPathname()), $coincidencia)) {
            $interfaces[] = $coincidencia[1];
        }
    }

    expect($interfaces)->not->toBeEmpty();

    exigirCobertura($interfaces, 'interfaces');
});

test('todo alias de middleware aparece en la documentación', function () {
    $alias = array_keys(BaseTenantServiceProvider::middlewareAliases());

    expect($alias)->not->toBeEmpty();

    exigirCobertura($alias, 'middleware');
});

test('todo comando aparece en la documentación', function () {
    $comandos = collect(Artisan::all())
        ->filter(fn ($c): bool => str_starts_with($c::class, 'Base\\Tenant\\'))
        ->map(fn ($c): string => (string) $c->getName())
        ->values()
        ->all();

    expect($comandos)->not->toBeEmpty();

    exigirCobertura($comandos, 'comandos');
});

/**
 * Los componentes salen del registro del proveedor y no de un listado a mano:
 * uno nuevo entra solo en la exigencia, que es lo que impide que esto
 * envejezca sin que nadie se entere.
 */
test('todo componente Livewire aparece en la documentación', function () {
    $componentes = array_keys(BaseTenantServiceProvider::livewireComponents());

    expect($componentes)->not->toBeEmpty();

    exigirCobertura($componentes, 'componentes Livewire');
});

test('todo componente Blade aparece en la documentación', function () {
    // Los de clase salen del registro; los anónimos son ficheros de vista y no
    // están registrados en ninguna parte, así que se leen del directorio.
    $deClase = array_keys(BaseTenantServiceProvider::bladeComponents());

    $anonimos = collect(File::files(dirname(__DIR__, 2).'/resources/views/components'))
        ->map(fn ($f): string => str_replace('.blade', '', $f->getFilenameWithoutExtension()))
        ->all();

    $componentes = array_values(array_unique([...$deClase, ...$anonimos]));

    expect($componentes)->not->toBeEmpty();

    exigirCobertura($componentes, 'componentes Blade');
});

test('toda directiva Blade aparece en la documentación', function () {
    $proveedor = File::get(dirname(__DIR__, 2).'/src/BaseTenantServiceProvider.php');

    preg_match_all("/Blade::if\('(\w+)'/", $proveedor, $coincidencias);

    $directivas = array_map(fn (string $d): string => '@'.$d, $coincidencias[1]);

    expect($directivas)->not->toBeEmpty();

    exigirCobertura($directivas, 'directivas Blade');
});

test('todo modelo aparece en la documentación', function () {
    $modelos = collect(File::files(dirname(__DIR__, 2).'/src/Models'))
        ->map(fn ($f): string => $f->getFilenameWithoutExtension())
        ->all();

    expect($modelos)->not->toBeEmpty();

    exigirCobertura($modelos, 'modelos');
});

test('todo servicio aparece en la documentación', function () {
    $servicios = collect(File::files(dirname(__DIR__, 2).'/src/Services'))
        ->map(fn ($f): string => $f->getFilenameWithoutExtension())
        ->all();

    expect($servicios)->not->toBeEmpty();

    exigirCobertura($servicios, 'servicios');
});

/**
 * Cada módulo declarado tiene que estar nombrado en alguna parte: uno que
 * exista en `Module::all()` y en ningún documento es una capacidad que ningún
 * agente va a encontrar.
 */
test('todo módulo aparece en la documentación', function () {
    exigirCobertura(Module::all(), 'módulos');
});
