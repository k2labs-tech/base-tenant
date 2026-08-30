<?php

declare(strict_types=1);

use Base\Tenant\Facades\Language as LanguageFacade;
use Base\Tenant\Facades\Meter;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Livewire\AccountManager;
use Base\Tenant\Livewire\ActivityLog;
use Base\Tenant\Livewire\Concerns\InteractsWithTable;
use Base\Tenant\Livewire\ConnectionManager as ConnectionManagerScreen;
use Base\Tenant\Livewire\FeatureManager;
use Base\Tenant\Livewire\FileLibrary;
use Base\Tenant\Livewire\InvitationManager;
use Base\Tenant\Livewire\LanguageManager as LanguageManagerScreen;
use Base\Tenant\Livewire\TransferManager as TransferManagerScreen;
use Base\Tenant\Livewire\UsageManager;
use Base\Tenant\Livewire\UserManager;
use Base\Tenant\Metering\MetricRegistry;
use Base\Tenant\Models\AccountConnection as AccountConnectionModel;
use Base\Tenant\Models\ActivityLog as ActivityLogModel;
use Base\Tenant\Models\DataTransfer as DataTransferModel;
use Base\Tenant\Models\File as FileModel;
use Base\Tenant\Models\Language as LanguageModel;
use Base\Tenant\Models\UserInvite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Livewire;

beforeEach(function () {
    $this->syncPermissions();
});

/**
 * Las pantallas se descubren leyendo `src/Livewire`, no se listan a mano: una
 * sexta que use el trait entra sola en todas las pruebas de este fichero.
 *
 * Definidas dentro del fichero y con nombre propio porque Pest carga todos los
 * ficheros de prueba en el mismo proceso y dos funciones con el mismo nombre
 * chocarían.
 *
 * @return array<int, class-string>
 */
function pantallasTabulares(): array
{
    // `glob` y no el facade `File`: los datasets se resuelven al recolectar los
    // tests, antes de que la aplicación esté levantada, y ahí un facade no
    // responde. Devolvía una lista vacía y los tests se saltaban enteros.
    // Recursivo: una pantalla tabular guardada en un subdirectorio se
    // escapaba del contrato entero sin que nada lo dijera.
    $raiz = dirname(__DIR__, 2).'/src/Livewire';

    $ficheros = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz)) as $fichero) {
        if ($fichero->isFile() && $fichero->getExtension() === 'php') {
            $ficheros[] = $fichero->getPathname();
        }
    }

    $clases = [];

    foreach ($ficheros as $fichero) {
        $relativa = trim(str_replace($raiz, '', substr($fichero, 0, -4)), '/');
        $clase = 'Base\\Tenant\\Livewire\\'.str_replace('/', '\\', $relativa);

        if (! class_exists($clase)) {
            continue;
        }

        if (in_array(InteractsWithTable::class, class_uses_recursive($clase), true)) {
            $clases[] = $clase;
        }
    }

    sort($clases);

    return $clases;
}

/**
 * Aparta en el tiempo el `updated_at` de dos filas.
 *
 * Sin esto las dos comparten segundo, ordenar por `updated_at` no mueve nada y
 * el test de la lista blanca pasaría con la lista blanca quitada: es decir, no
 * probaría nada. Se escribe por debajo del modelo para no tocar los timestamps.
 */
function separarEnElTiempo(string $tabla, string $primero, string $ultimo): void
{
    DB::table($tabla)->where('id', $primero)->update(['updated_at' => now()->subWeek()]);
    DB::table($tabla)->where('id', $ultimo)->update(['updated_at' => now()]);
}

/**
 * Lo que cada pantalla necesita para tener datos delante. El descubrimiento es
 * automático, pero los datos no pueden serlo: si aparece una pantalla sin
 * ficha, el test falla en vez de dejarla pasar en silencio.
 *
 * `columnaNoDeclarada` tiene que ser una columna real y ausente de
 * `sortableColumns()`. Una inventada no prueba nada: SQLite degrada un
 * identificador desconocido a literal de texto y ordena por una constante, así
 * que el test pasaría igual sin lista blanca.
 *
 * @return array<class-string, array<string, mixed>>
 */
function fichasDePantalla(): array
{
    return [
        UserManager::class => [
            'rol' => 'customer-admin',
            // No declarada en users (name, email, created_at). Se siembra en el
            // orden que hace que `updated_at desc` NO coincida con `name asc`:
            // si coincidieran, el test pasaría con o sin lista blanca.
            'columnaNoDeclarada' => 'updated_at',
            'vacioInicial' => __('base-tenant::common.empty_title'),
            'sembrar' => function ($cuenta, Closure $ayudante): array {
                $primero = $ayudante('createUser', $cuenta, 'customer-user', ['name' => 'Aaa Primero']);
                $ultimo = $ayudante('createUser', $cuenta, 'customer-user', ['name' => 'Zzz Ultimo']);

                separarEnElTiempo('users', $primero->getKey(), $ultimo->getKey());

                return ['Aaa Primero', 'Zzz Ultimo'];
            },
        ],

        AccountManager::class => [
            'rol' => 'customer-admin',
            'columnaNoDeclarada' => 'updated_at',
            'vacioInicial' => __('base-tenant::common.empty_title'),
            'preparar' => function ($cuenta, $admin, Closure $ayudante): void {
                $otra = $ayudante('createAccount', ['name' => 'Zzz Segunda']);
                $admin->accounts()->syncWithoutDetaching([$otra->getKey()]);

                separarEnElTiempo('accounts', $cuenta->getKey(), $otra->getKey());
            },
            // La cuenta principal se llama «Aaa Principal» y se crea antes.
            'sembrar' => fn ($cuenta, Closure $ayudante): array => ['Aaa Principal', 'Zzz Segunda'],
        ],

        InvitationManager::class => [
            'rol' => 'customer-admin',
            'columnaNoDeclarada' => 'updated_at',
            'vacioInicial' => __('base-tenant::common.empty_title'),
            'sembrar' => function ($cuenta, Closure $ayudante): array {
                $ids = [];

                foreach (['aaa@ejemplo.test', 'zzz@ejemplo.test'] as $correo) {
                    $ids[] = Tenant::runFor($cuenta, fn () => UserInvite::create([
                        'email' => $correo,
                        'token' => Str::random(64),
                        'expires_at' => now()->addWeek(),
                    ]))->getKey();
                }

                separarEnElTiempo('user_invites', $ids[0], $ids[1]);

                return ['aaa@ejemplo.test', 'zzz@ejemplo.test'];
            },
        ],

        ActivityLog::class => [
            'rol' => 'customer-admin',
            'columnaNoDeclarada' => 'description',
            'vacioInicial' => __('base-tenant::activity.no_activities_found'),
            // Por fecha descendente sale «aaa reciente» primero; por
            // descripción descendente saldría «zzz vieja». Distinto a propósito.
            'sembrar' => function ($cuenta, Closure $ayudante): array {
                Tenant::runFor($cuenta, fn () => ActivityLogModel::create([
                    'action' => 'created',
                    'description' => 'zzz vieja',
                    'created_at' => now()->subWeek(),
                ]));

                Tenant::runFor($cuenta, fn () => ActivityLogModel::create([
                    'action' => 'created',
                    'description' => 'aaa reciente',
                    'created_at' => now()->subMinute(),
                ]));

                return ['aaa reciente', 'zzz vieja'];
            },
        ],

        UsageManager::class => [
            'rol' => 'customer-admin',
            // `usage` es real en cada fila y no está declarada como ordenable.
            // Por nombre ascendente sale «Almacenamiento» antes que «Llamadas»;
            // por consumo descendente saldría al revés, que es justo lo que
            // hace que el test distinga tener lista blanca de no tenerla.
            'columnaNoDeclarada' => 'usage',
            'vacioInicial' => __('base-tenant::metering.empty'),
            'preparar' => function ($cuenta, $admin, Closure $ayudante): void {
                config([
                    'base-tenant.metering.metrics' => [
                        'storage.bytes' => ['type' => 'gauge', 'label' => 'base-tenant::metering.metrics.storage.bytes'],
                        'api.calls' => ['type' => 'counter', 'reset' => 'day'],
                    ],
                ]);

                app(MetricRegistry::class)->flush();

                Meter::for($cuenta)->set('storage.bytes', 1);
                Meter::for($cuenta)->increment('api.calls', 900);
            },
            'sembrar' => fn ($cuenta, Closure $ayudante): array => ['storage.bytes', 'api.calls'],
        ],

        LanguageManagerScreen::class => [
            // Pantalla de sistema: la gestiona quien administra la instalación,
            // no quien administra una cuenta.
            'rol' => 'administrator-tech',
            // `native_name` es real y no está declarada. Por posición sale
            // English antes que Español; por nombre nativo descendente sale al
            // revés, que es lo que distingue tener lista blanca de no tenerla.
            'columnaNoDeclarada' => 'native_name',
            'vacioInicial' => __('base-tenant::languages.empty'),
            'preparar' => function ($cuenta, $admin, Closure $ayudante): void {
                LanguageModel::query()->delete();

                foreach (config('base-tenant.languages.seed') as $idioma) {
                    LanguageModel::create($idioma);
                }

                LanguageFacade::flush();
            },
            'sembrar' => fn ($cuenta, Closure $ayudante): array => ['English', 'Español'],
        ],

        ConnectionManagerScreen::class => [
            'rol' => 'customer-admin',
            // `status` es real y no está declarada como ordenable. Por
            // proveedor ascendente sale «aaa» antes que «zzz»; por estado
            // descendente saldría al revés.
            'columnaNoDeclarada' => 'status',
            'vacioInicial' => __('base-tenant::connections.empty'),
            'sembrar' => function ($cuenta, Closure $ayudante): array {
                foreach ([['aaa-proveedor', 'unknown'], ['zzz-proveedor', 'healthy']] as [$proveedor, $estado]) {
                    Tenant::runFor($cuenta, fn () => AccountConnectionModel::create([
                        'provider' => $proveedor,
                        'label' => 'default',
                        'credentials' => ['token' => 'x'],
                        'status' => $estado,
                    ]));
                }

                return ['aaa-proveedor', 'zzz-proveedor'];
            },
        ],

        TransferManagerScreen::class => [
            'rol' => 'customer-admin',
            // `handler` es real y no está declarada como ordenable. Por nombre
            // ascendente sale «aaa» antes que «zzz»; por manejador descendente
            // saldría al revés.
            'columnaNoDeclarada' => 'handler',
            'vacioInicial' => __('base-tenant::transfer.empty'),
            'sembrar' => function ($cuenta, Closure $ayudante): array {
                foreach ([['aaa-primero.csv', 'zzz-manejador'], ['zzz-ultimo.csv', 'aaa-manejador']] as [$nombre, $manejador]) {
                    Tenant::runFor($cuenta, fn () => DataTransferModel::create([
                        'type' => DataTransferModel::IMPORT,
                        'handler' => $manejador,
                        'status' => DataTransferModel::COMPLETED,
                        'name' => $nombre,
                    ]));
                }

                return ['aaa-primero.csv', 'zzz-ultimo.csv'];
            },
        ],

        FileLibrary::class => [
            'rol' => 'customer-admin',
            // `mime_type` es real en cada fila y no está declarada como
            // ordenable. Por nombre ascendente sale «aaa» antes que «zzz»;
            // por tipo descendente saldría al revés.
            'columnaNoDeclarada' => 'mime_type',
            'vacioInicial' => __('base-tenant::files.empty'),
            'preparar' => function ($cuenta, $admin, Closure $ayudante): void {
                config(['base-tenant.files.driver' => 'local', 'base-tenant.files.disk' => 's3']);

                Storage::fake('s3');
            },
            'sembrar' => function ($cuenta, Closure $ayudante): array {
                foreach ([['aaa-primero.txt', 'text/plain'], ['zzz-ultimo.png', 'image/png']] as [$nombre, $tipo]) {
                    Tenant::runFor($cuenta, fn () => FileModel::create([
                        'collection' => 'library',
                        'disk' => 's3',
                        'path' => "accounts/{$cuenta->getKey()}/files/x/{$nombre}",
                        'name' => $nombre,
                        'mime_type' => $tipo,
                        'size' => 10,
                    ]));
                }

                return ['aaa-primero.txt', 'zzz-ultimo.png'];
            },
        ],

        FeatureManager::class => [
            'atributos' => ['is_admin' => true],
            // Por clave ascendente: api_access antes que max_users. Por valor
            // efectivo descendente sería al revés (3 antes que false).
            'columnaNoDeclarada' => 'effective',
            'vacioInicial' => __('base-tenant::features.empty'),
            'sembrar' => fn ($cuenta, Closure $ayudante): array => ['api_access', 'max_users'],
        ],
    ];
}

/**
 * Siembra los datos de una pantalla y autentica a quien la mira. Solo siembra:
 * montar el componente se hace aparte, porque cada test lo monta dos veces con
 * URLs distintas y sembrar dos veces rompía las claves únicas.
 *
 * @return array{0: array<int, string>, 1: array<string, mixed>}
 */
function prepararPantalla(object $caso, string $clase): array
{
    $fichas = fichasDePantalla();

    // Los ayudantes de TestCase son protegidos: desde una función suelta no se
    // alcanzan. Se atan al caso para llamarlos dentro del alcance de la clase.
    $ayudante = Closure::bind(
        fn (string $metodo, ...$argumentos) => $this->{$metodo}(...$argumentos),
        $caso,
        $caso::class
    );

    // `toHaveKey($clave, $valor)` compara el valor, no acepta un mensaje.
    expect(array_key_exists($clase, $fichas))->toBeTrue(
        "La pantalla {$clase} usa InteractsWithTable pero no tiene ficha en este test. Añádela: sin datos delante, las promesas del patrón no se comprueban."
    );

    $ficha = $fichas[$clase];

    $cuenta = $ayudante('createAccount', ['name' => 'Aaa Principal']);
    $admin = $ayudante('createUser', $cuenta, $ficha['rol'] ?? null, $ficha['atributos'] ?? []);

    if (isset($ficha['preparar'])) {
        ($ficha['preparar'])($cuenta, $admin, $ayudante);
    }

    $marcadores = ($ficha['sembrar'])($cuenta, $ayudante);

    $ayudante('actingAsTenant', $admin, $cuenta);

    return [$marcadores, $ficha];
}

test('toda pantalla tabular declara columnas ordenables', function (string $clase) {
    $reflexion = new ReflectionClass($clase);

    expect($reflexion->hasMethod('sortableColumns'))->toBeTrue();

    $metodo = $reflexion->getMethod('sortableColumns');
    $metodo->setAccessible(true);

    $columnas = $metodo->invoke($reflexion->newInstanceWithoutConstructor());

    expect($columnas)->toBeArray()->not->toBeEmpty()
        ->and($columnas)->each->toBeString();
})->with(pantallasTabulares());

/**
 * Sin `#[Url]` el estado no viaja en el enlace, que es la promesa que ninguna
 * de estas pantallas cumplía antes de este trabajo. Se comprueba por reflexión
 * y no por comportamiento porque el atributo es justo lo que se promete.
 */
test('el estado compartido de la tabla viaja por la URL', function (string $clase) {
    $reflexion = new ReflectionClass($clase);

    foreach (['search', 'sortBy', 'sortDirection', 'perPage'] as $propiedad) {
        expect($reflexion->hasProperty($propiedad))->toBeTrue("Falta \${$propiedad} en {$clase}");

        $atributos = $reflexion->getProperty($propiedad)->getAttributes(Url::class);

        expect($atributos)->not->toBeEmpty("\${$propiedad} de {$clase} no lleva #[Url]: su estado no se puede compartir por enlace");
    }
})->with(pantallasTabulares());

/**
 * `sortBy` llega de la URL sin pasar por `sort()`, así que la lista blanca hay
 * que comprobarla donde se construye el listado. Se compara el orden pintado
 * consigo mismo: no hace falta saber cuál es el correcto, solo que no cambia.
 */
test('una columna real no declarada no cambia el orden', function (string $clase) {
    [$marcadores, $ficha] = prepararPantalla($this, $clase);

    $ordenNormal = Livewire::test($clase)->html();

    $inyectado = Livewire::withQueryParams([
        'sortBy' => $ficha['columnaNoDeclarada'],
        'sortDirection' => 'desc',
    ])->test($clase);

    $inyectado->assertOk();

    // El orden de los marcadores, no su posición: dos renders distintos tienen
    // desplazamientos distintos aunque las filas salgan igual.
    $orden = function (string $html) use ($marcadores): array {
        $posiciones = [];

        foreach ($marcadores as $marcador) {
            $posicion = strpos($html, $marcador);

            expect($posicion)->not->toBeFalse("El marcador «{$marcador}» no aparece en la pantalla");

            $posiciones[$marcador] = $posicion;
        }

        asort($posiciones);

        return array_keys($posiciones);
    };

    expect($orden($inyectado->html()))->toBe($orden($ordenNormal));
})->with(pantallasTabulares());

/**
 * El fallo que apareció en la tabla de usuarios: `?page=99` deja la página
 * vacía sin que la tabla lo esté, y anunciar «aún no hay nada» encima de datos
 * que existen es mentir. Se llega por URL, que es lo que estas pantallas
 * comparten por enlace.
 */
test('una página fuera de rango no anuncia una tabla vacía', function (string $clase) {
    [$marcadores, $ficha] = prepararPantalla($this, $clase);

    // Primero, que de verdad haya datos que contradigan el vacío.
    Livewire::test($clase)->assertSee($marcadores[0]);

    Livewire::withQueryParams(['page' => 99])->test($clase)
        ->assertOk()
        ->assertDontSee($ficha['vacioInicial']);
})->with(pantallasTabulares());

/**
 * La promesa más fácil de romper en silencio: cada pantalla amplía
 * `resetFilters()` con sus propios filtros, y si alguna se olvida, la insignia
 * se queda puesta después de «limpiar» y ningún test se entera.
 *
 * Los filtros se descubren por reflexión, no se listan: una pantalla nueva, o
 * un filtro nuevo en una existente, entra solo.
 */
test('limpiar filtros deja cada filtro declarado en su valor inicial', function (string $clase) {
    prepararPantalla($this, $clase);

    $componente = Livewire::test($clase);

    $reflexion = new ReflectionClass($clase);
    $porDefecto = $reflexion->getDefaultProperties();

    $filtros = [];

    foreach ($reflexion->getProperties(ReflectionProperty::IS_PUBLIC) as $propiedad) {
        $nombre = $propiedad->getName();

        if ($nombre !== 'search' && ! str_starts_with($nombre, 'filter')) {
            continue;
        }

        if ((string) $propiedad->getType() !== 'string') {
            continue;
        }

        $filtros[] = $nombre;
    }

    // Que la pantalla tenga al menos búsqueda; si no, algo se ha descolgado.
    expect($filtros)->toContain('search');

    foreach ($filtros as $filtro) {
        $componente->set($filtro, 'zzz-valor-de-prueba');
    }

    $componente->call('resetFilters');

    // Con `expect` y no con `assertSet`: el tercer argumento de `assertSet` es
    // `$strict`, no un mensaje, y aquí el mensaje es lo que explica el fallo.
    foreach ($filtros as $filtro) {
        expect($componente->get($filtro))->toBe(
            $porDefecto[$filtro],
            "{$clase}::resetFilters() no limpia \${$filtro}: la insignia se queda puesta después de limpiar"
        );
    }

    $componente->assertViewHas('hasActiveFilters', false);
})->with(pantallasTabulares());

test('las diez pantallas tabulares están cubiertas', function () {
    // Si el descubrimiento deja de encontrarlas, los tests de arriba pasarían
    // sin comprobar nada, que es la peor forma de estar en verde.
    expect(pantallasTabulares())->toHaveCount(10)
        ->toContain(UserManager::class)
        ->toContain(AccountManager::class)
        ->toContain(InvitationManager::class)
        ->toContain(ActivityLog::class)
        ->toContain(FeatureManager::class)
        ->toContain(UsageManager::class)
        ->toContain(FileLibrary::class)
        ->toContain(LanguageManagerScreen::class)
        ->toContain(TransferManagerScreen::class)
        ->toContain(ConnectionManagerScreen::class);
});
