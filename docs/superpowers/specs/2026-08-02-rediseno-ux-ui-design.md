# Rediseño UX/UI de las pantallas del paquete

> Diseño validado el 2026-08-02. Cubre las 60 vistas vivas del paquete: fundamentos de tema, patrón de tabla, patrón de formulario, shell y estados.

## Por qué

La funcionalidad está construida, pero el acabado visual no la acompaña. La causa no es que el diseño sea flojo: es que **una parte de la hoja de estilos no llega a compilarse**.

Las vistas pintan con una paleta que nadie define. El instalador escribe en el `app.css` de la aplicación tres escalas (`surface`, `primary`, `accent`) y cuatro tokens planos (`success`, `error`, `warning`, `info`, sin escala). Las vistas, en cambio, usan:

| Utilidad | Usos | Estado |
|---|---|---|
| `secondary-*` (100→900) | 103 | No existe |
| `shadow-soft` | 34 | No existe |
| `success/warning/error-100…800` | 17 | Solo existe el token plano, sin escala |
| `gray-*` crudo | 205 | Existe, pero convive con la paleta semántica |

En Tailwind v4 una clase inexistente no da error: simplemente no aplica la propiedad. Como `border` y `divide-y` sí aplican grosor y heredan `currentColor`, el resultado son separadores casi negros, badges de estado transparentes y tarjetas sin sombra.

A esto se suman tres carencias funcionales:

- De las 8 pantallas de gestión, **solo 3 paginan** y **ninguna ordena** por columna.
- **No hay un solo `#[Url]`** en el paquete: ni filtros ni página sobreviven a un refresco, ni se pueden compartir por enlace.
- **16 de las 76 vistas están muertas** (nadie las referencia), un 21% de la superficie.

## Decisiones

| # | Decisión | Elegido |
|---|---|---|
| 1 | Propiedad del tema | El paquete define **solo** la escala de estados; el resto sale de Flux — **enmendada**, ver R-1: Flux no define ninguna escala `accent`, así que el paquete define también esa |
| 2 | Política con Flux Pro | Gratuito por defecto; Pro solo en filtro de fechas y desplegables largos — **enmendada**, ver R-2: el mecanismo `@if(Flux::pro())` no puede ser el que se escribió, porque Blade resuelve las etiquetas al compilar |
| 3 | Modo oscuro | Claro y oscuro, con selector en el menú de usuario |
| 4 | Cabecera de página | Dentro del componente, desactivable con `:heading="false"` |
| 5 | Alcance | Un spec de sistema; las 60 vistas se resuelven aplicándolo |
| 6 | Construcción de tablas | Flux directo en cada vista, compartiendo solo el trait de PHP |

Sobre la 6: se descartó un componente envoltorio propio (`<x-base-tenant::table>`) y un componente Livewire genérico configurado por PHP. El primero añade una capa que documentar y mantener; el segundo se rompe en cuanto una pantalla se sale del molde, y aquí ya hay dos que lo hacen (la matriz de permisos de roles y el árbol ordenable de navegación). La coherencia entre las 8 tablas la sostienen el trait, el patrón documentado y los tests, no una abstracción.

Sobre la 5: el patrón de tabla se valida en el spec contra **Usuarios** (el caso simple) y **Log de actividad** (el caso con más filtros y volumen), para que el sistema nazca probado contra el extremo difícil.

---

## Sección 1 — Fundamentos

### Vocabulario de color

Desaparecen de las vistas `secondary-*`, `surface-*` y `primary-*`. Se sustituyen por lo que Flux ya garantiza: `zinc-*` para superficies y texto, `accent-*` para la acción principal.

El paquete aporta un único fichero de tema, `resources/css/base-tenant.css`, con un bloque `@theme` que define lo que Flux no tiene: `success`, `warning`, `danger` e `info` con escala completa (50→900).

El instalador pasa a añadir un `@import` de ese fichero al `app.css` de la aplicación, en lugar del bloque de alias que inyecta hoy. Las instalaciones existentes no se rompen: su bloque actual permanece, y el `@import` solo añade las escalas de estado que faltaban.

### Retirada de reliquias

Se eliminan dos ficheros de Tailwind v3 que no participan en la compilación real (que va por `@source` desde la aplicación) y que hoy confunden a quien busca dónde se configura el tema:

- `tailwind.config.js`, con un `colors: {}` vacío que no configura nada.
- `resources/css/app.css`, con `@tailwind base/components/utilities`.

### Claro y oscuro

Cinco parejas fijas, decididas una vez y repetidas en todas las vistas:

| Elemento | Clase |
|---|---|
| Tarjeta | `bg-white dark:bg-zinc-900` |
| Borde | `border-zinc-200 dark:border-zinc-700` |
| Texto principal | `text-zinc-900 dark:text-white` |
| Texto secundario | `text-zinc-500 dark:text-zinc-400` |
| Fondo de página | `bg-zinc-50 dark:bg-zinc-950` |

El selector va en el menú de usuario, con el mecanismo de Flux (`localStorage`, sin backend), junto al de idioma.

### Limpieza

**16 vistas muertas, borradas:**

- `livewire/pages/auth/` (6 ficheros): duplicado completo de login, registro, recuperación, confirmación, restablecimiento y verificación. Los que se usan son los de `livewire/auth/`.
- `livewire/layout/navigation.blade.php`, `livewire/welcome/navigation.blade.php`, `layouts/navigation-mobile.blade.php`, `layouts/header.blade.php`, `layouts/language-selector.blade.php`.
- Componentes sin uso: `confirmation-modal`, `dialog-modal`, `locale-switcher`, `mini-chart`, `ui-kit`.

**Componentes anónimos sustituidos por Flux.** Al pasar los formularios a Flux, se quedan sin uso:

| Componente propio | Usos | Sustituto |
|---|---|---|
| `input-label` | 82 | `flux:field` + `flux:label` |
| `input-error` | 77 | `flux:error` |
| `primary-button` | 72 | `flux:button variant="primary"` |
| `text-input` | 57 | `flux:input` |
| `secondary-button` | 20 | `flux:button` |
| `dropdown-link` | 14 | `flux:menu.item` |
| `navigation-menu-item` | 13 | `flux:navlist.item` |
| `responsive-nav-link` | 8 | `flux:navlist.item` |
| `modal` | 8 | `flux:modal` |
| `nav-link` | 4 | `flux:navlist.item` |
| `livewire-modal` | 4 | `flux:modal` |
| `danger-button` | 4 | `flux:button variant="danger"` |
| `auth-session-status` | 4 | `flux:callout` |
| `action-message` | 4 | `flux:toast` |
| `icon` | 2 | `flux:icon` |
| `dropdown` | 2 | `flux:dropdown` |
| `avatar` | 1 | `flux:avatar` |

A esos se suman tres que ya no usa nadie hoy (`button`, `label`, `input`) y los cinco de la lista de vistas muertas.

Sobreviven dos: **`application-logo`**, que es marca propia, y **`menu-tree`**, un árbol ordenable que Flux no cubre. De 27 componentes anónimos quedamos en 2.

En total se borran **36 ficheros**: 11 vistas muertas y 25 componentes, cinco de los cuales están además en la lista de muertos.

---

## Sección 2 — Patrón de tabla

Construido con `flux:table` escrito directamente en cada vista. Lo compartido es el trait `InteractsWithTable`.

### A qué pantallas se aplica

De las 8 pantallas de gestión, solo cuatro son hoy tablas de verdad: **usuarios, cuentas, invitaciones y log de actividad**. A ellas se suma **features**, que hoy es una lista con interruptores y encaja mejor como tabla en cuanto una aplicación registra unas cuantas. Esas cinco adoptan el patrón completo.

Las otras tres conservan su forma, porque no son listados:

- **Roles** es una matriz de permisos: adopta la cabecera, los estados vacíos y el vocabulario visual, no la barra de filtros.
- **Navegación** es un árbol reordenable: igual que roles.
- **Ajustes de cuenta** es un formulario, y se rige por la sección 3.

### El trait

Aporta a las cinco pantallas tabulares, todo con `#[Url]` para que sobreviva al refresco y se pueda compartir por enlace:

- `$search`, con reseteo de página al cambiar.
- `$sortBy` y `$sortDirection`, con el método que alterna dirección al pulsar la misma columna.
- `$perPage`, con opciones 10 / 25 / 50 / 100.
- El registro de filtros activos y el `resetFilters()` que los limpia todos.

El trait no toca la consulta: cada pantalla sigue siendo dueña de su `query()`, y decide qué columnas son ordenables y sobre qué campos busca.

### Barra de herramientas

Buscador a la izquierda (`flux:input` con icono y botón de limpiar), botón **Filtros** con contador de filtros activos que abre un panel, y selector de filas por página. Los filtros aplicados se muestran debajo como etiquetas quitables una a una, con un enlace de limpiar todo.

La barra no crece con el número de filtros: la misma disposición sirve para Usuarios (dos filtros) y para el Log de actividad (usuario, acción y rango de fechas). Es también donde entra la mejora de Pro: `flux:date-picker` para el rango de fechas y `flux:select variant="combobox"` cuando un filtro pasa de ~10 opciones, ambos bajo `@if(Flux::pro())` con el equivalente gratuito como alternativa.

### Tabla

- Cabecera de página **dentro del componente**: título, descripción y acción principal, fuera de la tarjeta. Se apaga con `:heading="false"`.
- Columnas ordenables con `<flux:table.column sortable>`.
- Acciones de fila en un menú `flux:dropdown` de tres puntos, en lugar de los enlaces de texto sueltos actuales, que descuadran la fila y no caben en móvil.
- Paginación nativa de Flux (`:paginate`).
- El desplazamiento horizontal en pantallas estrechas lo resuelve el propio `flux:table`; no se construye una vista de tarjetas alternativa.

### Estados vacíos

Tres mensajes distintos para tres situaciones distintas:

1. **No hay nada todavía** — con la acción de crear.
2. **La búsqueda no encuentra nada** — con el botón de limpiar filtros.
3. **No tienes permiso para ver esto** — sin acción.

Hoy todas las pantallas de gestión dicen alguna variante de «no se encontraron resultados», sin distinguir el caso.

---

## Sección 3 — Patrón de formulario

### Disposición

Secciones apiladas a dos columnas: a la izquierda el título del bloque y una descripción de qué hace y qué implica («Contraseña — al cambiarla se le pedirá entrar de nuevo»); a la derecha los campos, con ancho de lectura acotado. En pantallas estrechas la columna izquierda pasa arriba.

Se aplica a las pantallas largas del paquete: `edit-user`, `edit-account`, perfil, preferencias y ajustes de cuenta. Esa columna de descripción es el único sitio donde caben las explicaciones que hoy no existen en ninguna pantalla.

`flux:tabs` es de pago, así que las pestañas quedan descartadas: lo que Pro aporta en formularios son campos concretos, no otra disposición.

### Reglas

- Cada campo es un `flux:field` con etiqueta, descripción, error y control en un solo bloque. Desaparecen los `<select>` a pelo con clases inventadas.
- **Un formulario por bloque**, cada uno con su botón de guardar. Se termina el markup actual de `edit-user`, donde las etiquetas `<form>` se abren dentro de un `@if` y se cierran en otro según sea alta o edición: alta y edición dejan de compartir vista.
- Guardar muestra estado (`wire:loading` en el botón) y confirma con toast.
- Sin permiso, el campo se deshabilita y se explica por qué; no se esconde.

---

## Sección 4 — Shell

El layout actual son unas 200 líneas escritas a mano: sidebar con Alpine (plegado con `localStorage`, cajón móvil con backdrop y transiciones declaradas una a una), cabecera, y banner de suplantación.

Pasa a construirse con `flux:sidebar` + `flux:navlist` + `flux:header` + `flux:main`, todos gratuitos, que resuelven lo mismo con modo oscuro y accesibilidad de teclado incluidos. Se borra la fontanería de Alpine.

**No cambia de dónde salen los menús**: el `MenuManager` sigue siendo la fuente, con sus permisos y sus resolvedores de badge (el contador de invitaciones pendientes ya existe). La vista solo mapea cada entrada a un `flux:navlist.item`. `navigation-items`, `navigation-master-data` y la ya muerta `navigation-mobile` se reducen a una sola vista.

La barra superior —hoy vacía, porque ninguna vista usa su slot `$header`— pasa a llevar **breadcrumbs** a la izquierda, coherente con la decisión de que el título vive dentro del componente, y a la derecha el conmutador de cuenta, las notificaciones, el selector de tema y el menú de usuario con `flux:profile`.

El banner de suplantación pasa a `flux:callout` en variante *warning*. Es el primer sitio donde se nota la escala nueva: hoy usa `bg-warning-100`, que no existe, y se ve un banner sin fondo.

El layout `guest` (login, registro, recuperación) va aparte: tarjeta centrada, marca arriba, mismo tratamiento de claro y oscuro.

---

## Sección 5 — Feedback, estados y garantías

**Confirmaciones.** Hoy están partidas: 3 vistas usan `wire:confirm` (el diálogo nativo del navegador, sin traducir y sin estilar) y 3 usan `flux:modal`. Se unifica en `flux:modal` para toda acción destructiva, nombrando el objeto afectado y con el botón en variante de peligro. `wire:confirm` desaparece del paquete.

**Confirmación de éxito.** Los 19 `Flux::toast` existentes se convierten en regla: toda mutación que sale bien avisa por toast. Los errores de validación van siempre junto al campo, nunca en toast.

**Estados de carga.** `wire:loading` sobre el cuerpo de la tabla, con `flux:skeleton` en la primera carga y atenuado en las siguientes. Los botones de guardar se deshabilitan y muestran progreso mientras dura la petición.

**Badges de estado.** Criterio único en todo el paquete, apoyado en la escala nueva:

| Situación | Variante |
|---|---|
| Activo, verificado | `success` |
| Pendiente, por caducar | `warning` |
| Revocado, caducado, suspendido | `danger` |
| Informativo (rol global, cuenta de sistema) | `info` |

**Traducciones.** Todo texto nuevo va con su clave en `en` y `es`, sin excepción.

### Tests

Tres guardas, todas baratas, contra las tres formas en que esto se ha podrido:

1. **Compilación de vistas** (`ViewCompilationTest`, ya existe): falla si una vista referencia un componente que ningún proveedor resuelve.
2. **Tokens de color**: recorre las vistas y falla si aparece una clase de una escala que el tema no define. Es exactamente el fallo que motivó este rediseño.
3. **Claves de traducción**: recorre las claves `base-tenant::` usadas y falla si falta la entrada en inglés o en español. El mismo chequeo que, hecho a mano, encontró el fichero `activity.php` sin traducir.

---

## Alcance

**Entra:** las 60 vistas vivas, el fichero de tema, el instalador (el `@import` sustituye al bloque de alias), el trait `InteractsWithTable`, el borrado de los 36 ficheros muertos o sustituidos, y los tres tests.

**No entra:**

- La lógica de negocio de los componentes Livewire: consultas, políticas y permisos se tocan solo donde el patrón lo exija (por ejemplo, añadir orden a una consulta).
- Las pantallas de suscripción y facturación de Cashier, que renderizan contra Stripe.
- Rediseñar el dashboard con contenido nuevo: se adapta al sistema, no se le inventan métricas.
- Documentar el sistema para la aplicación anfitriona más allá de actualizar `docs/FRONTEND.md`, que hoy describe un flujo de Tailwind v3 y Laravel Mix que ya no existe.

## Riesgos

- **Es un cambio visual generalizado.** Cualquier aplicación instalada verá cambiar todas las pantallas del paquete. No hay ruta de migración gradual: el vocabulario de color se sustituye de una vez.
- **Las aplicaciones ya escafoldadas no se resincronizan.** Poseen su copia del código; recibirán el rediseño reinstalando o aplicando los cambios a mano. Es la misma limitación que se documentó con los arreglos del 2026-08-01.
- **La superficie es grande.** 60 vistas en un solo sistema significa que un error en las cinco parejas de color o en la barra de herramientas se multiplica por 60. Los tests de tokens y de compilación acotan justo eso.

## Criterios de aceptación

1. Ninguna vista usa una clase de color de una escala que el tema no defina, y el test lo comprueba.
2. Las cinco pantallas tabulares (usuarios, cuentas, invitaciones, actividad y features) buscan, ordenan por columna, filtran y paginan, con el estado en la URL. Roles y navegación conservan su forma y adoptan el vocabulario visual.
3. Todas las pantallas se ven correctas en claro y en oscuro, con selector en el menú de usuario.
4. Los formularios usan `flux:field`; no queda ningún `input-label`, `text-input` ni `input-error`.
5. No queda ningún `wire:confirm`: las acciones destructivas confirman con `flux:modal`.
6. Los 36 ficheros muertos o sustituidos están borrados, y ninguna vista los referencia.
7. La suite pasa entera, incluidos los tres tests de garantía.

---

## Reconciliación con lo construido

> Escrita el 2026-08-03, al cerrar el rediseño. **Nada de lo anterior se ha reescrito**: el texto de arriba es lo que se creía el 2026-08-02, y el hueco entre aquello y lo que apareció al construirlo es la parte útil. Sólo se han marcado dos filas de la tabla de decisiones, porque una tabla que afirma algo que el código contradice se lee como norma vigente, no como historia.
>
> Las cifras de esta sección están medidas sobre el árbol, no estimadas.

### Los siete criterios de aceptación

| # | Criterio | Veredicto |
|---|---|---|
| 1 | Ninguna vista usa una clase de una escala que el tema no defina, y el test lo comprueba | **Cumplido**, con la corrección R-1: para cumplirlo hubo que definir también la escala `accent` |
| 2 | Las cinco pantallas tabulares buscan, ordenan, filtran y paginan con el estado en la URL; roles y navegación conservan su forma | **Cumplido**. `InteractsWithTable` publica cuatro propiedades con `#[Url]` (`q`, `sortBy`, `sortDirection`, `perPage`) |
| 3 | Todas las pantallas se ven correctas en claro y en oscuro, con selector en el menú de usuario | **Cumplido con matiz**, ver R-4 |
| 4 | Los formularios usan `flux:field`; no queda ningún `input-label`, `text-input` ni `input-error` | **Cumplido**, y ver R-5: tal como estaba escrito, se podía cumplir a la vez que se perdían mensajes de validación |
| 5 | No queda ningún `wire:confirm` | **Cumplido**, con guarda: `ConfirmationTest` falla listando cualquier vista que lo reintroduzca |
| 6 | Los **36** ficheros muertos o sustituidos están borrados | **Cumplido, cifra equivocada**: son **42**, ver R-3 |
| 7 | La suite pasa entera, incluidos los tres tests de garantía | **Cumplido**: 392 pruebas en verde. Las guardas ya no son tres, ver R-9 |

### R-1 — Flux no define `accent`

La sección 1 dice que `accent-*` es «lo que Flux ya garantiza». No lo es. `vendor/livewire/flux/dist/flux.css` declara **tres tokens planos** —`--color-accent`, `--color-accent-content` y `--color-accent-foreground`— y **ningún tono numérico**. Las vistas usaban `bg-accent-600`, `ring-accent-500` y compañía, así que la aplicación se estaba sirviendo sin color de acción principal: el mismo fallo que motivó el rediseño, en la escala que el spec daba por resuelta.

`resources/css/base-tenant.css` define ahora la escala `accent` completa (50→950) mapeada a **violeta**, la que Flux usa por defecto. El token plano `--color-accent` se deja a Flux a propósito: no es un tono, cambia de valor entre claro y oscuro, y ninguna vista lo usa.

La decisión se tomó a mitad del plan 1, al descubrir que la interfaz publicada no tenía color primario.

### R-2 — `@if(Flux::pro())` no puede guardar una etiqueta de Pro

La sección 2 dice que el `flux:date-picker` y el `flux:select variant="combobox"` van «ambos bajo `@if(Flux::pro())` con el equivalente gratuito como alternativa». Eso no funciona: **Blade resuelve las etiquetas de componente al compilar, no al pintar**, así que un `<flux:date-picker>` dentro de un `@if` falso rompe igual la vista entera —y rompe el `view:cache` de cualquier instalación sin Pro.

`Flux::pro()` sí se puede llamar sin el paquete (es sólo `isInstalled('livewire/flux-pro')`, devuelve `false`). Lo que hay que cambiar es la etiqueta: en `activity-log.blade.php` la mejora de Pro se monta con `<x-dynamic-component component="flux:date-picker" …>`, que se resuelve en tiempo de ejecución.

Regla general derivada, que vale para todo el paquete: **nunca un `@if … @endif` dentro de la lista de atributos de un componente Flux.** El compilador de etiquetas se traga la directiva interna y deja la externa descuadrada (`syntax error, unexpected token "else"`). Se usan atributos vinculados (`:disabled="$x"`); un `ComponentAttributeBag` descarta `null` y `false`.

### R-3 — Ficheros borrados: 42, no 36

Medido sobre el rango completo del rediseño:

| Grupo | Previsto | Real |
|---|---|---|
| Componentes anónimos | 25 | **26** |
| Vistas muertas | 11 | **14** |
| Reliquias de Tailwind v3 | (aparte) | 2 |
| **Total** | **36** | **42** |

Las tres vistas muertas de más son `layouts/navigation.blade.php`, `layouts/navigation-dropdown.blade.php` y `layouts/navigation-master-data.blade.php`. No aparecían en el recuento porque la guarda de huérfanas buscaba el nombre corto **como subcadena**: `layouts.navigation` casaba dentro del `layouts.navigation-items` de otro fichero y lo daba por vivo. La guarda se corrigió para exigir que la referencia termine donde termina el nombre, y entonces salieron.

El componente de más es **`menu-tree`**, y con él cae la promesa del spec de que sobrevivirían dos:

> «Sobreviven dos: `application-logo` … y `menu-tree`, un árbol ordenable que Flux no cubre. De 27 componentes anónimos quedamos en 2.»

`menu-tree` no era el árbol ordenable del editor de menús: era el **renderizador de la barra lateral**, y el editor tiene su propio marcado. Se borró en el plan 2 al reconstruir el shell. **De 27 componentes anónimos queda 1**: `application-logo`.

### R-4 — Qué garantiza realmente el modo oscuro

El criterio 3 dice «se ven correctas». Lo que hay es una garantía **estática**, no visual: `DarkModeTest` recorre todas las vistas y exige que cada superficie clara lleve su pareja oscura **dentro del mismo ámbito de clases** —el literal de un `class="…"`, cada rama entrecomillada de un ternario por separado, y el `'class' => '…'` de un `merge()`. Una comprobación por fichero habría dado por buena una vista con un elemento emparejado y el de al lado no.

Hay una excepción razonada por línea: el `bg-white` que envuelve el SVG del código QR en `two-factor-authentication`. El QR es negro sobre transparente; oscurecer ese fondo lo deja sin contraste y sin poder escanearse.

### R-5 — El criterio 4, tal como estaba escrito, se podía cumplir perdiendo errores

«Los formularios usan `flux:field`» sugiere que basta con pasar a Flux. No basta. Un control de Flux **con** `label` monta su propio `flux:field`, y con él la etiqueta **y el hueco del error**. Un control **sin** etiqueta no lo monta: el mensaje de validación no se pinta en ninguna parte, el formulario rechaza el envío y no dice por qué.

Apareció dos veces, las dos con marcado que cumplía el criterio al pie de la letra:

- el grupo de casillas de roles de `edit-user`, que dejaba mudo el `selectedRoles` requerido;
- los campos booleanos de `account-settings`.

Ambos llevan ahora un `<flux:error name="…" />` explícito. La regla está en `docs/FRONTEND.md`, y cada formulario tiene un barrido de pruebas que afirma `data-flux-error` **y** el texto del mensaje en el HTML devuelto.

### R-6 — El estado vacío de «no tienes permiso» es inalcanzable

La sección 2 pide tres estados vacíos distintos, el tercero «No tienes permiso para ver esto — sin acción». **No se construyó, y no se podía construir**: las ocho pantallas de gestión autorizan en `mount()` y lanzan antes de pintar nada.

| Pantalla | Guardia en `mount()` |
|---|---|
| `UserManager`, `AccountManager`, `InvitationManager`, `RoleManager` | `$this->authorize('viewAny', …)` |
| `ActivityLog`, `FeatureManager`, `NavigationManager`, `AccountSettings` | `abort_unless(…hasPermission(…), 403)` |

Quien no tiene permiso recibe un 403, no una pantalla. Los otros dos estados vacíos —«no hay nada todavía» y «la búsqueda no encuentra nada»— sí existen y están cubiertos por pruebas. Si se quiere el tercero, hay que decidir antes que estas pantallas se rendericen sin permiso, que es un cambio de comportamiento, no de maquetación.

### R-7 — Las mejoras de Pro están escritas pero no verificadas

`vendor/livewire/` contiene `flux` y no `flux-pro`. Todo lo que se ejercita en la suite es la **alternativa gratuita**: el par de `flux:input type="date"` del rango de fechas y el `flux:select` nativo. La rama de Pro está escrita y compila —por `x-dynamic-component`, ver R-2— pero **ninguna prueba la ha ejecutado nunca**. Cuando alguien instale Pro, esa rama se estrena en producción.

### R-8 — Promesas de las secciones 4 y 5 que no se construyeron

- **Breadcrumbs.** La sección 4 dice que la barra superior «pasa a llevar breadcrumbs a la izquierda». No hay ninguno: cero referencias en `resources/views` y en `src`. La barra lleva el conmutador de cuenta, las notificaciones, el selector de tema y el menú de usuario; el título vive dentro del componente, como decidía la fila 4 de la tabla.
- **`flux:skeleton`.** La sección 5 lo pide «en la primera carga» de las tablas. No se usa en ninguna vista. El estado de carga que sí existe es el `wire:loading` de los botones de guardar, en 20 vistas, con su `wire:target` apuntando al método que envía.
- **«Sin permiso, el campo se deshabilita y se explica por qué»** (sección 3) se cumple donde es alcanzable: el selector de cuenta del alta de usuario sale deshabilitado y relleno con la cuenta en contexto en lugar de esconderse, y los campos de ajustes se deshabilitan sin `settings.update`. En el gestor de roles el equivalente **no es alcanzable**: `edit()` autoriza antes de dejar abrir nada, así que la rama `@cannot` que la vista conserva es una red de seguridad, no un camino.

### R-9 — Las guardas ya no son tres

El spec preveía tres. La suite tiene hoy 392 pruebas, y las guardas de sistema son siete: compilación de vistas, tokens de color, claves de traducción, parejas claro/oscuro, vistas huérfanas, ausencia de `wire:confirm`, y el equilibrio de las etiquetas `<form>` en las pantallas de formulario.

Dos avisos para quien las lea:

- **`ViewCompilationTest` compila las vistas pero no las evalúa.** Verde no demuestra que una vista pinte; sólo que sus etiquetas se resuelven. Lo que lo demuestra es una prueba de render, y ésas necesitan `$this->withoutVite()` porque el paquete no compila assets para las pruebas.
- **`OrphanViewsTest` cuenta los comentarios como referencias.** Busca sobre el texto del fichero sin distinguir código de comentario, así que un componente mencionado en un comentario parece vivo. Pasó con `input-label`, que estuvo a cero usos reales mientras la guarda lo daba por referenciado desde un comentario del proveedor de servicios. **Para contar usos, la guarda no sirve**: hay que buscar por patrón, y con el límite puesto (`x-base-tenant::input(?![a-zA-Z0-9_-])`), porque `\b` no separa `input` de `input-label`.

### R-10 — Fallos anteriores al rediseño que aparecieron al recorrer las pantallas

Ninguno lo causó este trabajo; todos se verificaron contra el árbol previo antes de tocar nada.

| Hallazgo | Estado |
|---|---|
| `VerifyEmail` firmaba el enlace contra la ruta `verification.verify`, que el paquete registra como `base-tenant.verification.verify`. Cualquier usuario sin verificar se comía un **500** al abrir la pantalla, que es donde se manda el correo | **Corregido**: `VerifyEmailNotification::createUrlUsing()` en el proveedor, con prueba del enlace que la ruta acepta y del que va sin firma |
| `TwoFactorChallenge` no declaraba `#[Layout]`, así que Livewire caía en el layout de la aplicación, que el paquete no trae. `GET /two-factor-challenge` respondía **500**: quien tuviera segundo factor activo se quedaba sin poder entrar | **Corregido**: `#[Layout('base-tenant::layouts.guest')]`, con prueba por HTTP |
| `Base\Tenant\Models\User` **no implementa `MustVerifyEmail`**, sólo usa su trait. El middleware `verified` —que `auth_middleware` trae por defecto— es un no-op, `Registered` no manda nada, y el aviso de correo sin verificar del perfil es inalcanzable. Añadir el contrato hoy daría un 500 nuevo, porque `EnsureEmailIsVerified` redirige a `verification.notice`, otro nombre sin registrar | **Reportado, no tocado**: es una decisión de producto sobre el registro en toda aplicación anfitriona, y necesita tres cambios coordinados más un interruptor de configuración |
| El `Gate::before` del proveedor concede todo a `isSuperAdmin()` **antes** de que corra la política, así que el `if ($role->is_system) return false;` de `RolePolicy::delete()` no aplica a un administrador del sistema: puede borrar un rol de sistema | **Reportado, no tocado**: puede ser lo pretendido, pero la línea se lee como norma dura y no lo es |
| `resources/views/livewire/notifications/index.blade.php` tiene todavía cadenas en inglés escritas a pelo fuera de la barra de acciones en bloque | **Pendiente** |
