# Especificación de producto — base/tenant

> **Qué es este documento.** La fuente de verdad de todo lo que el producto hace
> hoy y de lo que está construyéndose, escrita para que la landing de venta pueda
> montarse sin leer el código. Cada capacidad trae el dolor que resuelve, la
> prueba técnica de que está resuelto, y su estado real.
>
> **Documento hermano:** [`WEBSITE-SPEC.md`](WEBSITE-SPEC.md) define la
> *estructura* del sitio (páginas, jerarquía, tono). Este define el *contenido*:
> qué se cuenta y con qué evidencia. No se solapan.
>
> Fecha: 1 septiembre 2026 · Versión del paquete: v2 (v3 en construcción)

---

## 0. Cómo leer este documento

Cada capacidad se describe con cuatro campos fijos, para que quien escriba la
landing pueda coger el que necesite sin interpretar nada:

| Campo | Para qué sirve |
|---|---|
| **Qué es** | La frase de una línea. Sirve tal cual para una tarjeta o una fila de tabla |
| **El dolor** | El problema concreto del comprador. Es el material de los titulares y del cuerpo de texto |
| **La prueba** | Código, cifra o mecanismo verificable. Es lo que convierte a un desarrollador escéptico |
| **Estado** | `Disponible` · `En construcción` · `Planificado`. **No se anuncia como disponible nada que no lo esté** |

**La regla que no se rompe:** la credibilidad de este producto viene de su
honestidad. Hay una sección §11 que lista lo que no está construido, lo que no
está verificado contra un servicio real y lo que está deliberadamente fuera de
alcance. Esa sección va enlazada desde la portada, no escondida en un FAQ. Si el
marketing contradice a la documentación, el primer desarrollador que lo note
descontará todo lo demás.

---

## 1. El producto en una frase

**La base multi-tenant para SaaS B2B en Laravel: aislamiento que aguanta donde
suele romperse, permisos por cuenta, límites de plan que de verdad se aplican, y
catorce módulos de producto encima.**

Variante corta, para el `<title>` y el hero:

> Todo lo de tu SaaS que no es tu producto, ya construido.

Variante larga, para el meta description y el primer párrafo:

> `base/tenant` es un paquete para Laravel 13 que resuelve la capa que hay
> debajo de todo SaaS B2B: cada cliente es una cuenta, nada se filtra entre
> ellas, cada cuenta tiene sus roles, sus límites y su configuración. Se instala
> como dependencia y, el día que quieras, te quedas el código y lo desinstalas.

---

## 2. A quién se le vende

**El comprador principal.** Un desarrollador Laravel senior o un fundador
técnico que va a construir un SaaS B2B y acaba de darse cuenta de cuánto de eso
no es su producto. Lleva tres días escribiendo el scoping por tenant y empieza a
sospechar que le va a llevar tres meses.

Está decidiendo **construirlo o comprarlo**, y el competidor honesto no es otro
paquete: es su propio fin de semana. No le convence una lista de funcionalidades,
porque se imagina escribiendo cada una. Le convencen **las que no había pensado
todavía**, y la evidencia de que alguien ya se chocó con ellas.

**Dos audiencias secundarias**, atendidas sin rediseñar el sitio para ellas:
- Alguien que ya usa el paquete y viene a consultar algo. Necesita documentación
  rápida y enlazable, no que se le venda otra vez.
- Alguien que evalúa por su equipo y necesita licencia y precio sin hablar con
  nadie.

**A quién NO se le vende** (y decirlo en voz alta gana más de lo que cuesta):
aplicaciones de un solo tenant, productos sin cuentas, equipos que por
cumplimiento necesitan ser dueños de cada línea desde el primer día —aunque para
estos últimos existe `eject`, §9.3.

---

## 3. Los cinco argumentos, en orden

La landing se construye sobre estos. Son las cosas que un fin de semana de
trabajo no produce.

1. **Aislamiento que aguanta donde suele romperse.** No "multi-tenant" como
   viñeta, sino: trabajos en cola, claves de caché, canales de broadcast,
   comandos de consola. El fallo concreto —*un job que se ejecuta como el cliente
   equivocado*— es lo que hay que nombrar, porque todo el que ha construido esto
   se lo ha encontrado.
2. **Permisos que son por cuenta.** La misma persona es administradora en una
   cuenta y sólo lectora en otra. Es el requisito que reescribe en silencio una
   capa de autorización seis meses después.
3. **Límites de plan que de verdad se aplican.** Contadores que sobreviven a dos
   peticiones simultáneas, y un 402 con un sitio al que ir. Casi toda
   implementación casera comprueba y luego actúa, y pasan las dos peticiones.
4. **Toda la superficie de producto, no sólo la tenancy.** Ficheros, importación,
   exportación, medición de consumo, credenciales de terceros, webhooks, GDPR,
   onboarding, API. Es la diferencia con cualquier librería de tenancy, y es el
   argumento que sobrevive al contacto con una hoja de ruta.
5. **Guardas, no sólo tests.** El número de tests, y sobre todo: las guardas de
   seguridad se verificaron **quitándolas** y confirmando que la suite se ponía
   roja. Un test que pasa con y sin la guarda es peor que no tener test, porque
   se lee como cobertura.

---

## 4. Capacidades del núcleo

### 4.1 Tenancy

**Qué es.** Una cuenta activa por petición, resuelta automáticamente y
transportada a todo lo que la petición dispare.

**El dolor.** El `account_id` acaba pasándose a mano por media aplicación. En
cuanto algo se ejecuta fuera de la petición HTTP —un job, un comando, un webhook
entrante— deja de haber contexto y el código o falla o, peor, ve los datos de
todos.

**La prueba.** Cuatro resolutores encadenados que corren en orden hasta que uno
devuelve cuenta: **dominio o subdominio**, **token de API**, **sesión**,
**usuario**. Y una sola línea de configuración decide qué hace una consulta
cuando no hay cuenta en contexto: `auto` (sin filtro en consola y colas, sin
resultados por HTTP), `allow` o `deny`.

```php
Tenant::current();                       // la cuenta activa, venga de donde venga
Tenant::runFor($otra, fn () => ...);     // ejecutar como otra, y restaurar al salir
```

**Estado.** `Disponible`.

---

### 4.2 Aislamiento

**Qué es.** Un trait que añade un *global scope* al modelo y sella el
`account_id` al crear, de modo que un `where` olvidado no puede filtrar los datos
de otro cliente.

**El dolor.** El scoping manual funciona hasta que alguien escribe la consulta
número cuarenta y siete un viernes. El fallo no da error: devuelve datos de más.

**La prueba.** La integración completa de un modelo propiedad del tenant es una
línea:

```php
class Invoice extends Model
{
    use BelongsToAccount;   // filtrado al leer, sellado al crear, también en jobs
}

Invoice::all();                        // las facturas de esta cuenta
Invoice::query()->acrossAccounts();    // todas — sólo herramientas de staff
```

Y —esto es lo que no tiene nadie— el paquete te da las aserciones para
comprobarlo **sobre tus propios modelos**:

```php
$this->assertTenantIsolated(Invoice::class, fn ($cuenta) => Invoice::factory()->create());
$this->assertJobCarriesTenant(new SyncInvoicesJob);
$this->assertPermissionIsAccountScoped('invoices.view');
```

**Estado.** `Disponible`.

---

### 4.3 Permisos por cuenta

**Qué es.** `spatie/laravel-permission` con `account_id` como clave de equipo.
Los roles se resuelven por cuenta, desde la base de datos, igual en peticiones
que en jobs, comandos y llamadas a la API.

**El dolor.** El atajo habitual es cachear los roles en la sesión HTTP. Funciona
en pantalla y falla en todo lo demás: en un job no hay sesión, así que o no hay
roles o —el caso peligroso— se devuelven los roles de todas las cuentas del
usuario. Es una escalada de privilegios entre clientes que no da ningún error.

**La prueba.** Nada vive en la sesión. Se comprueba con `hasPermission()`, con
`can` y con políticas, y funciona idéntico fuera de HTTP.

**Estado.** `Disponible`.

---

### 4.4 Roles que define el propio cliente

**Qué es.** Un catálogo de roles compartido más los roles que cada cuenta se
inventa para sí misma, editados en una matriz rol × permiso.

**El dolor.** Los roles fijos en un enum duran hasta el tercer cliente, que
quiere un "supervisor que ve todo pero no borra". A partir de ahí es una petición
de producto cada dos semanas.

**La prueba.** Pantalla `/roles` con la matriz completa. Los roles de sistema se
marcan y no se pueden romper; los de la cuenta viven dentro de ella.

**Estado.** `Disponible`.

---

### 4.5 Políticas

**Qué es.** Políticas de Laravel sobre `User`, `Account`, `Role` y `UserInvite`.

**El dolor.** Sin ellas, las comprobaciones de rol se esparcen por vistas y
componentes, y ninguna dice qué pasa cuando el objeto pertenece a otra cuenta.

**Estado.** `Disponible`.

---

### 4.6 Navegación en base de datos

**Qué es.** El menú se declara en código, se guarda en base de datos, y cada
cuenta puede reordenarlo y ocultar entradas. Se filtra por permiso y por
funcionalidad de plan, y lleva contadores en vivo.

**El dolor.** Un menú en Blade es un `@if` por rol que nadie se atreve a tocar, y
que no puede personalizarse por cliente sin un despliegue.

**La prueba.** `Menu::register()` para declararlo, pantalla `/navigation` para
que el cliente lo ordene, `k2labs-base:sync-menus` para reconciliar.

**Estado.** `Disponible`.

---

### 4.7 Feature flags por cuenta

**Qué es.** Funcionalidades por plan con **excepciones por cuenta y con fecha de
caducidad**.

**El dolor.** Los gates por plan cubren el 80 %. El 20 % restante es
comercial: "a este cliente le hemos prometido la exportación durante dos meses".
Eso, sin flags por cuenta, acaba siendo un `if ($account->id === 47)`.

**La prueba.**

```php
Feature::for($account)->active('export');
@feature('export') ... @endfeature
```

Pantalla `/features` con la línea base del plan y los overrides por cuenta.

**Estado.** `Disponible`.

---

### 4.8 Ajustes tipados

**Qué es.** Clases de esquema con valores por defecto declarados sobre un
almacén clave-valor, **y el formulario se dibuja solo a partir de los tipos de
las propiedades**.

**El dolor.** Un `settings` clave-valor sin tipar es un `json_decode` con los
dedos cruzados, y cada ajuste nuevo son tres sitios que tocar: el almacén, el
formulario y la validación.

**Estado.** `Disponible`.

---

### 4.9 Autenticación

**Qué es.** Login, registro, recuperación de contraseña, verificación de email,
2FA con códigos de recuperación, impersonación auditada y cambio de contraseña
forzado.

**La prueba.** 2FA TOTP con `pragmarx/google2fa` y QR generado en local.
Impersonación con `lab404/laravel-impersonate`, y cada entrada y salida queda en
el registro de actividad.

**Estado.** `Disponible`. La ampliación —magic links, passkeys, sesiones,
políticas por tenant— está en §6.

---

### 4.10 Facturación

**Qué es.** Laravel Cashier sobre Stripe, con las funcionalidades abiertas o
cerradas según el plan. Checkout con periodo de prueba, códigos promocionales y
portal de facturación de Stripe.

**Estado.** `Disponible`. La ampliación —asientos, planes editables, dunning—
está en §6.

---

### 4.11 Registro de actividad

**Qué es.** Auditoría por cuenta, con seguimiento automático de cambios en los
modelos y filtrado de campos sensibles.

**El dolor.** "¿Quién cambió esto?" es la primera pregunta de cualquier
incidencia con un cliente, y un log de fichero no la responde. Y el registro que
guarda el hash de la contraseña en el diff es un incidente esperando a pasar.

**La prueba.** Trait `LogsActivity`, pantalla `/activity` filtrable, y
`k2labs-base:prune-activity-log` diario para que la tabla no crezca sin fin.

**Estado.** `Disponible`.

---

### 4.12 Notificaciones

**Qué es.** Notificaciones en base de datos con componente de campana, sondeo y
resumen diario por email.

**Estado.** `Disponible`. Tiempo real con Reverb: `Planificado`.

---

### 4.13 Invitaciones

**Qué es.** Invitación por token con email y asignación de rol, con caducidad, y
aceptación tanto antes como después de registrarse.

**Estado.** `Disponible`.

---

## 5. Los módulos

Cada módulo tiene un interruptor. Un producto que no quiere ficheros ni webhooks
no arrastra sus tablas, ni sus rutas, ni sus entradas de menú, ni su trabajo
programado. La fachada del módulo apagado lanza excepción en vez de consultar
tablas que nunca migraste.

### 5.1 Medición de consumo — `Meter`

**Qué es.** Contadores y medidores por cuenta, con los límites del plan aplicados
bajo bloqueo, un 402 con llamada a la acción de mejora de plan, avisos al 80 % y
al 100 %, y reporte horario a Stripe Billing Meters.

**El dolor.** Dos peticiones simultáneas contra un límite de plan. La
implementación casera comprueba (`si consumo < límite`) y luego incrementa, y
ambas pasan la comprobación antes de que ninguna incremente. El cliente se queda
con un documento de más gratis; con un límite de asientos, con un usuario de más
para siempre.

**La prueba.**

```php
Meter::increment('documents.generated');
Meter::incrementOrFail('documents.generated');   // 402 cuando el plan está lleno
Meter::remaining('storage.bytes');
```

Y el middleware `base-tenant.metered` para cerrar una ruta entera.

**Por qué importa comercialmente.** Es lo que permite vender por consumo sin
construir un sistema de facturación. `k2labs-base:report-usage` empuja a Stripe
cada hora.

**Estado.** `Disponible`.

---

### 5.2 Ficheros — `HasFiles`, `FileStore`

**Qué es.** Subida directa a S3 que **nunca pasa por PHP**, colecciones con
reglas de tipo y tamaño, variantes de imagen, cuota por cuenta y una pantalla de
biblioteca de medios.

**El dolor.** `WithFileUploads` de Livewire sube el fichero a tu servidor y luego
a S3: pagas el ancho de banda dos veces, el proceso PHP se queda ocupado, y un
vídeo de 500 MB tumba la petición. Y la cuota por cuenta, si se calcula con un
`sum('size')` en cada carga de página, se convierte en una consulta cara en la
ruta más caliente.

**La prueba.**

```php
$propiedad->filesIn('fotos');
$propiedad->firstFile('portada')?->variantUrl('thumb');
```

```blade
<livewire:base-tenant.files.uploader :fileable="$propiedad" collection="fotos" />
<livewire:base-tenant.files.gallery  :fileable="$propiedad" collection="fotos" />
<livewire:base-tenant.files.usage-badge />
```

El medidor de almacenamiento es un *gauge* del módulo de consumo, no un `sum`, y
`k2labs-base:reconcile-storage` lo recuenta semanalmente por si se desvía.

**Estado.** `Disponible`.

---

### 5.3 Importación y exportación — `Transfer`

**Qué es.** CSV de entrada y de salida, mapeo automático de columnas, trabajos
en cola por trozos, y **las filas rechazadas se devuelven como fichero para
corregir y volver a subir**.

**El dolor.** Toda importación de CSV real falla parcialmente: 4.000 filas
buenas y 37 malas. Lo que el usuario necesita no es un mensaje de error, es las
37 filas de vuelta con el motivo al lado.

**La prueba.**

```php
Transfer::import('huespedes', $ficheroSubido, $mapeo);
Transfer::export('huespedes');
```

**Estado.** `Disponible`.

---

### 5.4 Generador de módulos — `k2labs-base:make-module`

**Qué es.** Un comando que escribe una vertical CRUD entera: modelo, migración,
factoría, política, los dos componentes Livewire, las dos vistas, los dos
idiomas, los tests y el documento para agentes.

**El dolor.** El coste real de un producto SaaS no es el primer CRUD, son los
veinte siguientes, cada uno con su tabla, su política, sus traducciones y sus
tests, todos ligeramente distintos porque los escribió otra persona otro mes.

**Estado.** `Disponible`.

---

### 5.5 Credenciales de terceros — `Connection`

**Qué es.** Las credenciales que un cliente tiene para servicios de terceros,
cifradas en reposo, con comprobación de salud nocturna y aviso al fallar.

**El dolor.** En cuanto el producto se integra con algo, cada cliente trae sus
propias claves. Acaban en `settings`, o en columnas sueltas, sin cifrar, y nadie
se entera de que caducaron hasta que el cliente llama.

**La prueba.**

```php
Connection::store('wubook', ['token' => '...']);
Connection::client('wubook');
```

`k2labs-base:check-connections` a diario, y notificación al responsable de la
cuenta cuando una empieza a fallar.

**Estado.** `Disponible`.

---

### 5.6 Webhooks salientes — `Webhook`

**Qué es.** Entregas firmadas con reintentos a 1 m / 5 m / 30 m / 2 h / 12 h y
desactivación automática de los destinos muertos.

**El dolor.** Un `Http::post()` suelto no está firmado —el receptor no puede
verificar que vienes de ti—, no reintenta, y cuando el endpoint del cliente lleva
seis meses caído sigues gastando cola en él.

**La prueba.** `Webhook::dispatch('booking.confirmed', ['id' => $reserva->id]);`

**Estado.** `Disponible`.

---

### 5.7 Idiomas — `Language`

**Qué es.** Locales que se activan y desactivan **en caliente, sin despliegue**,
con cobertura de traducción por idioma.

**El dolor.** Añadir un idioma suele ser un array en config y por tanto un
despliegue; y nadie sabe qué porcentaje está realmente traducido hasta que un
cliente ve una clave cruda en pantalla.

**La prueba.**

```php
Language::enable('ca');
Language::codes();
```

```bash
k2labs-base:lang-status --missing --fail-under=90   # rompe el CI si bajas del 90 %
k2labs-base:lang-push / lang-pull
```

**Estado.** `Disponible`.

---

### 5.8 Login social

**Qué es.** Google, LinkedIn y Microsoft Entra ID, con una regla anti-secuestro
de cuenta y el 2FA respetado.

**El dolor.** La implementación ingenua de "entrar con Google" permite tomar el
control de una cuenta existente si el proveedor devuelve un email que ya existe
sin verificar. Y casi todas se saltan el segundo factor.

**Estado.** `Disponible` (tres proveedores). Ampliar a GitHub, Apple, Facebook,
GitLab y Slack: `Planificado`.

---

### 5.9 Numeración correlativa — `Sequence`

**Qué es.** Numeración correlativa por cuenta, bloqueada, con reinicio automático
por periodo y formatos.

**El dolor.** En cuanto emites facturas, albaranes o expedientes, la numeración
deja de ser un detalle y pasa a ser un requisito legal: sin huecos, sin
repeticiones, reiniciando por año. Un `max(id) + 1` falla con concurrencia, y
exponer el autoincremento le cuenta a tu cliente cuántas facturas emites.

**La prueba.**

```php
Sequence::next('facturas', format: 'F{year}-{number:5}', period: 'year');
```

**Estado.** `Disponible`.

---

### 5.10 Onboarding — `Onboarding`

**Qué es.** Una lista de tareas de puesta en marcha declarativa que desaparece
cuando está terminada.

**El dolor.** La alternativa es una columna booleana por paso en `accounts`, y
una migración cada vez que cambias el onboarding.

**La prueba.** `Onboarding::progress()` y
`<livewire:base-tenant.onboarding.checklist />`.

**Estado.** `Disponible`.

---

### 5.11 Supresión de envíos — `Suppression`

**Qué es.** Una guarda global de envío alimentada por los webhooks firmados del
proveedor de correo.

**El dolor.** Una dirección que rebota y a la que sigues escribiendo quema tu
reputación de envío para todos los demás clientes. La comprobación en un solo
punto de llamada no sirve: hace falta que sea imposible saltársela.

**La prueba.** La guarda es global, no un `if` en el sitio donde te acordaste.
`Suppression::isSuppressed($email)` para consultarla, y
`k2labs-base:import-suppressions` para cargar el histórico.

**Estado.** `Disponible`.

---

### 5.12 GDPR

**Qué es.** Exportación de datos personales, purga programada de borrados
lógicos caducados, y aceptación de términos versionada.

**El dolor.** El soft delete es un periodo de gracia, no un archivo permanente,
y casi nadie lo purga. Y `$user->toArray()` como exportación de datos personales
incluye el hash de la contraseña.

**La prueba.** Un `GdprExporter` por dominio que contiene datos personales,
`k2labs-base:export-user-data`, y `k2labs-base:purge-deleted` a diario.

**Estado.** `Disponible`.

---

### 5.13 Pre-venta — `Presale`

**Qué es.** Registro cerrado, página de aterrizaje, lista de espera y plazas
fundadoras con contador en vivo.

**El dolor.** Validar antes de construir requiere una landing con lista de
espera, y lo normal es montarla fuera del producto, con su propia lista que luego
hay que migrar a mano.

**La prueba.** `Presale::seatsLeft()`, `Presale::join($email)`,
`k2labs-base:presale-open --batch=50`, y los componentes de tabla de precios y
formulario de lista de espera.

**Estado.** `Disponible`.

---

## 6. Lo nuevo — v3

Diez piezas. Cada una tiene aquí la especificación funcional que
la landing puede usar **en cuanto esté disponible**, y ni un día antes.

### 6.1 Subdominio por cliente

**Qué es.** Cada cuenta con su propia dirección: `cliente.tuapp.com`.

**El dolor.** El cliente empresarial espera su propio espacio, no un `/app` con
un selector de cuenta. Y sin subdominio no hay forma limpia de tener sesiones
independientes por cliente en el mismo navegador.

**Alcance.** Reserva y validación del subdominio (longitud, caracteres, lista de
reservados: `www`, `api`, `admin`, `app`, `mail`, `static`…), unicidad,
disponibilidad en vivo al escribirlo, cambio auditado con redirección desde el
anterior durante un periodo, y comportamiento definido de sesión y cookies entre
el dominio central y el del cliente.

**La prueba.** `Domain::claimSubdomain($account, 'acme')` y
`Domain::urlFor($account)`. La lista de reservados cubre los nombres que
colisionan con la propia instalación (`www`, `api`, `mail`, `admin`…), y el
índice único de la base de datos es la guarda real contra dos cuentas
reclamando el mismo nombre en el mismo instante.

**Estado.** `Disponible`.

---

### 6.2 Dominio personalizado por cliente

**Qué es.** El cliente apunta su propio dominio —`app.sucliente.com`— a tu
producto.

**El dolor.** Es la funcionalidad que un cliente empresarial pide en la segunda
reunión y que, sin ella, deja el producto con pinta de herramienta interna. La
parte difícil no es servir el dominio: es **verificar que es suyo** antes de
servirlo, o cualquiera puede apuntar un dominio al tuyo.

**Alcance.** Alta del dominio, verificación de propiedad por registro TXT,
estados (`pendiente`, `verificado`, `fallido`), re-verificación programada,
instrucciones de CNAME y certificado, y desactivación cuando deja de resolver.

**La prueba.** El registro TXT en `_base-tenant-verify.<dominio>`, y el filtro
`verified()` en el resolutor. Esa es la guarda de toda la capacidad: quitarla
pone la suite en rojo, porque sin ella cualquiera que pueda apuntar un DNS a
esta instalación sería servido como la cuenta que escribió el nombre en el
formulario.

Un dominio ya verificado **no** se degrada por un fallo puntual de DNS: sacar
de servicio el hostname de producción de un cliente por un parpadeo sería peor
que el problema que resuelve.

**Estado.** `Disponible`.

---

### 6.3 Magic links

**Qué es.** Entrar con un enlace firmado de un solo uso, sin contraseña.

**El dolor.** La contraseña es la primera fricción del registro y la primera
causa de tickets de soporte. Larafast y JetShip lo llevan; nosotros no.

**Alcance.** Solicitud con límite de frecuencia, caducidad corta, invalidación al
usarlo, y —esto es lo que suele hacerse mal— **respeto del segundo factor y de
las políticas de seguridad del tenant**: un magic link no puede ser una puerta
trasera que salte el 2FA obligatorio.

**Estado.** `En construcción`.

---

### 6.4 Passkeys (WebAuthn)

**Qué es.** Entrar con la huella, la cara o la llave física del dispositivo.

**El dolor.** El TOTP es mejor que nada pero es *phishable*: si el usuario mete
el código en una página falsa, se acabó. La passkey está atada al dominio y no
puede entregarse a un sitio que no sea el tuyo.

**Alcance.** Alta de credencial, listado, renombrado y borrado, uso como segundo
factor y como login sin contraseña, y respaldo con TOTP para no dejar a nadie
fuera al perder el dispositivo.

**Estado.** `En construcción`.

---

### 6.5 Sesiones activas y revocación

**Qué es.** Ver dónde tienes la sesión abierta y cerrarla a distancia.

**El dolor.** Un portátil perdido o un ordenador compartido no tienen respuesta
si el producto no lista sesiones. Es, además, una casilla fija en todos los
cuestionarios de seguridad de compra.

**La prueba.** Tabla propia, no la de Laravel: la suya sólo existe con el
driver de base de datos, y revocar borrando su fila sólo funciona con ese
driver. Aquí la revocación es una marca que lee el middleware, así que funciona
con cualquiera.

**El coste, dicho en voz alta:** una sesión revocada se cierra en su
*siguiente petición*, no en el instante en que se pulsa el botón. El diálogo de
confirmación lo dice.

El identificador de sesión se guarda **hasheado** y nunca se muestra: quien lo
tiene *es* la sesión, así que una copia de seguridad filtrada de esa tabla
sería un juego de sesiones vivas. La exportación GDPR incluye dirección y
dispositivo —que sí son dato personal— y omite el identificador.

«Cerrar las demás» conserva la actual: sacar a alguien de la pantalla que está
usando para asegurar su cuenta es como se queda a medias.

**Lo que NO incluye.** Ubicación aproximada por IP. Requiere una base de datos
de geolocalización que no queremos como dependencia.

**Estado.** `Disponible`.

---

### 6.6 Políticas de seguridad por tenant

**Qué es.** Cada cliente decide las reglas de seguridad de su propia cuenta.

**El dolor.** Es lo que separa "una herramienta" de "una herramienta que el
departamento de IT aprueba". Y ningún starter kit de Laravel lo tiene.

**Alcance.**
- **Forzar 2FA** a todos los miembros de la cuenta, con periodo de gracia para
  configurarlo.
- **Dominios de email permitidos** en registro e invitación, para que nadie meta
  su cuenta personal en el espacio de la empresa.
- **Lista blanca de IP**, con modo *aviso* antes que modo *bloqueo*, para no
  dejar fuera a la propia cuenta al configurarla.
- **Caducidad de sesión** por inactividad, decidida por la cuenta.
- Todo aplicado por middleware y **auditado**: quién cambió qué regla y cuándo.

**La prueba.** Tres middleware (`base-tenant.two-factor`,
`base-tenant.ip-allowlist`, `base-tenant.session-timeout`) y dos guardas que
existen porque el fallo que evitan no tiene arreglo desde dentro del producto:
una lista blanca vacía **nunca** bloquea, y la pantalla se niega a activar el
modo bloqueo desde una dirección que la lista no cubre. Quitar cualquiera de
las dos pone la suite en rojo.

El modo *aviso* va antes que el de bloqueo a propósito: registra lo que se
bloquearía sin bloquearlo, para que un administrador encienda la regla, mire un
día de tráfico real y encuentre la VPN de la oficina que se le olvidó.

**Lo que NO incluye.** Política de contraseñas propia por cuenta. Está fuera de
esta entrega.

**Estado.** `Disponible`.

---

### 6.7 Precio por asiento sincronizado con Stripe

**Qué es.** Cobrar por usuario, con la cantidad siempre igual a los miembros
activos de la cuenta.

**El dolor.** Es el modelo de precio por defecto del B2B, y a mano es una fuente
constante de desajuste: alguien añade tres usuarios y la suscripción sigue
diciendo cinco. Se factura de menos durante meses y nadie se entera.

**Alcance.** Alta y baja de usuario ajustan la cantidad en Stripe con prorrateo,
bloqueo al superar los asientos contratados con su 402 —reusando el módulo de
consumo, que ya sabe hacer esto bajo bloqueo—, y una reconciliación periódica que
detecta y corrige las desviaciones.

**Estado.** `En construcción`.

---

### 6.8 Planes y productos editables, sincronizados con Stripe

**Qué es.** Los planes dejan de vivir en un fichero de configuración y pasan a la
base de datos, editables desde el panel, sincronizados con los productos y
precios de Stripe.

**El dolor.** Hoy cambiar un precio es un despliegue. Peor: el catálogo vive en
dos sitios —tu `config` y tu cuenta de Stripe— y se desincronizan sin avisar.

**Alcance.** Planes, sus funcionalidades y sus límites editables en el panel;
sincronización con productos y precios de Stripe; y **un paso en el instalador**
que, si detecta claves de Stripe, ofrece crear el primer producto con sus datos
básicos, de modo que un proyecto nuevo arranca con catálogo funcionando en vez de
con un `null` en `default_price`.

**Estado.** `En construcción`.

---

### 6.9 Recuperación de pagos fallidos

**Qué es.** Que un cobro rechazado no se convierta en una baja silenciosa.

**El dolor.** Entre el 20 % y el 40 % del churn de un SaaS es involuntario:
tarjetas caducadas, límites, bancos que rechazan. Sin dunning, el cliente
descubre que ha dejado de pagar cuando descubre que ha dejado de tener acceso.

**Alcance.** Escucha de los webhooks de pago fallido de Stripe, secuencia de
avisos por email y dentro de la aplicación, aviso previo de tarjeta a punto de
caducar, periodo de gracia con degradación progresiva del acceso en vez de corte
seco, y recuperación automática en cuanto el cobro entra.

**Estado.** `En construcción`.

---

### 6.10 API REST por tenant, con documentación

**Qué es.** Una API pública versionada, con las claves gestionadas por el propio
cliente, y su documentación.

**El dolor.** Es la primera petición de cualquier cliente B2B con equipo técnico.
Hoy el producto sabe **empujar** eventos —los webhooks salientes— pero no deja
que nadie le **pregunte**. Y una API sin gestión de claves en la interfaz
significa que las claves las creas tú a mano, por email.

**Alcance.** Superficie REST versionada resuelta por token de cuenta; pantalla de
gestión de claves con *scopes*, rotación, caducidad y "último uso"; límite de
frecuencia por plan; errores y paginación homogéneos; y documentación OpenAPI
publicable.

**Base ya existente.** `ApiTokenTenantResolver` resuelve la cuenta desde el token
y `personal_access_tokens` ya tiene `account_id`. Sanctum está instalado. Falta
la superficie entera.

**Estado.** `En construcción`.

---

## 7. La experiencia

**Un patrón de tabla para todas las pantallas de gestión.** Búsqueda, orden,
densidad y tamaño de página **en la URL** —de modo que una vista filtrada se
puede compartir por chat—, cabeceras fijas, estados vacíos diseñados y esqueletos
de carga.

**Stack.** Laravel 13, Livewire 4, Flux UI 2.4 (basta la capa gratuita), Tailwind
4, Pest. Modo oscuro en todo.

**Componentes que se sueltan en una vista:**

```blade
<livewire:base-tenant.files.uploader :fileable="$m" collection="fotos" />
<livewire:base-tenant.files.gallery :fileable="$m" collection="fotos" />
<livewire:base-tenant.files.usage-badge />
<livewire:base-tenant.onboarding.checklist />
<livewire:base-tenant.presale.pricing-table />
<livewire:base-tenant.presale.waitlist-form source="landing" />
<livewire:base-tenant.profile.connected-accounts />
<x-base-tenant::social-buttons />
```

**Pantallas que trae:** panel, perfil, usuarios, invitaciones, cuentas, roles,
navegación, funcionalidades, ajustes, actividad, consumo, ficheros,
importaciones, conexiones, idiomas, checkout y facturación.

---

## 8. Pensado para trabajar con agentes de IA

**Qué es.** `docs/agents/`: un fichero por capacidad, corto como para caber en
una ventana de contexto junto al código de la tarea, y encabezado por un **mapa
de capacidades** que responde "¿esto ya existe?" antes de que se escriba nada.

**El dolor.** Un agente al que le pides "añade subida de ficheros" te la escribe.
Funciona, pasa sus propios tests, y se salta en silencio el aislamiento por
tenant, la cuota de almacenamiento y el registro de actividad, porque están en la
capa por la que ha pasado de largo.

**La prueba.** `k2labs-base:publish-agent-docs` copia la documentación a la
aplicación y mantiene una sección de `CLAUDE.md` / `AGENTS.md` **entre
marcadores**, de modo que puede reejecutarse tras cada `composer update` sin
tocar lo que el proyecto haya escrito alrededor.

**Por qué es un argumento de venta.** Ningún competidor lo tiene, y el comprador
de 2026 programa con un agente al lado. Es la diferencia entre un paquete que el
agente respeta y uno que el agente ignora.

**Estado.** `Disponible`.

---

## 9. Instalación, propiedad del código y operación

### 9.1 Instalación

```bash
composer require base/tenant
php artisan k2labs-base:install
```

El instalador pregunta cómo configurar la base de datos, si quieres modo
multi-equipo, Stripe y un usuario de prueba; **prueba la conexión antes de
escribir nada**; y ofrece publicar la documentación para agentes.
`--no-interaction` coge los valores por defecto para CI.

### 9.2 Empezar de cero con el starter kit

La forma más rápida de verlo funcionando no es instalarlo en una aplicación
existente, sino arrancar un proyecto nuevo desde `k2/base-tenant-kit`, que llega
con esto ya instalado y configurado.

### 9.3 Quedarte el código — `scaffold` / `eject`

**Qué es.** Un comando copia el código del paquete dentro de tu aplicación, y
otro elimina el paquete.

**El dolor.** La primera objeción a comprar cualquier kit es el *vendor lock-in*:
"¿y si el mantenimiento se para?". La respuesta habitual es un encogimiento de
hombros.

**La prueba.**

```bash
php artisan k2labs-base:scaffold --dry-run   # ver exactamente qué se movería
php artisan k2labs-base:scaffold             # ~200 ficheros a app/, resources/, routes/
php artisan k2labs-base:eject                # y fuera el paquete
```

`scaffold` reescribe `Base\Tenant\` a `App\`, `<x-base-tenant::*>` a
`<x-tenant.*>` y las claves de vista y traducción, y genera el
`TenancyServiceProvider` con todos los registros que hacía el paquete. Mientras
está copiado, **el paquete se aparta** para que nada quede registrado dos veces —
y se puede volver atrás.

**Por qué es el mejor argumento de la landing.** Es lo único que responde a la
objeción real. Merece su propia sección, no una viñeta.

**Estado.** `Disponible`.

### 9.4 Trabajo programado que trae de serie

| Comando | Frecuencia |
|---|---|
| `prune-activity-log` | diaria |
| `report-usage` (consumo a Stripe) | horaria |
| `reconcile-storage` | semanal |
| `check-connections` | diaria |
| `purge-deleted` | diaria |

---

## 10. La evidencia

Esto es lo que va en la sección "pruebas" de la landing, y todo es verificable:

| | |
|---|---|
| Tests del paquete | **859 pasando**, 4.036 aserciones, suite completa en 79 s |
| Las guardas se verificaron quitándolas | Una guarda necesita un test que se ponga rojo al retirarla. Un test que pasa igual con y sin ella se lee como cobertura y no lo es |
| Kit de aserciones para tu propio código | `assertTenantIsolated`, `assertJobCarriesTenant`, `assertPermissionIsAccountScoped` |
| Documentación por capacidad | 20 ficheros en `docs/agents/`, más el manual completo |
| Página de limitaciones | §11, enlazada desde la portada |

---

## 11. Lo que NO hace

**Esta sección se publica.** Es el activo de credibilidad más fuerte del sitio.

### 11.1 No construido (y sin fecha)

Base de datos por tenant · SSO empresarial SAML/OIDC · SCIM · blog o CMS ·
página pública de precios y marketing · SEO y sitemap · métricas de negocio
(MRR, churn, ARPU) · plantillas de email con marca · programa de afiliados ·
sistema de temas · sistema de plugins · perfiles públicos de usuario · búsqueda
global · papelera de restauración · notificaciones en tiempo real · pagos únicos
· Paddle o Lemon Squeezy · factura PDF propia con datos fiscales · Docker o
receta de despliegue · Horizon, Pulse o backups preconfigurados.

### 11.2 Deliberadamente fuera de alcance

- **Base de datos por tenant.** La apuesta es el *global scope* con propagación,
  y está probada. Ir a multi-BD es reescribir el eje entero. Si lo necesitas,
  `stancl/tenancy` es la herramienta correcta y lo decimos aquí.
- **Temas y plugins.** Encarecen el mantenimiento y el comprador de un producto
  B2B no los pide.
- **Perfiles públicos y afiliados.** Son patrones B2C.

### 11.3 No verificado contra un servicio real

Aquí van, nombradas una a una, las integraciones cuya ruta feliz está cubierta
por tests pero que no se han ejercitado contra el servicio de verdad en
producción. **Mantener esta lista al día es obligatorio antes de cada
publicación.** Un elemento sale de esta lista cuando alguien lo ha ejecutado
contra el servicio real, no cuando su test pasa.

---

## 12. Frente al mercado, en una tabla

Resumen del análisis completo en
[`plans/2026-08-31-comparativa-saas-starter-kits.md`](plans/2026-08-31-comparativa-saas-starter-kits.md).

| | base/tenant | SaaSykit | Larafast | JetShip | Spark | Wave |
|---|---|---|---|---|---|---|
| Aislamiento propagado a jobs, caché y broadcast | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Permisos por cuenta fuera de la sesión HTTP | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Aserciones de aislamiento para tu código | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Límites de plan bajo bloqueo + 402 | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Feature flags por cuenta con caducidad | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Numeración correlativa | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Import/export con fichero de rechazos | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Credenciales de terceros con health check | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Webhooks salientes firmados | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Idiomas activables sin despliegue | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| GDPR completo | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `eject`: quedarte el código y borrar el paquete | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Documentación para agentes de IA | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Blog, SEO y landing de marketing | ❌ | ✅ | ✅ | ✅ | ❌ | ✅ |
| Métricas de negocio (MRR, churn) | ❌ | ✅ | 🟡 | 🟡 | ❌ | 🟡 |
| Varias pasarelas de pago | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |

La lectura, y conviene que la landing la haga explícita: **no somos un kit para
lanzar rápido, somos la base para construir en serio.** Quien quiera una landing
y un blog el viernes, que compre otra cosa; lo decimos nosotros antes de que lo
descubra él.

---

## 13. Material para la landing

### 13.1 Titulares candidatos

1. *Todo lo de tu SaaS que no es tu producto, ya construido.*
2. *Multi-tenant de verdad: el aislamiento también dentro de la cola.*
3. *Llevas tres días con el scoping por tenant. Te quedan tres meses.*
4. *La base para SaaS B2B que puedes desinstalar quedándote el código.*
5. *Tus clientes son cuentas. Nada se filtra entre ellas. Y hay un test que lo demuestra.*

### 13.2 Orden de la portada

1. **Qué es**, en una frase, y para quién en otra. Una sola acción principal. Sin
   carrusel y sin muro de logos que el producto no se ha ganado.
2. **El problema, nombrado con precisión.** Tres o cuatro frases sobre la
   diferencia entre "filtrar la consulta" y "que el filtro sobreviva a un job".
   Quien lo ha sufrido se reconoce; quien no, todavía no es el comprador.
3. **Código real.** `BelongsToAccount` en un modelo, y el mismo modelo leído
   dentro de un job sin trabajo adicional. Corto como para leerlo sin scroll.
4. **La rejilla de capacidades.** El núcleo y los catorce módulos, una línea cada
   uno, enlazando a la documentación. Aquí es donde la superficie hace su trabajo.
5. **La evidencia.** Número de tests, el enfoque de las guardas, y **el enlace a
   la página de limitaciones**. Poner ese enlace en la portada es justamente el
   argumento.
6. **`eject`**, con su propia sección: la respuesta a la objeción del lock-in.
7. **El starter kit**, como la forma de empezar un proyecto nuevo.

### 13.3 Las objeciones, y su respuesta

| Objeción | Respuesta |
|---|---|
| "Esto me lo hago yo en un fin de semana" | Hazte la lista: los cinco puntos del §3. El fin de semana produce el scoping; no produce que sobreviva a la cola, ni el 402 bajo bloqueo, ni el fichero de rechazos del CSV |
| "¿Y si dejáis de mantenerlo?" | `scaffold` y `eject`. Te quedas ~200 ficheros en tu `app/` y borras el paquete. Un comando, no una reescritura |
| "Ya uso `stancl/tenancy`" | Resuelven cosas distintas. Ellos hacen multi-base; nosotros, la superficie de producto entera sobre base compartida. Si necesitas aislamiento a nivel de base de datos, lo correcto es lo suyo |
| "No tiene blog ni landing" | Cierto, y está en la página de limitaciones. No es lo que compras aquí |
| "Es propietario" | Precio y licencia visibles sin hablar con nadie. Y `eject` como salida |
| "¿Funciona con mi aplicación existente?" | El instalador detecta conflictos y prueba la conexión antes de escribir nada. `--dry-run` en todo lo destructivo |

### 13.4 Preguntas frecuentes

- ¿Un usuario puede pertenecer a varias cuentas? Sí — modo multi-equipo. Es la
  única decisión de arquitectura que conviene tomar antes de escribir código.
- ¿Puedo apagar módulos que no uso? Sí, uno a uno. Apagado significa sin tablas,
  sin rutas, sin menú y sin trabajo programado.
- ¿Necesito Flux Pro? No. El paquete sólo usa componentes de la capa gratuita.
- ¿Base de datos por tenant? No, y §11.2 explica por qué.
- ¿Qué versiones? PHP 8.4, Laravel 13, Livewire 4, MySQL o PostgreSQL.

### 13.5 Prueba mínima que la landing debe poder enseñar

Un bloque de código, no una captura:

```php
class Invoice extends Model
{
    use BelongsToAccount;
}

// En una petición: las facturas de esta cuenta.
Invoice::all();

// En un job encolado, sin pasar nada: las facturas de esta cuenta.
class SendMonthlyInvoices implements ShouldQueue
{
    public function handle(): void
    {
        Invoice::all();   // sigue siendo la cuenta correcta
    }
}
```

Y debajo, una línea: *y este test lo comprueba sobre tus modelos, no sobre los
nuestros* — con `assertJobCarriesTenant`.

---

## 14. Mantenimiento de este documento

- Una capacidad no pasa a `Disponible` hasta que está construida, probada y
  documentada. El estado de §6 se actualiza al cerrar cada pieza.
- §11.1 y §11.3 se revisan **antes de cada publicación**. Son las que sostienen
  la credibilidad del resto.
- El número de tests de §10 se toma de la ejecución real de la suite, no de
  memoria.
