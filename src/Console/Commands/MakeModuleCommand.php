<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Commands;

use Base\Tenant\Console\Concerns\HasDeprecatedAlias;
use Base\Tenant\Console\Support\ModuleField;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Scaffold a whole vertical module into the host application.
 *
 *     php artisan k2labs-base:make-module Booking \
 *         --fields="reference:string,guests:integer,starts_at:date,notes:text:nullable" \
 *         --files=documents \
 *         --pretend
 *
 * What comes out is meant to reach production without being rewritten: the
 * model is tenant-scoped and logged, the screens follow the package's table
 * pattern, the policy checks permissions rather than ownership, and the test
 * that ships with it is the tenant isolation one -- the guarantee a generated
 * module is most likely to lose and least likely to be missed.
 *
 * Everything it appends to an existing file is wrapped in markers, so running
 * it twice replaces its own block and never touches anything around it.
 */
class MakeModuleCommand extends Command
{
    use HasDeprecatedAlias;

    protected $signature = 'k2labs-base:make-module
                            {name : Singular class name, e.g. Booking}
                            {--fields= : name:type[:nullable], comma separated}
                            {--files= : Add a file collection with this name}
                            {--pretend : List what would be written, and write nothing}
                            {--force : Overwrite files that already exist}';

    protected $description = 'Generate a tenant-scoped CRUD module in the application';

    /** @var list<array{path: string, contents: string}> */
    protected array $planned = [];

    /** @var list<string> */
    protected array $skipped = [];

    public function handle(): int
    {
        // Artisan resuelve el comando una vez y lo reutiliza, así que el plan
        // de una ejecución sobrevive a la siguiente: sin este reinicio, una
        // segunda pasada vuelve a escribir los ficheros que la primera había
        // decidido no tocar.
        $this->planned = [];
        $this->skipped = [];

        try {
            $names = $this->names($this->argument('name'));
            $fields = ModuleField::parse((string) $this->option('fields'));
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($fields === []) {
            $this->components->error('A module needs at least one field. Pass --fields="name:string".');

            return self::FAILURE;
        }

        $this->planFiles($names, $fields);

        try {
            $edits = $this->planEdits($names, $fields);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());
            $this->line('  <fg=gray>Nothing was written.</>');

            return self::FAILURE;
        }

        if ($this->option('pretend')) {
            $this->report($edits);

            return self::SUCCESS;
        }

        foreach ($this->planned as $file) {
            File::ensureDirectoryExists(dirname($file['path']));
            File::put($file['path'], $file['contents']);
        }

        foreach ($edits as $edit) {
            File::put($edit['path'], $edit['contents']);
        }

        $this->report($edits);

        $this->newLine();
        $this->components->info('Next: run the migration, then `php artisan k2labs-base:sync-roles && php artisan k2labs-base:sync-menus`.');

        return self::SUCCESS;
    }

    /**
     * Every name the templates need, derived once so they cannot disagree.
     *
     * @return array<string, string>
     */
    protected function names(string $name): array
    {
        $class = Str::studly(Str::singular($name));

        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $class)) {
            throw new InvalidArgumentException("`{$name}` is not a usable class name.");
        }

        $kebab = Str::kebab(Str::pluralStudly($class));

        return [
            'class' => $class,
            'variable' => Str::camel($class),
            'plural' => Str::camel(Str::pluralStudly($class)),
            'kebab' => $kebab,
            'table' => Str::snake(Str::pluralStudly($class)),
            'permission' => str_replace('-', '_', $kebab),
            'title' => Str::headline(Str::pluralStudly($class)),
            'singular' => Str::headline($class),
            'namespace' => 'App\\Models',
        ];
    }

    /**
     * @param  array<string, string>  $names
     * @param  list<ModuleField>  $fields
     */
    protected function planFiles(array $names, array $fields): void
    {
        $collection = (string) $this->option('files');

        $casts = array_filter(array_map(fn (ModuleField $f): ?string => $f->cast(), $fields));

        $this->plan(app_path("Models/{$names['class']}.php"), $this->render('model', $names, [
            'imports' => $collection !== '' ? "use Base\\Tenant\\Files\\FileCollection;\nuse Base\\Tenant\\Traits\\HasFiles;\n" : '',
            'traits' => $collection !== '' ? "    use HasFiles;\n\n" : '',
            'casts' => $casts === [] ? '' : implode("\n", $casts)."\n",
            'collections' => $collection === '' ? '' : <<<PHP

    /**
     * @return array<string, FileCollection>
     */
    public function fileCollections(): array
    {
        return [
            '{$collection}' => FileCollection::make('{$collection}'),
        ];
    }

PHP,
        ]));

        $this->plan(
            database_path('migrations/'.date('Y_m_d_His')."_create_{$names['table']}_table.php"),
            $this->render('migration', $names, [
                'columns' => implode("\n", array_map(fn (ModuleField $f): string => $f->migrationLine(), $fields)),
            ]),
        );

        $this->plan(database_path("factories/{$names['class']}Factory.php"), $this->render('factory', $names, [
            'definition' => implode("\n", array_map(fn (ModuleField $f): string => $f->factoryLine(), $fields))."\n",
        ]));

        $this->plan(app_path("Policies/{$names['class']}Policy.php"), $this->render('policy', $names));

        $this->plan(app_path("Livewire/{$names['class']}/Index.php"), $this->render('livewire-index', $names, [
            'sortable' => "'".implode("', '", [...array_map(fn (ModuleField $f): string => $f->name, $fields), 'created_at'])."'",
            'searchColumn' => $fields[0]->name,
        ]));

        $this->plan(app_path("Livewire/{$names['class']}/Form.php"), $this->render('livewire-form', $names, [
            // Written as a token so the stub does not carry a bare `$` that a
            // later replacement could walk into.
            'dollarRecordId' => '$recordId',
            'properties' => implode("\n", array_map(fn (ModuleField $f): string => $f->propertyLine(), $fields))."\n",
            'fill' => implode("\n", array_map(
                fn (ModuleField $f): string => "            \$this->{$f->name} = \${$names['variable']}->{$f->name};",
                $fields,
            )),
            'rules' => implode("\n", array_map(fn (ModuleField $f): string => $f->rulesLine(), $fields))."\n",
        ]));

        $this->plan(resource_path("views/livewire/{$names['kebab']}/index.blade.php"), $this->renderView('view-index', $names, $fields));
        $this->plan(resource_path("views/livewire/{$names['kebab']}/form.blade.php"), $this->renderView('view-form', $names, $fields));

        foreach (['en', 'es'] as $locale) {
            $this->plan(lang_path("{$locale}/{$names['kebab']}.php"), $this->renderLang($names, $fields, $locale));
        }

        $this->plan(base_path("tests/Feature/{$names['class']}Test.php"), $this->render('test', $names, [
            'formSets' => implode("\n", array_map(
                fn (ModuleField $f): string => "        ->set('{$f->name}', {$f->testValue()})",
                $fields,
            ))."\n",
        ]));

        $this->plan(base_path("docs/agents/app/{$names['kebab']}.md"), $this->render('agent-doc', $names, [
            'date' => now()->toDateString(),
            'lowerTitle' => Str::lower($names['title']),
            'fieldTable' => implode("\n", array_map(
                fn (ModuleField $f): string => "| `{$f->name}` | {$f->type}".($f->nullable ? ', nullable' : '').' |',
                $fields,
            )),
        ]));
    }

    /**
     * The three files that are appended to rather than created.
     *
     * @param  array<string, string>  $names
     * @param  list<ModuleField>  $fields
     * @return list<array{path: string, contents: string, label: string}>
     */
    protected function planEdits(array $names, array $fields): array
    {
        $edits = [];

        $routes = base_path('routes/web.php');

        if (File::exists($routes)) {
            $edits[] = [
                'path' => $routes,
                'label' => 'routes/web.php',
                'contents' => $this->withBlock(File::get($routes), $names['kebab'], <<<PHP
Route::middleware(['auth', 'base-tenant.account-context'])->group(function () {
    Route::get('/{$names['kebab']}', \\App\\Livewire\\{$names['class']}\\Index::class)->name('{$names['kebab']}.index');
    Route::get('/{$names['kebab']}/create', \\App\\Livewire\\{$names['class']}\\Form::class)->name('{$names['kebab']}.create');
    Route::get('/{$names['kebab']}/{{$names['variable']}}/edit', \\App\\Livewire\\{$names['class']}\\Form::class)->name('{$names['kebab']}.edit');
});
PHP),
            ];
        }

        $config = config_path('base-tenant.php');

        if (File::exists($config)) {
            $permissions = <<<PHP
        '{$names['permission']}' => [
            '{$names['permission']}.view',
            '{$names['permission']}.create',
            '{$names['permission']}.update',
            '{$names['permission']}.delete',
        ],
PHP;

            $edits[] = [
                'path' => $config,
                'label' => 'config/base-tenant.php (permissions)',
                'contents' => $this->withBlock(
                    File::get($config),
                    $names['kebab'],
                    $permissions,
                    marker: '// base-tenant:permissions',
                    indent: '        ',
                ),
            ];
        }

        return $edits;
    }

    /**
     * Put a block into a file, replacing the one that is already there.
     *
     * The block owns its own markers, so a second run rewrites exactly what
     * the first one wrote and nothing else. Without that, re-running after
     * adding a field appends a second copy and the file quietly grows a
     * duplicate route every time.
     */
    protected function withBlock(
        string $contents,
        string $key,
        string $block,
        ?string $marker = null,
        string $indent = '',
    ): string {
        $begin = "{$indent}// base-tenant:module:{$key}:begin";
        $end = "{$indent}// base-tenant:module:{$key}:end";

        $section = $begin."\n".$block."\n".$end;

        if (str_contains($contents, $begin) && str_contains($contents, $end)) {
            return (string) preg_replace(
                '/'.preg_quote($begin, '/').'.*?'.preg_quote($end, '/').'/s',
                str_replace('\\', '\\\\', $section),
                $contents,
            );
        }

        // An anchor says where the block belongs inside the file. A file that
        // asks for one and no longer has it cannot be appended to: the end of
        // `config/base-tenant.php` is after the `];` that closes the array, and
        // a block written there is a parse error that stops the application
        // from booting — reported, until this guard, as a successful edit.
        if ($marker !== null) {
            if (! str_contains($contents, $marker)) {
                throw new RuntimeException(
                    "Anchor [{$marker}] not found. Put it back where the generated block belongs and run this again; "
                    .'writing the block anywhere else would break the file.'
                );
            }

            return str_replace($marker, $section."\n\n".$indent.ltrim($marker), $contents);
        }

        return rtrim($contents)."\n\n".$section."\n";
    }

    /**
     * @param  array<string, string>  $names
     * @param  array<string, string>  $extra
     */
    protected function render(string $stub, array $names, array $extra = []): string
    {
        $contents = File::get($this->stubPath($stub));

        foreach ([...$names, ...$extra] as $token => $value) {
            $contents = str_replace('{{ '.$token.' }}', $value, $contents);
        }

        return $contents;
    }

    /**
     * Where a template comes from.
     *
     * A project that has published the stubs owns them: the generator reads
     * its copy so the house style survives a package update. The fallback is
     * the package's own copy, which only exists while the package is a
     * dependency — `dirname(__DIR__, 3)` from `app/Console/Commands/` is the
     * project root, not a package. An application that has taken ownership of
     * the code gets the stubs copied into `stubs/base-tenant/module/`; if they
     * are not there, say so instead of failing on a path nobody can read.
     */
    protected function stubPath(string $stub): string
    {
        $published = base_path("stubs/base-tenant/module/{$stub}.stub");

        if (File::exists($published)) {
            return $published;
        }

        $packaged = dirname(__DIR__, 3)."/stubs/module/{$stub}.stub";

        if (File::exists($packaged)) {
            return $packaged;
        }

        throw new RuntimeException(
            "Module template [{$stub}.stub] not found. Copy the package's stubs/module directory to "
            .base_path('stubs/base-tenant/module').' and run this again.'
        );
    }

    /**
     * @param  array<string, string>  $names
     * @param  list<ModuleField>  $fields
     */
    protected function renderView(string $stub, array $names, array $fields): string
    {
        $headings = [];
        $cells = [];
        $skeleton = [];
        $inputs = [];

        foreach ($fields as $index => $field) {
            $headings[] = <<<BLADE
                            <th scope="col" class="px-4 py-2.5">
                                <button type="button" wire:click="sort('{$field->name}')" class="group inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-zinc-500 transition-colors hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                                    {{ __('{$names['kebab']}.fields.{$field->name}') }}
                                    <flux:icon :icon="\$sortDirection === 'desc' ? 'chevron-down' : 'chevron-up'" variant="micro" />
                                </button>
                            </th>
BLADE;

            $value = $field->type === 'boolean'
                ? "{{ \${$names['variable']}->{$field->name} ? __('base-tenant::common.yes') : __('base-tenant::common.no') }}"
                : "{{ \${$names['variable']}->{$field->name} }}";

            $numeric = in_array($field->type, ['integer', 'decimal'], true) ? ' tabular-nums' : '';

            $cells[] = <<<BLADE
                                <td class="px-4 {{ \$rowPadding }}">
                                    <span class="text-sm{$numeric} text-zinc-900 dark:text-white">{$value}</span>
                                </td>
BLADE;

            $skeleton[] = '                                <td class="px-4 {{ $rowPadding }}"><flux:skeleton class="h-3 w-24" /></td>';

            $inputs[] = $field->type === 'boolean'
                ? "            <flux:checkbox wire:model=\"{$field->name}\" :label=\"__('{$names['kebab']}.fields.{$field->name}')\" />"
                : ($field->type === 'text'
                    ? "            <flux:textarea wire:model=\"{$field->name}\" :label=\"__('{$names['kebab']}.fields.{$field->name}')\" class=\"sm:col-span-2\" />"
                    : "            <flux:input wire:model=\"{$field->name}\" type=\"{$field->inputType()}\" :label=\"__('{$names['kebab']}.fields.{$field->name}')\" />");
        }

        return $this->render($stub, $names, [
            'headings' => implode("\n", $headings),
            'cells' => implode("\n", $cells),
            'skeletonCells' => implode("\n", $skeleton),
            'inputs' => implode("\n", $inputs),
            'searchColumn' => $fields[0]->name,
        ]);
    }

    /**
     * @param  array<string, string>  $names
     * @param  list<ModuleField>  $fields
     */
    protected function renderLang(array $names, array $fields, string $locale): string
    {
        $spanish = $locale === 'es';

        $labels = [
            'description' => $spanish ? 'Los '.Str::lower($names['title']).' de esta cuenta.' : 'The '.Str::lower($names['title']).' of this account.',
            'createLabel' => $spanish ? 'Crear '.Str::lower($names['singular']) : 'New '.Str::lower($names['singular']),
            'editLabel' => $spanish ? 'Editar '.Str::lower($names['singular']) : 'Edit '.Str::lower($names['singular']),
            'savedLabel' => $spanish ? 'Guardado' : 'Saved',
            'deletedLabel' => $spanish ? 'Eliminado' : 'Deleted',
            'confirmLabel' => $spanish ? '¿Eliminar este registro?' : 'Delete this record?',
            'searchLabel' => $spanish ? 'Buscar...' : 'Search...',
            'emptyLabel' => $spanish ? 'Todavía no hay nada aquí' : 'Nothing here yet',
            'emptyHintLabel' => $spanish ? 'Crea el primero para empezar.' : 'Create the first one to get started.',
        ];

        return $this->render('lang', $names, [
            ...$labels,
            'fields' => implode("\n", array_map(
                fn (ModuleField $f): string => "        '{$f->name}' => '{$f->label()}',",
                $fields,
            ))."\n",
        ]);
    }

    protected function plan(string $path, string $contents): void
    {
        if (File::exists($path) && ! $this->option('force')) {
            $this->skipped[] = $this->relative($path);

            return;
        }

        $this->planned[] = ['path' => $path, 'contents' => $contents];
    }

    /**
     * @param  list<array{path: string, contents: string, label: string}>  $edits
     */
    protected function report(array $edits): void
    {
        $verb = $this->option('pretend') ? 'Would write' : 'Wrote';

        $this->newLine();

        foreach ($this->planned as $file) {
            $this->line("  <fg=green>{$verb}</> ".$this->relative($file['path']));
        }

        foreach ($edits as $edit) {
            $this->line('  <fg=cyan>'.($this->option('pretend') ? 'Would update' : 'Updated')."</> {$edit['label']}");
        }

        foreach ($this->skipped as $path) {
            $this->line("  <fg=yellow>Kept</> {$path} <fg=gray>(exists; --force to overwrite)</>");
        }
    }

    protected function relative(string $path): string
    {
        return Str::after($path, base_path().'/');
    }
}
