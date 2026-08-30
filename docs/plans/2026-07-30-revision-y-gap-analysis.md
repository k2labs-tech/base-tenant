# base/tenant — Revisión completa y análisis de brechas

> Fecha: 30 julio 2026 · Rama analizada: `feat/checkonline-compat` (64c9ee9)
> Contraste con: `k2_base_tenant_funcional.md` (v1.0, 29 julio 2026)

---

## 1. Resumen ejecutivo

`base/tenant` hoy es un **starter kit de autenticación + cuentas + Cashier**, no una capa de tenancy. Cubre bien la superficie de "usuario entra, paga y ve un panel": auth con 2FA, invitaciones, notificaciones, activity log, settings clave-valor, planes por config y un instalador. Lo que **no** tiene es el núcleo que el documento de K2 da por supuesto:

- **No hay aislamiento de tenant**. Cero global scopes, cero trait `BelongsToAccount`, cero `Tenant::current()`. El `account_id` se filtra a mano en cada componente Livewire, y solo en algunos.
- **No hay permisos**. Solo roles, y la comprobación de rol vive **en la sesión HTTP**, así que no funciona en jobs, comandos ni API.
- **No hay menús por BBDD**. La navegación es Blade estático con datos de demo hardcodeados (`78%`, `24 tareas`, `+23%`).
- **No hay contrato de paquete**. 252 líneas de tests unitarios, ninguno de aislamiento multi-tenant.

Además hay **fugas de la aplicación anfitriona dentro del paquete**: referencias a tablas `projects` y `translations`, y roles `project-admin` / `project-collaborator` que **no existen en la config**. Esto último hace que, en una instalación limpia, ningún usuario que no sea system admin pueda entrar al gestor de usuarios.

Mi lectura: el salto de aquí a la v1.0 del documento K2 no es "añadir módulos", es **refundar el eje tenancy/autorización** y luego colgar los módulos encima. Intentar el orden inverso multiplica el trabajo.

---

## 2. Inventario real del paquete

### Lo que existe y funciona

| Área | Implementación | Valoración |
|---|---|---|
| Auth | Livewire: login, registro, reset, verify, confirm, force-change | Completo |
| 2FA | TOTP con `pragmarx/google2fa`, QR, códigos de recuperación | Completo |
| Cuentas | `Account` (UUID, soft deletes, Billable), pivot `account_user`, switcher | Base sólida |
| Roles | Tabla `roles` + pivot `role_user`, sync desde config, `is_system` | Funcional pero limitado (§3, §4) |
| Invitaciones | `InvitationService`, token, aceptación pre/post signup, `expires_at` | Completo |
| Billing | Cashier 16, checkout, middleware `HasSubscription` | Suscripción simple |
| Planes | `FeatureService` sobre `config('base-tenant.plans')` | Estático, no por cuenta |
| Notificaciones | `BaseTenantNotification`, campana con polling, índice | Solo canal `database` |
| Activity log | `ActivityLog` polimórfico, trait `LogsActivity`, poda | Sin actor `system`/`agent` |
| Settings | `Setting` polimórfico, cascada user→account, `tenant_setting()` | Sin tipado ni schema |
| Impersonación | `lab404/laravel-impersonate` | Sin auditoría ni consentimiento |
| i18n | ES/EN completos, `SetLocale` | Bien |
| Instalador | `InstallCommand` + `ConflictDetector` / `EnvironmentManager` / `MigrationRunner` | Muy trabajado |

### Lo que no existe

Equipos · Permisos granulares · Feature flags por cuenta · Menús dinámicos · API REST/tokens · Vault de credenciales · Outbox · Webhooks salientes · Alertas de negocio · Retención/legal holds · Trace ID · Multi-guard / portales · Tokens de recurso firmados · Visibilidad por partner · Broadcasting · Onboarding/wizard · Búsqueda global · Import/export · Soft-delete recovery UI · Health checks.

---

## 3. Hallazgos concretos del código

Ordenados por severidad. Todos verificados en el árbol actual.

### 3.1 Crítico — El paquete referencia tablas de la app anfitriona

`src/Livewire/AccountManager.php:113` y `:126` consultan `projects` y `translations` directamente:

```php
$projectsCount = \DB::table('projects')->where('account_id', ...)->count();
$translationsCount = \DB::table('translations')->where('account_id', ...)->count();
```

En cualquier proyecto que no tenga esas tablas, **borrar una cuenta lanza excepción SQL**. Viola frontalmente el DoD del documento K2 ("ningún `use App\` dentro del paquete" — el espíritu es el mismo).

### 3.2 Crítico — Detección de relaciones solo funciona en SQLite

`src/Livewire/AccountManager.php:133-135`:

```php
$tables = \DB::select("SELECT name FROM sqlite_master WHERE type='table' ...");
$columns = \DB::select("PRAGMA table_info($tableName)");
```

En MySQL o PostgreSQL esto **falla o devuelve vacío**, es decir: en producción el guard de "no borrar cuentas con datos" no protege nada. Debe usar `Schema::getTables()` / `Schema::hasColumn()`.

### 3.3 Crítico — Roles comprobados contra roles que no existen

La config define `customer-admin`, `customer-user`, `customer-viewer`, `customer-finance`. El código comprueba `project-admin` y `project-collaborator`:

- `src/Livewire/UserManager.php:26-27, 100`
- `src/Livewire/AccountManager.php:26, 79`
- `src/Livewire/EditUser.php` (varios puntos)

Consecuencia en instalación limpia: `abort(403)` para todo usuario que no sea system admin. El gestor de usuarios es inaccesible para el rol que precisamente debería usarlo.

### 3.4 Grave — `hasRole()` depende de la sesión HTTP

`src/Models/User.php:225-232`:

```php
public function hasRole(string $role): bool
{
    if (! session()->has('user.roles')) { $this->storeRolesSession(); }
    return in_array($role, session()->get('user.roles', []));
}
```

Implicaciones:
- En un **job**, un **comando artisan** o una **petición API con Sanctum** no hay sesión → `storeRolesSession()` sin `current_account_id` devuelve *todos* los roles de todas las cuentas → escalada de privilegios entre tenants.
- Comprobar el rol de **otro** usuario (`$otherUser->hasRole(...)`) devuelve los roles del usuario **en sesión**. Es un bug latente serio.
- La caché de sesión no se invalida al cambiar roles: hay que acordarse de llamar `storeRolesSession()` a mano (se hace en switch e impersonación, no en `EditUser`).

### 3.5 Grave — `detach()` de roles borra los de todas las cuentas

`src/Livewire/EditUser.php:389`:

```php
$this->user->roles()->detach();
```

Con `role_user.account_id` recién añadido, editar a un usuario desde la cuenta A **le borra los roles de la cuenta B**. El `attach` sí pasa `account_id` (`:305`, `:394`) pero la rama `sync()` (`:309`, `:398`) lo pierde, y `User::addRole()` (`:159`) nunca lo escribe.

La relación tampoco declara el pivot:

```php
public function roles(): BelongsToMany
{
    return $this->belongsToMany(config('base-tenant.models.role', Role::class));
    // falta ->withPivot('account_id')->withTimestamps()
}
```

### 3.6 Grave — Ningún aislamiento automático de tenant

`grep addGlobalScope src/` → 0 resultados. No existe `BelongsToAccount`, ni `Tenant::current()`, ni resolución de tenant fuera de `session('current_account_id')`. Todo modelo de negocio de la app anfitriona debe filtrar a mano, y una consulta olvidada es una fuga de datos entre clientes.

`SetAccountContext` además **exime a los superadmin** (`is_admin || account_id === null`) sin fijar contexto, con lo que el estado "sin tenant" es indistinguible de "tenant no resuelto".

### 3.7 Medio — Placeholders en producción

- `src/Models/User.php:121-124` → `hasAlerts()` devuelve `random_int(0, 1)`.
- `resources/views/layouts/navigation-items.blade.php` → menú entero con enlaces `href="#"` y métricas inventadas (78%, 24, 32 members, +12%).

### 3.8 Medio — Componentes Livewire referencian el layout de la app

`#[Layout('layouts.app')]` en `UserManager`, `AccountManager`, `EditUser`… apunta a la vista de la app anfitriona, no a `base-tenant::layouts.app`. Es la clase de fallo que arreglaba el commit 64c9ee9; conviene revisarlo entero y decidir una convención (layout configurable).

### 3.9 Medio — Sanctum instalado, sin usar

`laravel/sanctum` está en `composer.json` y existe la migración `personal_access_tokens`, pero no hay `routes/api.php`, ni guard `api`, ni gestión de tokens en UI. Dependencia muerta o funcionalidad a medio hacer.

### 3.10 Medio — Cobertura de tests casi nula

3 ficheros, 252 líneas, todos unitarios de modelo. Cero tests de aislamiento, autorización, invitaciones, billing o Livewire. El documento K2 exige un **kit de aserciones multi-tenant** (`assertTenantIsolated`, `assertJobCarriesTenant`, `assertPartnerScoped`) como criterio de aceptación de KB-01.

---

## 4. Comparativa contra el funcional K2 (v1.0)

| Módulo K2 | Backlog | Estado en `base/tenant` | Brecha |
|---|---|---|---|
| **Tenancy Core** | KB-01 | 🔴 Parcial | Existe `Account` y sesión. Falta: `Tenant::current()`, `BelongsToAccount`, global scope, resolución por dominio/API key, y **propagación a jobs, cache, scheduler y broadcasting** |
| **Visibility** | KB-02 | 🔴 Ausente | No existe la segunda dimensión (`VisibleToPartner`) ni registro de partner types |
| **Access** | KB-03 | 🟠 Parcial | Auth + 2FA + impersonación sí. Falta: multi-guard, portales, magic links, `ResourceToken` firmado con TTL |
| **Teams** | KB-04 | 🔴 Ausente | No hay `teams` ni `team_members` ni `AssigneeResolverContract`. `account_user` no es un equipo |
| **Flags** | KB-05 | 🟠 Parcial | `FeatureService` lee **config estática por plan**. Falta: flags por cuenta persistidos, UI admin, `Feature::for($account)->active('x')` |
| **Settings** | KB-06 | 🟠 Parcial | Clave-valor con cascada. Falta: clases tipadas con schema, scaffolding UI, defaults de producto |
| **Vault** | KB-07 | 🔴 Ausente | Sin `integration_credentials`, cifrado, rotación ni health-check |
| **Outbox** | KB-08 | 🔴 Ausente | Sin outbox, sin dedupe, sin DLQ |
| **SaaS Billing** | KB-09 | 🟠 Parcial | Cashier + checkout. Falta: cantidades por unidad y modo, tramos, add-ons medidos, medidores registrables |
| **Audit** | KB-10 | 🟠 Parcial | `ActivityLog` sí. Falta: `ActorResolver` con `user\|system\|agent` |
| **Locale** | KB-11 | 🟠 Parcial | Pila cuenta→usuario existe vía settings. Falta: `LocaleResolver::forContact()` |
| **AlertCenter** | KB-12 | 🔴 Ausente | `Alerts\Table` es un stub sobre `hasAlerts()` aleatorio |
| **Retention** | KB-13 | 🔴 Ausente | Solo poda de activity log por días. Sin reglas por tipo ni legal holds |
| **Observability** | KB-14 | 🔴 Ausente | Sin `TraceId`, sin log JSON estructurado |
| **Support** | KB-15 | 🟠 Parcial | Impersonación funciona pero **sin auditar y sin consentimiento** |

**Lectura:** 0 de 15 módulos completos. 7 parciales, 8 ausentes. La v0.9 de K2 (gates KB-01/05/07/08/14 en verde) exige trabajo en 5 módulos de los cuales 3 están a cero.

---

## 5. Comparativa contra un "SaaS boilerplate completo"

Más allá del documento K2, esto es lo que separa este paquete de los boilerplates de referencia (Jetstream+Spatie, Filament, Nova, Bullet Train):

### 5.1 RBAC granular — **la brecha más grande**

Hoy: rol → cadena. Punto. No hay permisos, no hay recursos, no hay acciones.

Lo que necesita un SaaS B2B serio:

```
permissions        (key, group, description)              users.create, invoices.approve
role_permission    (role_id, permission_id)
role_user          (role_id, user_id, account_id)         ← ya existe la columna
```

Y sobre eso:
- **Roles por cuenta**: `roles.account_id` nullable → roles de sistema (globales) + roles que cada cliente define. Hoy los roles son globales y todos los tenants comparten los mismos.
- **Gates registradas**: `Gate::before()` que resuelva permisos del usuario **en la cuenta activa**, con caché por `(user, account)` invalidada en escritura — no en sesión.
- **Policies** para `User`, `Account`, `Role`, `UserInvite`. Hoy no hay ninguna: la autorización son `if` sueltos repetidos en cada componente.
- **Permisos a nivel de campo/registro** para casos como "el finance ve importes, el viewer no".
- **UI de gestión de roles**: matriz rol × permiso. Ahora mismo los roles solo se editan tocando `config/base-tenant.php` y ejecutando `base-tenant:sync-roles`.

### 5.2 Menús por configuración de BBDD

Nada de esto existe. Propuesta mínima:

```
menus            id, key, account_id?, name
menu_items       id, menu_id, parent_id, label_key, icon, route_name, url,
                 permission, feature_flag, order, is_active, meta(json)
```

Con:
- **Resolución en cascada**: menú de producto (definido por código/seeder) → overrides por cuenta → visibilidad por permiso y por feature flag.
- **Registro programático** desde módulos: `Menu::register('main', fn($m) => $m->item(...))` para que cada paquete de negocio cuelgue sus entradas sin tocar el core.
- **Caché por `(account, rol-set, locale)`** con invalidación en cambio.
- **UI drag & drop** de ordenación.
- **Badges dinámicos** resueltos por callback (`->badge(fn() => Task::pending()->count())`) — que es lo que el menú actual finge con números fijos.

### 5.3 API y tokens

- `routes/api.php` con guard `sanctum`.
- **API keys por cuenta** con scopes, expiración, último uso, y **resolución de tenant desde la key** (KB-01 lo pide explícitamente).
- Rate limiting por cuenta y por plan.
- Webhooks salientes con firma HMAC, reintentos y log de entregas.
- Documentación (Scramble ya está en el CHANGELOG como "planned").

### 5.4 Onboarding y ciclo de vida de cuenta

- Wizard post-registro (`accounts.onboarded_at` existe pero nadie lo usa).
- Estados de cuenta: `active | trialing | past_due | suspended | cancelled` con middleware que actúe en consecuencia. Hoy solo hay `hasActiveSubscription()`.
- Cancelación con periodo de gracia y **exportación de datos** (GDPR).
- Borrado en cascada real y auditado (lo que el `AccountManager` intenta hacer a mano con SQL crudo).

### 5.5 Operación y cumplimiento

- Panel de superadmin: métricas MRR/churn, cuentas, uso, logs.
- Health checks (`spatie/laravel-health`) y trace ID en logs.
- Consentimiento de impersonación + registro de sesiones (`impersonation_sessions`).
- Retención por tipo de dato y legal holds.
- Exportación/portabilidad y derecho al olvido.

### 5.6 Calidad de vida para los proyectos que estáis montando

Mirando qué construís últimamente (PMS, Perfilinquilino, ContentHub, IURISDOCS — todos con integraciones externas y contenido por cuenta), lo que más rendimiento daría:

1. **Vault de credenciales** (KB-07). Todos esos productos hablan con APIs de terceros por cuenta. Sin esto, cada proyecto reinventa el cifrado.
2. **Outbox** (KB-08). Igual: sincronizaciones salientes con reintentos y dedupe aparecen en PMS y ContentHub.
3. **Media/attachments por cuenta** con cuota — no está en K2 y lo necesitáis en casi todo.
4. **Búsqueda global multi-tenant** (Scout con filtro de tenant).
5. **Import/export CSV genérico** con validación y preview.
6. **Cola de emails transaccionales con plantillas por cuenta** (branding del cliente).
7. **Campos personalizados por cuenta** — recurrente en B2B; encaja como extensión de `settings` tipados.

---

## 6. Arquitectura propuesta para los cuatro ejes que pides

### 6.1 Tenancy — contexto único, no sesión

```php
// Base\Tenant\Tenancy\TenantManager (singleton)
Tenant::current(): ?Account
Tenant::set(Account $a): void
Tenant::forget(): void
Tenant::runFor(Account $a, Closure $fn): mixed
```

Resolución en cadena de responsabilidad: **dominio/subdominio → API key → sesión → `last_account_id`**. Un solo punto de verdad; la sesión pasa a ser un *driver*, no el estado.

```php
trait BelongsToAccount
{
    public static function bootBelongsToAccount(): void
    {
        static::addGlobalScope(new AccountScope);          // where account_id = Tenant::current()->id
        static::creating(fn ($m) => $m->account_id ??= Tenant::currentId());
    }
}
```

Propagación obligatoria (es lo que KB-01 exige probar con tests, no leyendo código):
- **Jobs**: middleware que serializa `account_id` en el payload y lo restaura en `handle()`.
- **Cache**: prefijo `tenant:{id}:` automático.
- **Scheduler**: comandos que iteran cuentas con `Tenant::runFor()`.
- **Broadcasting**: canales con el tenant en el nombre y autorización que lo verifica.

### 6.2 RBAC granular

```php
$user->can('users.create');                      // permiso en la cuenta activa
$user->can('update', $invoice);                  // policy, con scope de tenant implícito
$user->hasPermissionTo('invoices.approve', $account);
```

Implementación: tabla `permissions` + `role_permission`, `roles.account_id` nullable, `Gate::before` que resuelve desde **caché por `(user_id, account_id)`** (no sesión), invalidada por evento en cualquier escritura de roles/permisos. `hasRole()` se conserva como azúcar pero delega en la misma resolución.

Migración desde lo actual: los roles de config se mapean a conjuntos de permisos, así que el código existente (`hasRole('customer-admin')`) sigue funcionando durante la transición.

### 6.3 Menús por BBDD

Ver §5.2. Punto clave de diseño: **el menú no se edita a mano en BBDD**, se *declara* en código por cada módulo y la BBDD guarda solo overrides por cuenta (orden, visibilidad, etiqueta). Así un despliegue nuevo no requiere seeders manuales y el cliente sigue pudiendo personalizar.

### 6.4 Flags y settings tipados

```php
Feature::for($account)->active('content_studio');    // tabla features, no config
Settings::for($account)->get(BrandVoiceSettings::class);  // clase con schema y defaults
```

`FeatureService` actual pasa a ser el *driver de plan* de un sistema de flags con tres capas: **plan → override por cuenta → override por entorno**.

---

## 7. Roadmap propuesto

Fases pensadas para que cada una deje el paquete usable, no para seguir el orden del documento K2 literalmente.

### Fase 0 — Saneamiento (1–2 semanas)

Sin esto, cualquier cosa que construyamos encima hereda los fallos.

1. Eliminar referencias a `projects`/`translations` y al SQL SQLite-específico (§3.1, §3.2).
2. Unificar roles: decidir el juego real y quitar `project-admin`/`project-collaborator` (§3.3).
3. `roles()->withPivot('account_id')`, arreglar `detach()`/`sync()`/`addRole()` (§3.5).
4. Quitar `hasAlerts()` aleatorio y los datos de demo del menú (§3.7).
5. Layouts configurables en componentes Livewire (§3.8).
6. Suite de tests de contrato con `assertTenantIsolated` en rojo → sirve de red para todo lo demás.

### Fase 1 — Núcleo tenancy + RBAC (3–4 semanas)

7. `TenantManager`, `BelongsToAccount`, `AccountScope`.
8. Propagación a jobs / cache / scheduler / broadcasting, **con test por cada uno**.
9. Tabla `permissions`, `role_permission`, `roles.account_id`; `Gate::before`; caché con invalidación.
10. Policies para los modelos del paquete; sustituir los `if` de los componentes.
11. `hasRole()` sin sesión (compatible hacia atrás).

*Al final de esta fase se cierra KB-01 y se cubre la brecha 5.1.*

### Fase 2 — Configurabilidad (2–3 semanas)

12. Menús por BBDD con registro programático, permisos, flags y caché.
13. Feature flags por cuenta con UI admin (KB-05).
14. Settings tipados con clases de schema (KB-06).
15. UI de gestión de roles y permisos (matriz).

### Fase 3 — Integraciones (3–4 semanas)

16. Vault de credenciales cifradas con rotación y health-check (KB-07).
17. Outbox con dedupe, reintentos y DLQ visible (KB-08).
18. API: `routes/api.php`, API keys por cuenta con scopes, resolución de tenant por key, rate limit por plan.
19. Webhooks salientes firmados.

### Fase 4 — Operación (2–3 semanas)

20. `TraceId` + log JSON (KB-14).
21. `ActorResolver` `user|system|agent` en audit (KB-10).
22. AlertCenter real (KB-12).
23. Impersonación auditada con consentimiento (KB-15).
24. Retención por tipo + legal holds (KB-13).

### Fase 5 — Extensión (según necesidad)

25. Teams y pools de asignación (KB-04).
26. Multi-guard, portales, magic links, `ResourceToken` (KB-03).
27. Visibility / partner types (KB-02).
28. Billing avanzado: unidades, tramos, add-ons medidos (KB-09).

**Estimación total Fases 0–4: ~11–16 semanas** de trabajo dedicado. Las fases 0 y 1 son las que no admiten atajo.

---

## 8. Decisiones que hay que tomar antes de empezar

Estas condicionan la arquitectura y no las puedo resolver yo:

1. **¿`base/tenant` se convierte en `k2/base-tenant` o son paquetes distintos?** El documento K2 describe un paquete con namespace y plan de versiones propios. Si es el mismo, hay que planificar el renombrado de namespace (`Base\Tenant` → `K2\BaseTenant`) y el upgrade path de las apps ya desplegadas.

2. **¿Construir el RBAC o adoptar `spatie/laravel-permission`?** Spatie tiene teams desde v5 y resolvería el 70% de §5.1 en días en vez de semanas. El coste es adaptar el modelo `Role` actual y aceptar sus convenciones. Mi recomendación: **adoptarlo y envolverlo** en una fachada propia, para no rehacer caché, migraciones y UI que ya están resueltos y probados.

3. **¿Multi-team pasa a ser siempre `true`?** El flag `multi_team` duplica la lógica en cada consulta (`UserManager` tiene tres ramas por esto). Unificar en el modelo pivot y dejar que las apps single-team simplemente tengan una cuenta por usuario simplifica mucho.

4. **¿Perfilinquilino es el banco de pruebas?** El documento lo señala como prueba de no-regresión. Si es así, la Fase 1 debe ejecutarse contra su base de datos real antes de dar KB-01 por cerrado.

5. **¿Flux Pro sigue siendo la UI?** Es dependencia comercial y ata el paquete a Livewire 4. Para el menú dinámico y la matriz de permisos hay bastante UI por construir; conviene confirmar antes.

---

## 9. Lo que haría primero, si tuviera que elegir una sola cosa

Los tres bugs de la sección §3.1–§3.3. Son ~2 horas de trabajo y hoy dejan el paquete **roto en cualquier instalación limpia sobre MySQL**: borrar una cuenta lanza excepción, el guard de integridad no protege, y el gestor de usuarios devuelve 403 al rol que debería usarlo. Todo lo demás de este documento es construcción; eso es una fuga.
