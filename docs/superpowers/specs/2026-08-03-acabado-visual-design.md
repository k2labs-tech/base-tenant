# Acabado visual — sistema de diseño

> Segundo rediseño. El primero (2026-08-02) resolvió coherencia y garantías: un vocabulario de color, un patrón de tabla repetible, modo oscuro y seis guardas en la suite. No definió en ningún sitio qué es un buen acabado, así que una pantalla correcta y sosa cumplía sus siete criterios de aceptación. Este documento define eso.

## El diagnóstico, mirando las pantallas construidas

No es que estén mal: es que están **planas**. Concretamente, sobre la tabla de usuarios y el panel:

- **Una sola capa.** Fondo, tarjeta y fila comparten peso visual. Nada indica qué contiene a qué.
- **Tipografía monótona.** Título, cabecera de columna, dato y metadato usan casi el mismo tamaño y el mismo peso. No hay jerarquía que leer.
- **Filas vacías de información.** Una fila de usuario usa cuatro columnas para nombre, email, rol y un menú. Cabe el doble de información en el mismo alto sin apretar nada.
- **Espacio desaprovechado.** Una tabla de una fila deja 700 px de vacío por debajo. El contenido no ocupa la pantalla ni la estructura la organiza.
- **Sin chrome de página.** No hay migas, ni recuento, ni resumen. Se entra a la pantalla sin saber dónde se está ni cuánto hay.
- **Barra de herramientas sin gramática.** Tres controles alineados sin agrupar: no se distingue lo que filtra de lo que actúa.
- **Estados sin diseñar.** Vacíos, carga y error son una frase centrada.

Todo eso es consecuencia de una decisión correcta llevada demasiado lejos: apoyarse en los componentes de Flux tal cual, sin componer nada por encima. Flux da piezas bien hechas; la composición sigue siendo trabajo nuestro.

## Principio rector

**Neutro no es soso.** El grado de elaboración no está en el color —la paleta sigue siendo zinc más un acento— sino en **jerarquía, densidad de información, capas y detalle de estado**. Una aplicación empresarial se ve seria cuando cada pantalla responde a tres preguntas antes de que el usuario pregunte: dónde estoy, cuánto hay, y qué puedo hacer.

---

## 1. Escala tipográfica

Cinco roles, y solo cinco. Cualquier texto de la aplicación es uno de estos:

| Rol | Clases | Uso |
|---|---|---|
| Título de página | `text-xl font-semibold tracking-tight` | Uno por pantalla |
| Título de sección | `text-sm font-semibold` | Cabecera de bloque, de tarjeta |
| Etiqueta | `text-xs font-medium uppercase tracking-wider text-zinc-500` | Cabeceras de columna, etiquetas de campo |
| Dato | `text-sm text-zinc-900 dark:text-white` | El contenido |
| Metadato | `text-xs text-zinc-500 dark:text-zinc-400` | Lo secundario, bajo el dato |

Los números en tablas llevan `tabular-nums`: sin eso, una columna de importes no alinea y se ve amateur a primera vista aunque nadie sepa decir por qué.

## 2. Capas de superficie

Cuatro niveles, con una regla: **cada nivel se separa del anterior por borde, no por sombra**. Las sombras se reservan para lo que flota de verdad (menús, modales, toasts).

| Nivel | Claro | Oscuro |
|---|---|---|
| Fondo de página | `bg-zinc-50` | `dark:bg-zinc-950` |
| Panel | `bg-white` + `border-zinc-200` | `dark:bg-zinc-900` + `dark:border-zinc-800` |
| Cabecera de panel / pie | `bg-zinc-50/60` | `dark:bg-zinc-900/40` |
| Fila al pasar el ratón | `bg-zinc-50` | `dark:bg-zinc-800/50` |

## 3. Anatomía de la página

Toda pantalla de gestión tiene la misma estructura, de arriba abajo:

1. **Migas** — `flux:breadcrumbs`, en la barra superior. Prometidas en el primer rediseño y nunca construidas.
2. **Cabecera de página** — título, una línea de descripción, y a la derecha las acciones. Junto al título, **el recuento**: «Usuarios · 47». Un número en la cabecera cambia por completo la sensación de estar ante una herramienta y no ante una maqueta.
3. **Tira de resumen** *(solo donde aporta)* — dos a cuatro cifras en una fila: total, pendientes, activos este mes. No es un dashboard: es una línea de contexto.
4. **Panel de contenido** — barra de herramientas, tabla, pie con paginación.

## 4. Anatomía de la tabla

Lo que la separa de la actual:

- **Cabecera fija** al hacer scroll, con fondo de nivel 3 y borde inferior.
- **Celda primaria compuesta**: avatar + nombre en «dato» + email en «metadato» debajo. Una columna menos y más información.
- **Divisores solo horizontales**, `border-zinc-100 dark:border-zinc-800`. Las rejillas completas envejecen mal.
- **Fila con estado**: hover de nivel 4, y un borde izquierdo de 2 px en `accent` cuando la fila está seleccionada.
- **Columna de estado** con punto de color y texto, no un badge suelto: el punto se lee de un vistazo en una lista larga.
- **Acciones**: la principal visible al pasar el ratón, el resto en el menú de tres puntos. Hoy todo está escondido.
- **Alto de fila**: 52 px en cómodo, 40 px en compacto. Con conmutador de densidad, que es de las cosas que más se agradecen en uso real.
- **Pie**: «Mostrando 1–25 de 47» a la izquierda, paginación a la derecha, ambos en «metadato».

## 5. Barra de herramientas, con gramática

Tres zonas, siempre en el mismo orden: **buscar** (izquierda, crece), **filtrar** (centro, chips), **ver** (derecha: densidad, columnas, tamaño de página). Lo que filtra nunca se mezcla con lo que actúa; las acciones viven en la cabecera de página.

Los filtros aplicados se muestran como chips quitables bajo la barra, y el botón de filtros lleva el recuento. Eso ya existe; lo que falta es la separación visual de las tres zonas con un divisor sutil.

## 6. Estados, diseñados

- **Vacío inicial**: icono en círculo de nivel 3, título en «título de sección», una línea de metadato y la acción primaria. No una frase suelta.
- **Vacío por búsqueda**: distinto del anterior, con el término buscado citado y el botón de limpiar.
- **Carga**: `flux:skeleton` con la forma de tres filas. Prometido en el primer rediseño y nunca construido.
- **Error**: `flux:callout` en `danger` con la acción de reintentar.

## 7. Detalle fino

Lo que separa «correcto» de «cuidado», y que hoy no está:

- Transición de 150 ms en hover de fila y de botón.
- Anillo de foco visible y consistente (`focus-visible:ring-2 ring-accent-500 ring-offset-2`).
- Estados deshabilitados con opacidad y cursor, nunca elementos ocultos.
- Iconos siempre a 16 px en tablas y menús, alineados ópticamente con el texto.
- Avatares con iniciales sobre color derivado del nombre, no todos del mismo gris.

---

## Alcance

**Entra:** las cinco pantallas tabulares, la cabecera de página de las ocho de gestión, migas en el shell, los estados vacíos y de carga, y el detalle fino. La tabla de usuarios es la implementación de referencia, como en el primer rediseño.

**No entra:** cambiar la paleta, el modo oscuro (ya resuelto), los formularios a dos columnas (ya resueltos), y el contenido del panel, que hoy es maqueta con datos inventados y merece su propio encargo.

## Criterios de aceptación

A diferencia del primer spec, estos son visuales y se verifican mirando, no ejecutando:

1. Una pantalla de gestión responde, sin scroll, a: dónde estoy, cuánto hay, qué puedo hacer.
2. En una fila de tabla se distinguen tres niveles tipográficos sin medir nada.
3. Las cuatro capas de superficie son distinguibles en claro y en oscuro.
4. Los tres estados vacíos son distintos entre sí y ninguno es una frase centrada.
5. La cabecera de la tabla queda fija al hacer scroll con 50 filas.
6. Existe conmutador de densidad y el estado se conserva al recargar.
7. Ningún control cambia de sitio entre pantallas: la gramática de la barra es la misma en las cinco.

---

## Reconciliación con lo construido

Escrito al cerrar, el 2026-08-03. El texto de arriba queda como estaba: es el registro de lo que se decidió antes de construir, y la distancia con lo que salió es la parte útil.

### Los siete criterios

| # | Criterio | Estado |
|---|---|---|
| 1 | Responde sin scroll a dónde estoy, cuánto hay, qué puedo hacer | **Cumplido.** Migas, recuento junto al título y acción principal a la derecha, en las seis pantallas |
| 2 | Tres niveles tipográficos en una fila | **Cumplido.** Nombre como dato, email como metadato, cabecera como etiqueta |
| 3 | Cuatro capas distinguibles en claro y oscuro | **Cumplido**, con `DarkModeTest` exigiendo la pareja dentro del mismo atributo |
| 4 | Tres estados vacíos distintos | **Cumplido a medias, y no por descuido.** Ver abajo |
| 5 | Cabecera fija con 50 filas | **Cumplido** (`sticky top-0` con `backdrop-blur`) |
| 6 | Conmutador de densidad que sobrevive al refresco | **Cumplido**, con `#[Url]` y lista blanca |
| 7 | Misma gramática de barra en las cinco | **Cumplido con dos excepciones declaradas.** Ver abajo |

### R-1 · El tercer estado vacío es inalcanzable

El spec pedía tres: nada todavía, la búsqueda no encuentra nada, y no tienes permiso. Los dos primeros están. **El tercero no puede existir**: los componentes autorizan en `mount()` y lanzan un 403 antes de renderizar, así que la vista nunca llega a pintarse sin permiso. Construirlo exigiría dejar entrar a quien no debe para enseñarle una pantalla vacía, que es peor comportamiento. Se deja fuera a propósito.

Un cuarto caso que el spec no previó y sí apareció: **la página desbordada**. Con una fila y `?page=5` la tabla anunciaba «aquí no hay nada todavía» sobre datos que sí existen. Se resuelve mirando el total y no la página, con un ayudante en el trait y un test de contrato que lo exige en las cinco pantallas.

### R-2 · Dos excepciones a la gramática de la barra

- **Features no tiene selector de tamaño de página**, porque no pagina: el catálogo lo acota el plan. Un control que no gobierna nada es peor que su ausencia, y hay un test que exige que siga sin estar.
- **Notificaciones no tiene zona de «ver»**, porque no tiene densidad ni paginación configurable. Sus filtros van tras el mismo botón de embudo que el resto, que es lo que sostiene la coherencia.

### R-3 · Lo que se construyó sin estar en el spec

- **Migas** y **esqueleto de carga**: estaban prometidos en el spec *anterior* y nunca se construyeron. Se cierran aquí.
- **Columnas condicionales**: la de caducidad en features solo aparece si alguna fila tiene algo que poner. Una columna entera de guiones ocupa ancho sin informar.
- **Notificaciones** entró en el alcance al final: estaba traducida pero fuera del sistema visual, y era la única pantalla que desentonaba.

### R-4 · Un bug de fondo que el rediseño destapó

`UserManager` consultaba la clase `User` **del paquete** en lugar del modelo configurado. Los roles se guardan con el `model_type` del modelo de la aplicación, así que el morph no casaba y **la columna de roles salía vacía en toda instalación real**. Invisible para la suite, porque en los tests del paquete el modelo configurado *es* el del paquete. Cubierto ahora con un fixture que simula el modelo anfitrión.

Es el argumento más fuerte a favor de mirar la aplicación en marcha: 400 tests en verde convivían con una columna que nunca mostró nada.

### R-5 · Lo que sigue abierto, y por qué

- **`MustVerifyEmail`**: el paquete tiene rutas, pantalla y middleware de verificación, pero su modelo no implementa el contrato. Activarlo son tres cambios coordinados más un interruptor de configuración; hacer solo el primero cambia un 500 por otro. Es decisión de producto.
- **Sin integración continua**: los 428 tests solo se ejecutan cuando alguien los lanza. No hay `.github/workflows`.
