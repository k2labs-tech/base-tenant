<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * The command is exercised against a throwaway base path, never the real
 * project: `setBasePath()` moves `base_path()`, `config_path()` and
 * `resource_path()` into a temporary directory for the duration of the test.
 *
 * `--keep-package` is always passed so `composer remove` never runs.
 */
beforeEach(function () {
    $this->packagePath = dirname(__DIR__, 2);
    $this->workspace = sys_get_temp_dir().'/base-tenant-eject-'.bin2hex(random_bytes(6));

    File::ensureDirectoryExists($this->workspace.'/config');
    File::ensureDirectoryExists($this->workspace.'/resources/css');

    File::put($this->workspace.'/composer.json', json_encode([
        'name' => 'acme/app',
        'require' => ['php' => '^8.4'],
    ]));

    File::put(
        $this->workspace.'/config/base-tenant.php',
        "<?php\n\nreturn [\n    'installation_state' => 'scaffolded',\n];\n"
    );

    File::put($this->workspace.'/resources/css/app.css', <<<'CSS'
        @import 'tailwindcss';
        @import '../../vendor/k2labs/base-tenant/resources/css/base-tenant.css';

        @source '../**/*.blade.php';
        @source '../../base-tenant/resources/views/**/*.blade.php';
        @source '../../vendor/k2labs/base-tenant/resources/views/**/*.blade.php';
        CSS);

    config()->set('base-tenant.installation_state', 'scaffolded');

    $this->originalBasePath = $this->app->basePath();
    $this->app->setBasePath($this->workspace);
});

afterEach(function () {
    $this->app->setBasePath($this->originalBasePath);

    File::deleteDirectory($this->workspace);
});

test('eject copies the theme and repoints the stylesheet at it', function () {
    $this->artisan('k2labs-base:eject', ['--keep-package' => true, '--force' => true])
        ->assertSuccessful();

    expect(File::exists($this->workspace.'/resources/css/base-tenant.css'))->toBeTrue()
        ->and(File::get($this->workspace.'/resources/css/base-tenant.css'))
        ->toBe(File::get($this->packagePath.'/resources/css/base-tenant.css'));

    $css = File::get($this->workspace.'/resources/css/app.css');

    expect($css)
        ->toContain("@import './base-tenant.css';")
        ->not->toContain('vendor/k2labs/base-tenant/resources/css')
        // Both spellings of the package view paths: `base-tenant/` when linked,
        // `base/tenant/` under vendor.
        ->not->toContain('base-tenant/resources/views')
        ->not->toContain('base/tenant/resources/views')
        // The application's own scanning path is untouched.
        ->toContain("@source '../**/*.blade.php';");
});

/**
 * The ordering regression: `installation_state` used to be written before the
 * stylesheet work. A failure there left the config saying `ejected` with the
 * package still installed, and `verifyState()` refused to run again -- the
 * project could only be recovered by editing config by hand.
 */
test('a failure taking the stylesheet leaves the project able to eject again', function () {
    // A directory where the copied theme should go: `File::copy()` cannot
    // write over it, so `takeStylesheet()` throws.
    File::ensureDirectoryExists($this->workspace.'/resources/css/base-tenant.css');

    $this->artisan('k2labs-base:eject', ['--keep-package' => true, '--force' => true])
        ->assertFailed();

    expect(File::get($this->workspace.'/config/base-tenant.php'))
        ->toContain("'installation_state' => 'scaffolded'")
        ->not->toContain('ejected');

    // composer.json is rolled back too, so a re-run starts from where it began.
    $manifest = json_decode(File::get($this->workspace.'/composer.json'), true);

    expect($manifest['require'])->toBe(['php' => '^8.4']);
});

/**
 * The docs invite the application to edit the copied theme, so a second eject
 * -- or an application that already vendored its own copy -- must not lose
 * those edits without saying so.
 */
test('eject keeps an existing theme that was edited', function () {
    File::put($this->workspace.'/resources/css/base-tenant.css', "@theme {\n  --color-accent-500: red;\n}\n");

    // Without `--force` the command asks before it does anything; the point of
    // this test is the theme, so both questions get a yes.
    $this->artisan('k2labs-base:eject', ['--keep-package' => true])
        ->expectsConfirmation('Continue anyway?', 'yes')
        ->expectsConfirmation('Remove the package and transfer its dependencies?', 'yes')
        ->expectsOutputToContain('already exists and differs')
        ->assertSuccessful();

    expect(File::get($this->workspace.'/resources/css/base-tenant.css'))
        ->toContain('--color-accent-500: red;');

    // The stylesheet is still repointed: the file it names is there, it is just
    // the application's version.
    expect(File::get($this->workspace.'/resources/css/app.css'))
        ->toContain("@import './base-tenant.css';");
});

test('force overwrites an existing theme', function () {
    File::put($this->workspace.'/resources/css/base-tenant.css', "@theme {\n  --color-accent-500: red;\n}\n");

    $this->artisan('k2labs-base:eject', ['--keep-package' => true, '--force' => true])
        ->assertSuccessful();

    expect(File::get($this->workspace.'/resources/css/base-tenant.css'))
        ->toBe(File::get($this->packagePath.'/resources/css/base-tenant.css'));
});

test('an app.css the installer never wired is left alone', function () {
    $untouched = "@import 'tailwindcss';\n\n@source '../**/*.blade.php';\n";

    File::put($this->workspace.'/resources/css/app.css', $untouched);

    $this->artisan('k2labs-base:eject', ['--keep-package' => true, '--force' => true])
        ->assertSuccessful();

    expect(File::get($this->workspace.'/resources/css/app.css'))->toBe($untouched)
        ->and(File::exists($this->workspace.'/resources/css/base-tenant.css'))->toBeFalse();
});

test('eject refuses to run twice', function () {
    config()->set('base-tenant.installation_state', 'ejected');

    $this->artisan('k2labs-base:eject', ['--keep-package' => true, '--force' => true])
        ->assertFailed();
});

/**
 * El instalador del kit encadena `scaffold` y `eject` con `$this->call()`, es
 * decir en el mismo proceso. Escribir el estado solo en el fichero deja la
 * configuración en memoria con el valor de arranque, así que el eject cree que
 * no hay código copiado y se niega justo después de que el scaffold lo copiara.
 */
test('el estado que escribe el scaffold vale en el mismo proceso', function () {
    config()->set('base-tenant.installation_state', 'installed');

    File::put(
        $this->workspace.'/config/base-tenant.php',
        "<?php\n\nreturn [\n    'installation_state' => 'installed',\n];\n"
    );

    $this->artisan('k2labs-base:scaffold', ['--overwrite' => true])->assertSuccessful();

    expect(config('base-tenant.installation_state'))->toBe('scaffolded');
});
