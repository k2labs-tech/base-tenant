# Patrón de tabla — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que las cinco pantallas tabulares busquen, ordenen por columna, filtren y paginen, con el estado en la URL, sobre `flux:table`.

**Architecture:** Un trait de PHP, `InteractsWithTable`, aporta el estado compartido —búsqueda, orden, filas por página— con `#[Url]`, y nada más: la consulta sigue siendo de cada componente. El markup se escribe directamente en cada vista con `flux:table`, sin componente envoltorio propio: fue una decisión explícita del diseño, para que lo que se lee en la vista sea exactamente lo que se pinta y personalizar una pantalla no pelee con una abstracción.

**Tech Stack:** Laravel 12, Livewire 4, Flux UI v2.15 (gratuito), Tailwind v4, Pest 3.

**Alcance:** Plan 3 de 4 del rediseño de `docs/superpowers/specs/2026-08-02-rediseno-ux-ui-design.md`, sección 2. Los planes 1 y 2 están terminados: el tema existe, el vocabulario de color es uno solo, el armazón corre sobre Flux y todas las vistas tienen pareja oscura. La suite está en 225 tests.

**Estado de partida, medido:** de los ocho gestores, solo `user-manager`, `account-manager` y `activity-log` paginan; **ninguno ordena**; no hay un solo `#[Url]` en el paquete, así que ni filtros ni página sobreviven a un refresco ni se pueden compartir por enlace. La búsqueda está reimplementada a mano en cada componente.

**Una desviación consciente de las reglas de estos planes:** las tareas 3 a 6 no repiten el markup completo de la tabla, sino que remiten a `resources/views/livewire/user-manager.blade.php`, que la tarea 2 deja escrito en el repositorio. Repetir 200 líneas de markup cinco veces haría el documento inmanejable y, peor, garantizaría que las cinco copias divergieran en la primera corrección. Lo que sí se detalla por completo en cada tarea es lo que las diferencia: columnas, cuáles ordenan, filtros, acciones de fila y claves de idioma.

---

### Task 1: El trait `InteractsWithTable`

**Files:**
- Create: `src/Livewire/Concerns/InteractsWithTable.php`
- Create: `tests/Feature/InteractsWithTableTest.php`

**Lo que importa de seguridad, y es la razón de que este trait tenga un contrato en vez de ser cuatro propiedades sueltas:** `$sortBy` llega desde la URL, es decir, del usuario, y acaba en un `orderBy()`. Sin validación es una inyección directa en la consulta. Cada componente declara qué columnas admite ordenar, y el trait rechaza todo lo demás en silencio, volviendo al orden por defecto.

- [ ] **Step 1: Write the failing test**

Crea `tests/Feature/InteractsWithTableTest.php`:

```php
<?php

declare(strict_types=1);

use Base\Tenant\Livewire\UserManager;
use Livewire\Livewire;

beforeEach(function () {
    $this->syncPermissions();
});

test('ordenar por una columna declarada cambia el orden', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin', ['name' => 'Ana']);
    $this->createUser($cuenta, 'customer-user', ['name' => 'Zoe']);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->call('sort', 'name')
        ->assertSet('sortBy', 'name')
        ->assertSet('sortDirection', 'asc')
        ->call('sort', 'name')
        ->assertSet('sortDirection', 'desc');
});

/**
 * `sortBy` viaja por la URL, así que lo escribe el usuario. Sin lista blanca
 * acaba tal cual en un `orderBy()`.
 */
test('una columna no declarada se ignora', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->call('sort', 'password')
        ->assertSet('sortBy', null)
        ->assertOk();
});

test('cambiar la búsqueda vuelve a la primera página', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->set('page', 3)
        ->set('search', 'ana')
        ->assertSet('page', 1);
});

test('limpiar filtros deja la tabla en su estado inicial', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->set('search', 'ana')
        ->call('sort', 'email')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('sortBy', null);
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `./vendor/bin/pest tests/Feature/InteractsWithTableTest.php`
Expected: FAIL — `Method sort does not exist`.

- [ ] **Step 3: Write the trait**

Crea `src/Livewire/Concerns/InteractsWithTable.php`:

```php
<?php

declare(strict_types=1);

namespace Base\Tenant\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Estado compartido de las pantallas tabulares: búsqueda, orden y tamaño de
 * página, todo en la URL para que sobreviva a un refresco y se pueda compartir
 * por enlace.
 *
 * No toca la consulta: cada componente sigue siendo dueño de la suya y decide
 * qué columnas admite ordenar.
 */
trait InteractsWithTable
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: null)]
    public ?string $sortBy = null;

    #[Url(except: 'asc')]
    public string $sortDirection = 'asc';

    #[Url(except: 25)]
    public int $perPage = 25;

    /**
     * Columnas que este componente admite ordenar. Sin declararlas, no se
     * ordena por nada: `sortBy` viene de la URL y acabaría en un `orderBy()`.
     *
     * @return array<int, string>
     */
    abstract protected function sortableColumns(): array;

    public function sort(string $column): void
    {
        if (! in_array($column, $this->sortableColumns(), true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sortBy = $column;
        $this->sortDirection = 'asc';
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'sortBy', 'sortDirection']);
        $this->resetPage();
    }

    /**
     * Aplica el orden pedido, si es uno de los declarados, y deja que el
     * componente ponga el suyo por defecto cuando no hay ninguno.
     */
    protected function applySort(Builder $query, string $porDefecto): Builder
    {
        if ($this->sortBy === null || ! in_array($this->sortBy, $this->sortableColumns(), true)) {
            return $query->orderBy($porDefecto);
        }

        return $query->orderBy($this->sortBy, $this->sortDirection === 'desc' ? 'desc' : 'asc');
    }

    /** Cualquier cambio de criterio invalida la página en la que estabas. */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }
}
```

**Comprueba antes de darlo por bueno**, porque es mi lectura de Livewire 4 y no una cita: que `#[Url]` acepta `as:` y `except:` en esta versión, que `updatedSearch()` se dispara con `wire:model.live`, y que `WithPagination` puede convivir dentro de otro trait sin que los componentes que ya lo usan lo declaren dos veces — si eso da conflicto, quítalo de los componentes al aplicarlo.

- [ ] **Step 4: Wire it into `UserManager` so the test can exercise it**

En `src/Livewire/UserManager.php`: usa el trait en lugar de `WithPagination`, borra la propiedad `$search` que ya declara el trait, añade `sortableColumns()` devolviendo `['name', 'email', 'created_at']`, y en `users()` sustituye `->orderBy('name')` por `$this->applySort($query, 'name')` y `->paginate(10)` por `->paginate($this->perPage)`.

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest tests/Feature/InteractsWithTableTest.php` → 4 passed.
Run: `./vendor/bin/pest` → todo verde.

- [ ] **Step 6: Commit**

```bash
git add src/Livewire/Concerns/InteractsWithTable.php src/Livewire/UserManager.php tests/Feature/InteractsWithTableTest.php
git commit -m "feat: share table state through a trait, with the sort column whitelisted"
```

---

### Task 2: Usuarios, la implementación de referencia

Esta es la pantalla que las cuatro siguientes copian, así que su markup se detalla entero. Es también la que fija las decisiones de diseño ya aprobadas: cabecera dentro del componente, barra con buscador y panel de filtros, acciones de fila en menú de tres puntos, y tres estados vacíos distintos.

**Files:**
- Rewrite: `resources/views/livewire/user-manager.blade.php`
- Modify: `src/Livewire/UserManager.php`
- Modify: `resources/lang/en/users.php`, `resources/lang/es/users.php`, `resources/lang/en/common.php`, `resources/lang/es/common.php`
- Create: `tests/Feature/UserTableTest.php`

- [ ] **Step 1: Add the language keys**

En `common.php` (ambos idiomas), para lo que compartirán las cinco pantallas:

```php
    'search' => 'Search…',            // es: 'Buscar…'
    'filters' => 'Filters',           // es: 'Filtros'
    'clear_filters' => 'Clear',       // es: 'Limpiar'
    'per_page' => 'Per page',         // es: 'Por página'
    'actions' => 'Actions',           // es: 'Acciones'
    'empty_title' => 'Nothing here yet',          // es: 'Aquí no hay nada todavía'
    'empty_search' => 'No results for your search', // es: 'Tu búsqueda no encuentra nada'
    'empty_search_hint' => 'Try other terms or clear the filters.', // es: 'Prueba otros términos o limpia los filtros.'
```

En `users.php` (ambos), lo específico: `filter_role`, `all_roles`, `empty_description` («Invita a alguien para empezar.» / "Invite someone to get started.").

- [ ] **Step 2: Write the failing test**

Crea `tests/Feature/UserTableTest.php`, cubriendo lo que el patrón promete y que hoy no existe:

```php
<?php

declare(strict_types=1);

use Base\Tenant\Livewire\UserManager;
use Livewire\Livewire;

beforeEach(function () {
    $this->syncPermissions();
});

test('busca por nombre y por email', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin', ['name' => 'Ana Gil', 'email' => 'ana@example.com']);
    $this->createUser($cuenta, 'customer-user', ['name' => 'Zoe Ruiz', 'email' => 'zoe@example.com']);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->set('search', 'zoe@')
        ->assertSee('Zoe Ruiz')
        ->assertDontSee('Ana Gil');
});

test('ordena por nombre en los dos sentidos', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin', ['name' => 'Ana Gil']);
    $this->createUser($cuenta, 'customer-user', ['name' => 'Zoe Ruiz']);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->call('sort', 'name')
        ->assertSeeInOrder(['Ana Gil', 'Zoe Ruiz'])
        ->call('sort', 'name')
        ->assertSeeInOrder(['Zoe Ruiz', 'Ana Gil']);
});

test('filtra por rol', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin', ['name' => 'Ana Gil']);
    $this->createUser($cuenta, 'customer-user', ['name' => 'Zoe Ruiz']);

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->set('filterRole', 'customer-user')
        ->assertSee('Zoe Ruiz')
        ->assertDontSee('Ana Gil');
});

test('distingue no haber nada de no encontrar nada', function () {
    $cuenta = $this->createAccount();
    $admin = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($admin, $cuenta);

    Livewire::test(UserManager::class)
        ->set('search', 'nadie-se-llama-asi')
        ->assertSee(__('base-tenant::common.empty_search'))
        ->assertDontSee(__('base-tenant::common.empty_title'));
});
```

- [ ] **Step 3: Extend the component**

En `src/Livewire/UserManager.php`: añade `#[Url(except: '')] public string $filterRole = '';` y `updatedFilterRole()` que llame a `resetPage()`; aplica el filtro en `users()` con un `->when($this->filterRole, fn (Builder $q) => $q->whereHas('roles', fn ($r) => $r->where('name', $this->filterRole)))`; expón a la vista los roles asignables para el desplegable y un booleano `$hayFiltros` (`$this->search !== '' || $this->filterRole !== ''`) para elegir el estado vacío.

- [ ] **Step 4: Rewrite the view**

Sustituye `resources/views/livewire/user-manager.blade.php` por la estructura siguiente. **Verifica cada componente y prop contra los stubs de Flux** (`vendor/livewire/flux/stubs/resources/views/flux/table/*`, `input/*`, `select/*`, `dropdown.blade.php`, `menu/*`, `button/*`) y corrige lo que no case, informando de ello — el markup de abajo es mi lectura, no una cita, y en planes anteriores dicté markup equivocado más de una vez.

```blade
<div>
    {{-- Cabecera dentro del componente: se apaga con :heading="false" --}}
    @if($heading ?? true)
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('base-tenant::users.management_title') }}</flux:heading>
                <flux:subheading>{{ __('base-tenant::users.management_description') }}</flux:subheading>
            </div>

            @if($canEdit)
                <flux:button :href="route('base-tenant.users.create')" variant="primary" icon="plus" wire:navigate>
                    {{ __('base-tenant::users.add_new') }}
                </flux:button>
            @endif
        </div>
    @endif

    <flux:card class="p-0">
        {{-- Barra de herramientas --}}
        <div class="flex items-center gap-2 p-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                :placeholder="__('base-tenant::common.search')"
                icon="magnifying-glass"
                clearable
                class="flex-1"
            />

            <flux:dropdown>
                <flux:button icon="funnel" variant="subtle">
                    {{ __('base-tenant::common.filters') }}
                    @if($filterRole)
                        <flux:badge size="sm" color="accent">1</flux:badge>
                    @endif
                </flux:button>

                <flux:menu class="w-64 p-3">
                    <flux:select wire:model.live="filterRole" :label="__('base-tenant::users.filter_role')">
                        <flux:select.option value="">{{ __('base-tenant::users.all_roles') }}</flux:select.option>
                        @foreach($roles as $rol)
                            <flux:select.option :value="$rol->name">{{ $rol->label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:menu>
            </flux:dropdown>

            <flux:select wire:model.live="perPage" class="w-24">
                @foreach([10, 25, 50, 100] as $tamano)
                    <flux:select.option :value="$tamano">{{ $tamano }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        {{-- Filtros activos --}}
        @if($hayFiltros)
            <div class="flex flex-wrap items-center gap-2 px-3 pb-3">
                @if($filterRole)
                    <flux:badge size="sm" variant="pill">
                        {{ __('base-tenant::users.filter_role') }}: {{ $filterRole }}
                        <flux:badge.close wire:click="$set('filterRole', '')" />
                    </flux:badge>
                @endif

                <flux:link wire:click="resetFilters" class="text-sm">
                    {{ __('base-tenant::common.clear_filters') }}
                </flux:link>
            </div>
        @endif

        <flux:table :paginate="$users">
            <flux:table.columns>
                <flux:table.column
                    sortable
                    :sorted="$sortBy === 'name'"
                    :direction="$sortDirection"
                    wire:click="sort('name')"
                >{{ __('base-tenant::users.name') }}</flux:table.column>

                <flux:table.column
                    sortable
                    :sorted="$sortBy === 'email'"
                    :direction="$sortDirection"
                    wire:click="sort('email')"
                >{{ __('base-tenant::users.email') }}</flux:table.column>

                <flux:table.column>{{ __('base-tenant::users.roles') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @forelse($users as $usuario)
                    <flux:table.row :key="$usuario->id">
                        <flux:table.cell class="flex items-center gap-3">
                            <flux:avatar size="xs" :initials="$usuario->initials" />
                            {{ $usuario->name }}
                            @if($usuario->id === Auth::id())
                                <flux:badge size="sm" color="green">{{ __('base-tenant::users.you') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell variant="strong">{{ $usuario->email }}</flux:table.cell>

                        <flux:table.cell>
                            @foreach($usuario->roles as $rol)
                                <flux:badge size="sm" variant="pill">{{ $rol->label }}</flux:badge>
                            @endforeach
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            @if($canEdit)
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button icon="ellipsis-horizontal" variant="subtle" size="sm" square />

                                    <flux:menu>
                                        <flux:menu.item icon="pencil" :href="route('base-tenant.users.edit', $usuario)" wire:navigate>
                                            {{ __('base-tenant::users.edit') }}
                                        </flux:menu.item>

                                        @if($usuario->id !== Auth::id())
                                            @if($isSystemAdmin && $usuario->canBeImpersonated())
                                                <flux:menu.item icon="user-circle" wire:click="impersonate('{{ $usuario->id }}')">
                                                    {{ __('base-tenant::users.impersonate') }}
                                                </flux:menu.item>
                                            @endif

                                            <flux:menu.separator />

                                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete('{{ $usuario->id }}')">
                                                {{ __('base-tenant::users.remove') }}
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-12 text-center">
                            @if($hayFiltros)
                                <flux:heading>{{ __('base-tenant::common.empty_search') }}</flux:heading>
                                <flux:subheading>{{ __('base-tenant::common.empty_search_hint') }}</flux:subheading>
                                <flux:button wire:click="resetFilters" variant="subtle" size="sm" class="mt-3">
                                    {{ __('base-tenant::common.clear_filters') }}
                                </flux:button>
                            @else
                                <flux:heading>{{ __('base-tenant::common.empty_title') }}</flux:heading>
                                <flux:subheading>{{ __('base-tenant::users.empty_description') }}</flux:subheading>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- El modal de borrado ya existe; consérvalo tal cual --}}
</div>
```

Dudas concretas mías que hay que resolver leyendo los componentes, no adivinando:

- **`wire:click` sobre `flux:table.column`**: el stub renderiza un `<button data-flux-table-sortable>` dentro de la celda cuando `sortable` está presente. Comprueba si los atributos del componente llegan a ese botón o se quedan en el `<th>`; si se quedan, el clic no hará nada y habrá que envolver el contenido a mano.
- **`:direction`**: el stub lo usa para elegir el icono. Pásale `$sortDirection` solo cuando esa columna es la ordenada, o todas las flechas apuntarán igual.
- **`flux:badge.close`, `flux:link`, `flux:avatar size="xs"`, `flux:table.cell variant="strong"` y `align="end"`**: confirma que existen en la versión gratuita.
- **El tercer estado vacío** —«no tienes permiso»— no aparece aquí porque `mount()` ya autoriza y lanza un 403 antes de renderizar. Déjalo así y anótalo: el spec pedía tres estados, y este componente solo puede llegar a dos.

- [ ] **Step 5: Verify**

Run: `./vendor/bin/pest tests/Feature/UserTableTest.php` → 4 passed.
Run: `./vendor/bin/pest` → todo verde, incluidos `DarkModeTest` (el markup nuevo tiene que llevar sus parejas, o usar componentes Flux que ya las traen), `TranslationKeysTest` y `LivewireAuthorizationTest`.

- [ ] **Step 6: Commit**

```bash
git add -A resources/views resources/lang src/Livewire/UserManager.php tests/Feature/UserTableTest.php
git commit -m "feat: rebuild the user table on flux with search, sort and filters"
```

---

### Task 3: Cuentas

**Files:**
- Rewrite: `resources/views/livewire/account-manager.blade.php`
- Modify: `src/Livewire/AccountManager.php`
- Modify: `resources/lang/{en,es}/accounts.php`
- Create: `tests/Feature/AccountTableTest.php`

Aplica la estructura de `resources/views/livewire/user-manager.blade.php`, que ya existe tras la tarea 2. Lo que cambia:

- **Columnas:** nombre (ordenable), email (ordenable), usuarios (recuento, no ordenable), creada (`created_at`, ordenable, formateada con el formato de fecha de la cuenta), estado de suscripción (badge: activa → `success`, en prueba → `warning`, sin suscripción → `zinc`).
- **`sortableColumns()`:** `['name', 'email', 'created_at']`. El recuento de usuarios no entra: exige un `withCount` y ordenar por él necesita `orderBy('users_count')`, que solo funciona si la subconsulta está presente — hazlo solo si `withCount` ya está en la consulta, y si no, déjalo fuera y dilo.
- **Filtros:** estado de suscripción. Sin filtro de rol.
- **Acciones de fila:** editar, y borrar con confirmación. El modal de borrado ya existe en esta vista.
- **Estado vacío:** `accounts.empty_description`, que hay que añadir en los dos idiomas.
- **Tests:** los mismos cuatro casos que `UserTableTest`, adaptados.

- [ ] **Step 1: Escribe el test primero, míralo fallar, implementa, verifica, commitea**

```bash
git commit -m "feat: rebuild the account table on the shared pattern"
```

---

### Task 4: Invitaciones

**Files:**
- Rewrite: `resources/views/livewire/invitation-manager.blade.php`
- Modify: `src/Livewire/InvitationManager.php`
- Modify: `resources/lang/{en,es}/invitations.php`
- Create: `tests/Feature/InvitationTableTest.php`

Esta pantalla **no pagina hoy** y tiene además un formulario de alta encima de la tabla; consérvalo donde está, encima de la tarjeta, y no lo conviertas en modal.

- **Columnas:** email (ordenable), rol, quién invitó, caduca (`expires_at`, ordenable, con badge `warning` cuando quedan menos de 48 horas y `danger` cuando ya caducó), acciones.
- **`sortableColumns()`:** `['email', 'expires_at', 'created_at']`.
- **Filtros:** estado —pendiente, caducada, aceptada—. Comprueba en `src/Models/UserInvite.php` qué scopes existen ya (`pending()` existe) antes de inventar la consulta.
- **Acciones de fila:** reenviar y revocar, ambas ya implementadas en el componente.
- **Paginación:** añádela; usa `$this->perPage` como las demás.
- **Estado vacío:** `invitations.no_pending` ya existe; úsala para el caso «no hay nada» y añade la variante de búsqueda.

- [ ] **Step 1: Escribe el test primero, míralo fallar, implementa, verifica, commitea**

```bash
git commit -m "feat: rebuild the invitation table on the shared pattern"
```

---

### Task 5: Registro de actividad, el caso difícil

Es la pantalla que decide si el patrón aguanta: tres filtros más un rango de fechas, y la que más filas acumula.

**Files:**
- Rewrite: `resources/views/livewire/activity-log.blade.php`
- Modify: `src/Livewire/ActivityLog.php`
- Modify: `resources/lang/{en,es}/activity.php`
- Create: `tests/Feature/ActivityTableTest.php`

- **Columnas:** fecha (`created_at`, ordenable, **orden por defecto descendente** — un registro de actividad se lee por lo más reciente), usuario, acción (badge), elemento, descripción.
- **`sortableColumns()`:** `['created_at', 'action']`.
- **Filtros:** usuario, acción y rango de fechas. Los dos primeros ya existen como `$filterUser` y `$filterAction`; añade `$filterFrom` y `$filterUntil`, ambos con `#[Url]`.
- **La mejora de Flux Pro entra aquí, y solo aquí.** Envuelta en `@if(Flux::pro())`, un `flux:date-picker` en modo rango para las fechas; sin Pro, dos `flux:input type="date"`. Comprueba la firma real del componente si el paquete `livewire/flux-pro` está instalado; si no lo está, escribe la rama igualmente pero **no puedes probarla** — dilo en el informe en lugar de afirmar que funciona.
- **Igual para el filtro de usuario:** si el número de usuarios de la cuenta supera los diez, un `flux:select variant="combobox"` bajo `Flux::pro()`, y el `select` nativo como alternativa.
- **Estado vacío:** `activity.no_activities_found` ya existe.

- [ ] **Step 1: Escribe el test primero, míralo fallar, implementa, verifica, commitea**

Incluye un test del rango de fechas: una entrada dentro y otra fuera, y comprueba que solo se ve la de dentro.

```bash
git commit -m "feat: rebuild the activity log with filters and a date range"
```

---

### Task 6: Features

Hoy es una lista con interruptores, no una tabla. Pasa a tabla, que es lo que pide en cuanto una aplicación registra unas cuantas.

**Files:**
- Rewrite: `resources/views/livewire/feature-manager.blade.php`
- Modify: `src/Livewire/FeatureManager.php`
- Modify: `resources/lang/{en,es}/features.php`
- Create: `tests/Feature/FeatureTableTest.php`

- **Columnas:** funcionalidad (nombre y descripción en la misma celda), ámbito (global o de la cuenta, badge `info` para global), valor, caducidad, acciones.
- **`sortableColumns()`:** `['key']`.
- **Filtros:** ámbito. La búsqueda va contra la clave y el nombre.
- **Cuidado:** este componente lee de `FeatureManager` (el servicio, `src/Features/FeatureManager.php`), no directamente de un modelo, y devuelve una `Collection`, no un `LengthAwarePaginator`. Decide si paginar en memoria o no paginar, y **dilo**: `flux:table` acepta `:paginate` solo con un paginador. Si el catálogo de features es por definición corto, no paginar es la respuesta correcta; en ese caso el selector de filas por página tampoco pinta nada y hay que quitarlo de esta pantalla.

- [ ] **Step 1: Escribe el test primero, míralo fallar, implementa, verifica, commitea**

```bash
git commit -m "feat: turn the feature list into a table"
```

---

### Task 7: Guarda del patrón

**Files:**
- Create: `tests/Feature/TablePatternTest.php`

Un test que recorra los cinco componentes tabulares y verifique que cumplen el contrato, para que una pantalla nueva no se salte el patrón por descuido:

- Todos usan `InteractsWithTable`.
- Todos declaran `sortableColumns()` no vacío.
- Ninguno admite ordenar por una columna que no esté en su lista — llama a `sort()` con `'password'` y comprueba que `sortBy` sigue nulo.
- Todos tienen `$search`, `$sortBy`, `$sortDirection` y `$perPage` marcados con `#[Url]`, verificable por reflexión sobre los atributos de las propiedades.

Ese último punto es el que más vale: es lo que garantiza que el estado se pueda compartir por enlace, y es justo lo que hoy no cumple ninguna pantalla.

```bash
git commit -m "test: hold every table screen to the shared contract"
```

---

## Definición de terminado

- [ ] `./vendor/bin/pest` en verde con los tests nuevos de las cinco pantallas más el del contrato.
- [ ] Las cinco buscan, ordenan por al menos dos columnas, filtran y —salvo features, si se decide no paginar— paginan.
- [ ] Recargar la página con filtros aplicados los conserva; copiar la URL y abrirla en otra pestaña reproduce la misma vista.
- [ ] Ninguna pantalla ordena por una columna no declarada, ni siquiera manipulando la URL a mano.
- [ ] `DarkModeTest` sigue verde: el markup nuevo lleva sus parejas o usa componentes de Flux que ya las traen.
- [ ] `./vendor/bin/pint --dirty` sin cambios pendientes.

## Comprobación manual

1. Ordenar por una columna, refrescar: el orden se mantiene y la flecha sigue donde estaba.
2. Aplicar dos filtros, copiar la URL, abrirla en una ventana de incógnito tras iniciar sesión: misma vista.
3. Manipular `?sortBy=password` a mano: la tabla vuelve a su orden por defecto sin error.
4. En móvil: la tabla desplaza en horizontal dentro de su tarjeta, sin que la página entera se desplace.
5. En oscuro: las cinco pantallas, incluidos badges de estado y menús de acciones.

## Qué queda para el plan 4

| Plan | Contenido | Sección del spec |
|---|---|---|
| 4 | Patrón de formulario a dos columnas; retirada de los componentes que sustituye Flux; confirmaciones con `flux:modal`; el antipatrón de `<form>` abierto entre condicionales en `edit-user` | 3 y 5 |
