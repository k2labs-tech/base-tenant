<?php

declare(strict_types=1);

use Base\Tenant\Console\Support\ModuleField;
use Illuminate\Support\Facades\File;

/**
 * Todo lo que escribe el generador, para poder limpiarlo y para poder
 * comprobar que no se deja nada.
 *
 * @return list<string>
 */
function ficherosGenerados(): array
{
    return [
        app_path('Models/Booking.php'),
        app_path('Policies/BookingPolicy.php'),
        app_path('Livewire/Booking/Index.php'),
        app_path('Livewire/Booking/Form.php'),
        database_path('factories/BookingFactory.php'),
        resource_path('views/livewire/bookings/index.blade.php'),
        resource_path('views/livewire/bookings/form.blade.php'),
        lang_path('en/bookings.php'),
        lang_path('es/bookings.php'),
        base_path('tests/Feature/BookingTest.php'),
        base_path('docs/agents/app/bookings.md'),
    ];
}

function generarBooking(array $opciones = []): void
{
    test()->artisan('k2labs-base:make-module', [
        'name' => 'Booking',
        '--fields' => 'reference:string,guests:integer,starts_at:date,notes:text:nullable,confirmed:boolean',
        ...$opciones,
    ])->assertSuccessful();
}

afterEach(function () {
    foreach (ficherosGenerados() as $fichero) {
        File::delete($fichero);
    }

    File::deleteDirectory(app_path('Livewire/Booking'));
    File::deleteDirectory(resource_path('views/livewire/bookings'));
    File::deleteDirectory(base_path('docs/agents/app'));

    foreach (File::glob(database_path('migrations/*_create_bookings_table.php')) as $migracion) {
        File::delete($migracion);
    }
});

test('los campos se leen con su tipo y su nulabilidad', function () {
    $campos = ModuleField::parse('reference:string,notes:text:nullable,guests:integer');

    expect($campos)->toHaveCount(3)
        ->and($campos[0]->name)->toBe('reference')
        ->and($campos[1]->nullable)->toBeTrue()
        ->and($campos[2]->type)->toBe('integer');
});

test('un tipo inventado no se acepta en silencio', function () {
    expect(fn () => ModuleField::parse('x:pterodactilo'))
        ->toThrow(InvalidArgumentException::class, 'pterodactilo');
});

test('un nombre de columna que no lo es se rechaza', function () {
    expect(fn () => ModuleField::parse('Nombre Raro:string'))
        ->toThrow(InvalidArgumentException::class);
});

/**
 * Un booleano sin valor por defecto entra como null, y null no es ni verdadero
 * ni falso en ninguno de los sitios donde después se lee.
 */
test('un booleano no nulable sale con valor por defecto', function () {
    $campo = new ModuleField('confirmed', 'boolean');

    expect($campo->migrationLine())->toContain('->default(false)');
});

test('sin campos el generador se niega en vez de crear un módulo vacío', function () {
    $this->artisan('k2labs-base:make-module', ['name' => 'Booking'])->assertFailed();
});

test('el ensayo enseña lo que haría y no escribe nada', function () {
    generarBooking(['--pretend' => true]);

    foreach (ficherosGenerados() as $fichero) {
        expect(File::exists($fichero))->toBeFalse("El ensayo ha escrito {$fichero}.");
    }
});

test('el generador escribe el módulo entero', function () {
    generarBooking();

    foreach (ficherosGenerados() as $fichero) {
        expect(File::exists($fichero))->toBeTrue("Falta {$fichero}.");
    }

    expect(File::glob(database_path('migrations/*_create_bookings_table.php')))->toHaveCount(1);
});

/**
 * El criterio del spec es que el código generado llegue a producción sin
 * retocarlo. Lo mínimo para eso es que sea PHP válido: un stub con una llave
 * de más produce un módulo que ni siquiera carga, y el generador no lo notaría.
 */
test('todo el PHP generado es sintácticamente válido', function () {
    generarBooking();

    $php = array_filter(ficherosGenerados(), fn (string $f): bool => str_ends_with($f, '.php'));
    $php = [...$php, ...File::glob(database_path('migrations/*_create_bookings_table.php'))];

    foreach ($php as $fichero) {
        $salida = [];
        $codigo = 0;

        exec('php -l '.escapeshellarg($fichero).' 2>&1', $salida, $codigo);

        expect($codigo)->toBe(0, "PHP inválido en {$fichero}:\n".implode("\n", $salida));
    }
});

/**
 * Las vistas se compilan aparte: un Blade roto pasa `php -l` sin enterarse,
 * porque para PHP es texto.
 */
test('las vistas generadas compilan', function () {
    generarBooking();

    $compilador = app('blade.compiler');

    foreach ([resource_path('views/livewire/bookings/index.blade.php'), resource_path('views/livewire/bookings/form.blade.php')] as $vista) {
        $compilado = $compilador->compileString(File::get($vista));

        $salida = [];
        $codigo = 0;

        $temporal = tempnam(sys_get_temp_dir(), 'blade').'.php';
        File::put($temporal, $compilado);

        exec('php -l '.escapeshellarg($temporal).' 2>&1', $salida, $codigo);

        File::delete($temporal);

        expect($codigo)->toBe(0, "Blade inválido en {$vista}:\n".implode("\n", $salida));
    }
});

/**
 * El módulo generado tiene que traer puestas las garantías del paquete, no
 * quedarse en un CRUD que las pierde.
 */
test('el modelo generado trae el ámbito de cuenta y el registro de actividad', function () {
    generarBooking();

    $modelo = File::get(app_path('Models/Booking.php'));

    expect($modelo)->toContain('use BelongsToAccount;')
        ->and($modelo)->toContain('use LogsActivity;')
        ->and($modelo)->toContain("'guests' => 'integer',")
        ->and($modelo)->toContain("'confirmed' => 'boolean',");
});

test('el listado generado declara lista blanca de orden', function () {
    generarBooking();

    $indice = File::get(app_path('Livewire/Booking/Index.php'));

    expect($indice)->toContain('use InteractsWithTable;')
        ->and($indice)->toContain("return ['reference', 'guests', 'starts_at', 'notes', 'confirmed', 'created_at'];");
});

/**
 * La garantía que un módulo generado tiene más papeletas de perder y menos de
 * que alguien la eche en falta.
 */
test('el test generado comprueba el aislamiento entre cuentas', function () {
    generarBooking();

    expect(File::get(base_path('tests/Feature/BookingTest.php')))
        ->toContain('una cuenta no ve los registros de otra');
});

test('la colección de ficheros solo aparece si se pide', function () {
    generarBooking();

    expect(File::get(app_path('Models/Booking.php')))->not->toContain('HasFiles');

    File::delete(app_path('Models/Booking.php'));

    generarBooking(['--files' => 'documents']);

    $modelo = File::get(app_path('Models/Booking.php'));

    expect($modelo)->toContain('use HasFiles;')
        ->and($modelo)->toContain("'documents' => FileCollection::make('documents')");
});

test('un fichero que ya existe no se pisa sin pedirlo', function () {
    generarBooking();

    File::put(app_path('Models/Booking.php'), '<?php // mío');

    generarBooking();

    expect(File::get(app_path('Models/Booking.php')))->toBe('<?php // mío');

    generarBooking(['--force' => true]);

    expect(File::get(app_path('Models/Booking.php')))->toContain('class Booking');
});

/**
 * Se relanza al añadir un campo. Sin marcadores propios, cada pasada añadiría
 * una segunda copia de las rutas y de los permisos, y el fichero crecería una
 * ruta duplicada cada vez.
 */
test('relanzarlo no duplica lo que añade a las rutas ni a la configuración', function () {
    $rutas = base_path('routes/web.php');
    $config = config_path('base-tenant.php');

    File::ensureDirectoryExists(dirname($rutas));
    File::ensureDirectoryExists(dirname($config));

    File::put($rutas, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
    File::put($config, "<?php\n\nreturn [\n    'permissions' => [\n        // base-tenant:permissions\n    ],\n];\n");

    generarBooking();
    generarBooking(['--force' => true]);

    expect(substr_count(File::get($rutas), 'base-tenant:module:bookings:begin'))->toBe(1)
        ->and(substr_count(File::get($rutas), "name('bookings.index')"))->toBe(1)
        ->and(substr_count(File::get($config), 'bookings.view'))->toBe(1);

    // Y lo que había alrededor sigue estando.
    expect(File::get($rutas))->toContain('use Illuminate\\Support\\Facades\\Route;');

    File::delete($rutas);
    File::delete($config);
});

test('los permisos se colocan donde marca el marcador', function () {
    $config = config_path('base-tenant.php');

    File::ensureDirectoryExists(dirname($config));
    File::put($config, "<?php\n\nreturn [\n    'permissions' => [\n        'users' => ['users.view'],\n\n        // base-tenant:permissions\n    ],\n];\n");

    generarBooking();

    $contenido = File::get($config);

    expect($contenido)->toContain('bookings.view')
        // Dentro del array de permisos, no pegado al final del fichero.
        ->and(strpos($contenido, 'bookings.view'))->toBeLessThan(strpos($contenido, '];'));

    File::delete($config);
});
