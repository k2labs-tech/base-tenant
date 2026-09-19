<?php

declare(strict_types=1);

use Base\Tenant\BaseTenantServiceProvider;
use Base\Tenant\Console\Support\CodeTransformer;
use Base\Tenant\Console\Support\DependencyTransferManager;
use Base\Tenant\Console\Support\ScaffoldPlan;
use Base\Tenant\Console\Support\ServiceProviderGenerator;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->packagePath = dirname(__DIR__, 2);
    $this->workspace = sys_get_temp_dir().'/base-tenant-scaffold-'.bin2hex(random_bytes(6));

    File::ensureDirectoryExists($this->workspace);
});

afterEach(function () {
    File::deleteDirectory($this->workspace);
});

/**
 * The regression that matters: the plan is a deny-list, so a subsystem added
 * to src/ is scaffolded without anyone editing the command.
 */
test('every directory under src is scaffolded unless explicitly excluded', function () {
    $plan = new ScaffoldPlan($this->packagePath, $this->workspace);

    $planned = collect($plan->files())
        ->filter(fn (array $file): bool => str_starts_with($file['relative'], 'app/'))
        ->map(fn (array $file): string => explode('/', substr($file['relative'], 4))[0])
        ->unique();

    $onDisk = collect(File::directories("{$this->packagePath}/src"))
        ->map(fn (string $path): string => basename($path))
        ->reject(fn (string $directory): bool => $directory === 'Console');

    expect($planned)->toContain(...$onDisk->all());
});

test('the subsystems added most recently are in the plan', function () {
    $plan = new ScaffoldPlan($this->packagePath, $this->workspace);

    $relatives = collect($plan->files())->pluck('relative');

    expect($relatives)->toContain(
        'app/Tenancy/TenantManager.php',
        'app/Tenancy/AccountScope.php',
        'app/Tenancy/TenantTeamResolver.php',
        'app/Menu/MenuManager.php',
        'app/Features/FeatureManager.php',
        'app/Settings/SettingsSchema.php',
        'app/Policies/UserPolicy.php',
        'app/Facades/Tenant.php',
        'app/Traits/BelongsToAccount.php',
    );
});

test('the package service provider and installer internals stay behind', function () {
    $plan = new ScaffoldPlan($this->packagePath, $this->workspace);

    $relatives = collect($plan->files())->pluck('relative');

    expect($relatives)->not->toContain(
        'app/BaseTenantServiceProvider.php',
        'app/Console/Commands/InstallCommand.php',
        'app/Console/Commands/ScaffoldCommand.php',
        'app/Console/Commands/EjectCommand.php',
        'app/Console/Support/ScaffoldPlan.php',
    );
});

test('the commands the application keeps are scaffolded', function () {
    $plan = new ScaffoldPlan($this->packagePath, $this->workspace);

    expect(collect($plan->files())->pluck('relative'))->toContain(
        'app/Console/Commands/SyncRolesCommand.php',
        'app/Console/Commands/SyncMenusCommand.php',
        'app/Console/Commands/PruneActivityLogCommand.php',
    );
});

test('every migration is copied, none is consolidated away', function () {
    $plan = new ScaffoldPlan($this->packagePath, $this->workspace);

    $planned = collect($plan->files())
        ->filter(fn (array $file): bool => str_starts_with($file['relative'], 'database/migrations/'))
        ->count();

    $onDisk = count(glob("{$this->packagePath}/database/migrations/*.php"));

    expect($planned)->toBe($onDisk)->and($planned)->toBeGreaterThan(30);
});

test('the wireui vendor translations are left out', function () {
    $plan = new ScaffoldPlan($this->packagePath, $this->workspace);

    expect(collect($plan->files())->pluck('relative')->filter(
        fn (string $relative): bool => str_contains($relative, 'lang/tenant/vendor')
    ))->toBeEmpty();
});

test('scaffolded code produces valid PHP with no package references left', function () {
    $plan = new ScaffoldPlan($this->packagePath, $this->workspace);
    $transformer = new CodeTransformer;

    $checked = 0;

    foreach ($plan->files() as $file) {
        $contents = $transformer->transform(File::get($file['source']));

        File::ensureDirectoryExists(dirname($file['destination']));
        File::put($file['destination'], $contents);

        if (! str_ends_with($file['destination'], '.php')) {
            continue;
        }

        $lint = shell_exec('php -l '.escapeshellarg($file['destination']).' 2>&1');

        $this->assertStringContainsString(
            'No syntax errors',
            (string) $lint,
            "Syntax error in {$file['relative']}"
        );

        // Blade files legitimately keep their own syntax; PHP sources must
        // carry no reference to the package namespace.
        if (! str_ends_with($file['relative'], '.blade.php')) {
            $this->assertStringNotContainsString(
                'Base\\Tenant',
                $contents,
                "Leftover package namespace in {$file['relative']}"
            );
        }

        $checked++;
    }

    expect($checked)->toBeGreaterThan(150);
});

test('blade views lose every package reference', function () {
    $plan = new ScaffoldPlan($this->packagePath, $this->workspace);
    $transformer = new CodeTransformer;

    foreach ($plan->files() as $file) {
        if (! str_ends_with($file['relative'], '.blade.php')) {
            continue;
        }

        $contents = $transformer->transform(File::get($file['source']));

        $this->assertStringNotContainsString('x-base-tenant::', $contents, "Component tag left in {$file['relative']}");
        $this->assertStringNotContainsString("'base-tenant::", $contents, "View or translation key left in {$file['relative']}");
    }
});

/**
 * A bare `<x-input-label>` resolves inside the package and nowhere else: the
 * transformer only rewrites namespaced tags, and the copied components live
 * under `views/components/tenant`. So every tag has to carry the prefix.
 */
test('package views reference the shipped components through the package prefix', function () {
    $components = collect(File::files("{$this->packagePath}/resources/views/components"))
        ->map(fn ($file): string => str_replace('.blade.php', '', $file->getFilename()))
        ->all();

    $pattern = '/<\/?x-('.implode('|', array_map('preg_quote', $components)).')(?=[\s>\/])/';

    $offenders = [];

    foreach (File::allFiles("{$this->packagePath}/resources/views") as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        if (preg_match_all($pattern, File::get($file->getPathname()), $matches)) {
            $offenders[] = $file->getRelativePathname().': '.implode(', ', array_unique($matches[0]));
        }
    }

    expect($offenders)->toBe([]);
});

test('the scaffolded config points at the application, not at the package', function () {
    $transformed = (new CodeTransformer)->transform(
        File::get("{$this->packagePath}/config/base-tenant.php")
    );

    expect($transformed)
        ->not->toContain('base-tenant::')
        ->not->toContain('Base\\Tenant')
        ->toContain("'tenant::layouts.app'")
        ->toContain('BASE_TENANT_LAYOUT_APP');
});

test('the generated service provider is valid and registers everything', function () {
    $transformer = new CodeTransformer;

    $generator = new ServiceProviderGenerator(
        $transformer,
        "{$this->packagePath}/stubs/TenancyServiceProvider.php.stub"
    );

    $rendered = $generator->render();
    $path = "{$this->workspace}/TenancyServiceProvider.php";

    File::put($path, $rendered);

    expect(shell_exec('php -l '.escapeshellarg($path).' 2>&1'))->toContain('No syntax errors');

    expect($rendered)
        ->toContain('namespace App\Providers;')
        ->toContain('TenantTeamResolver::class')
        ->toContain('QueueTenancy::register')
        ->toContain('Livewire::addPersistentMiddleware')
        ->toContain('Http\\Middleware\\TrackUserSession::class')
        ->toContain('Gate::before')
        ->toContain("loadRoutesFrom(base_path('routes/tenant/web.php'))")
        ->toContain("loadViewsFrom(resource_path('views/tenant'), 'tenant')")
        ->toContain("loadTranslationsFrom(lang_path('tenant'), 'tenant')")
        ->not->toContain('Base\Tenant')
        ->not->toContain('{{');
});

test('the generated provider registers every Livewire component the package does', function () {
    $rendered = (new ServiceProviderGenerator(
        new CodeTransformer,
        "{$this->packagePath}/stubs/TenancyServiceProvider.php.stub"
    ))->render();

    foreach (BaseTenantServiceProvider::livewireComponents() as $name => $class) {
        $this->assertStringContainsString("'{$name}' =>", $rendered, "Missing Livewire component {$name}");
    }

    expect($rendered)
        ->toContain('\App\Livewire\RoleManager::class')
        ->toContain('\App\Livewire\NavigationManager::class');
});

test('the generated provider maps blade layout aliases to the tenant prefix', function () {
    $rendered = (new ServiceProviderGenerator(
        new CodeTransformer,
        "{$this->packagePath}/stubs/TenancyServiceProvider.php.stub"
    ))->render();

    expect($rendered)
        ->toContain("'tenant.app-layout' => \App\View\Components\AppLayout::class")
        ->toContain("'tenant.guest-layout' => \App\View\Components\GuestLayout::class");
});

test('dependencies move to the application without duplicating what it has', function () {
    File::put("{$this->workspace}/composer.json", json_encode([
        'name' => 'acme/app',
        'require' => ['php' => '^8.4', 'laravel/framework' => '^12.0', 'laravel/sanctum' => '^4.0'],
    ]));

    $manager = new DependencyTransferManager($this->packagePath, $this->workspace);

    $pending = $manager->pendingDependencies();

    expect($pending)->toHaveKey('spatie/laravel-permission')
        ->and($pending)->toHaveKey('laravel/cashier')
        ->and($pending)->not->toHaveKey('laravel/sanctum')
        ->and($pending)->not->toHaveKey('php')
        ->and($pending)->not->toHaveKey('illuminate/support');

    $manager->transfer();

    $manifest = json_decode(File::get("{$this->workspace}/composer.json"), true);

    expect($manifest['require'])->toHaveKey('spatie/laravel-permission')
        ->and($manifest['require']['laravel/sanctum'])->toBe('^4.0')
        ->and($manifest['repositories'])->toHaveKey('livewire/flux-pro');
});

test('the helpers file is added to the application autoload when it was scaffolded', function () {
    File::put("{$this->workspace}/composer.json", json_encode(['name' => 'acme/app', 'require' => []]));

    $manager = new DependencyTransferManager($this->packagePath, $this->workspace);

    expect($manager->pendingAutoloadFiles())->toBe([]);

    File::ensureDirectoryExists("{$this->workspace}/app");
    File::put("{$this->workspace}/app/helpers.php", '<?php');

    expect($manager->pendingAutoloadFiles())->toBe(['app/helpers.php']);

    $manager->transfer();

    $manifest = json_decode(File::get("{$this->workspace}/composer.json"), true);

    expect($manifest['autoload']['files'])->toBe(['app/helpers.php']);
});

test('a failed transfer can be rolled back', function () {
    $original = json_encode(['name' => 'acme/app', 'require' => []]);

    File::put("{$this->workspace}/composer.json", $original);

    $manager = new DependencyTransferManager($this->packagePath, $this->workspace);

    $backup = $manager->backup();
    $manager->transfer();

    expect(File::get("{$this->workspace}/composer.json"))->not->toBe($original);

    $manager->restore($backup);

    expect(File::get("{$this->workspace}/composer.json"))->toBe($original);
});

test('factories, seeders and the test kit land on the namespaces a Laravel app autoloads', function () {
    $plan = new ScaffoldPlan($this->packagePath, $this->workspace);
    $transformer = new CodeTransformer;

    $expected = [
        'database/factories/' => 'Database\\Factories',
        'database/seeders/' => 'Database\\Seeders',
        'tests/' => 'Tests',
    ];

    $checked = 0;

    foreach ($plan->files() as $file) {
        foreach ($expected as $prefix => $namespace) {
            if (! str_starts_with($file['relative'], $prefix)) {
                continue;
            }

            $contents = $transformer->transform(File::get($file['source']));

            preg_match('/^namespace ([^;]+);/m', $contents, $matches);

            expect($matches[1] ?? null)->toBe($namespace, $file['relative']);

            $checked++;
        }
    }

    // Counted from disk rather than hardcoded, so adding a seeder does not
    // fail this test for the wrong reason.
    $expected = count(glob("{$this->packagePath}/database/factories/*.php"))
        + count(glob("{$this->packagePath}/database/seeders/*.php"))
        + 1; // tests/TenancyAssertions.php

    expect($checked)->toBe($expected);
});

test('menu labels and every other translation key lose the package prefix', function () {
    $plan = new ScaffoldPlan($this->packagePath, $this->workspace);
    $transformer = new CodeTransformer;

    foreach ($plan->files() as $file) {
        $contents = $transformer->transform(File::get($file['source']));

        $this->assertStringNotContainsString(
            'base-tenant::',
            $contents,
            "Untransformed view or translation namespace in {$file['relative']}"
        );
    }

    $menus = $transformer->transform(
        File::get("{$this->packagePath}/src/Menu/DefaultMenus.php")
    );

    expect($menus)->toContain("->label('tenant::app.navigation.dashboard')");
});

test('a fully qualified reference keeps its root separator', function () {
    $transformer = new CodeTransformer;

    expect($transformer->transformNamespaces('$x = \\Base\\Tenant\\Models\\User::class;'))
        ->toBe('$x = \\App\\Models\\User::class;');

    expect($transformer->transformNamespaces('use Base\\Tenant\\Models\\User;'))
        ->toBe('use App\\Models\\User;');
});

test('a class name that merely starts with the package namespace is left alone', function () {
    $transformer = new CodeTransformer;

    expect($transformer->transformNamespaces('Base\\TenantOther\\Thing'))
        ->toBe('Base\\TenantOther\\Thing');
});

test('double-escaped namespaces in configuration are rewritten', function () {
    $transformer = new CodeTransformer;

    expect($transformer->transformNamespaces("'model' => 'Base\\\\Tenant\\\\Models\\\\User',"))
        ->toBe("'model' => 'App\\\\Models\\\\User',");
});

test('JSON translations go to the lang root, where Laravel can actually load them', function () {
    $plan = new ScaffoldPlan($this->packagePath, $this->workspace);

    $json = collect($plan->files())
        ->filter(fn (array $file): bool => str_ends_with($file['relative'], '.json'))
        ->pluck('relative');

    expect($json)->toContain('lang/en.json', 'lang/es.json')
        ->and($json)->not->toContain('lang/tenant/en.json');
});

test('PHP translations keep the tenant namespace', function () {
    $plan = new ScaffoldPlan($this->packagePath, $this->workspace);

    expect(collect($plan->files())->pluck('relative'))
        ->toContain('lang/tenant/en/users.php', 'lang/tenant/es/users.php');
});
