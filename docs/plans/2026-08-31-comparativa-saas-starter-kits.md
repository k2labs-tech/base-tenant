# base/tenant vs. los Laravel SaaS starter kits del mercado

> Fecha: 31 agosto 2026 · Rama: `dev` (ab5adc4, v2)
> Alcance: `base/tenant` (el paquete) + `k2/base-tenant-kit` (el starter kit)

---

## 0. Nota sobre las fuentes

El inventario de `base/tenant` está verificado contra el código (`src/`, `routes/`,
`config/base-tenant.php`, `composer.json`). El de los competidores procede de sus
páginas de producto, documentación pública y artículos comparativos — **no** de
inspeccionar su código, que es de pago en casi todos los casos. Donde digo "no lo
tiene", léase "no lo anuncian ni aparece en su documentación pública".

Una aclaración de entrada: **`Naimul007A/laravel-base-kit` no es un SaaS starter
kit.** Es un paquete MIT con una `BaseService` para CRUD de API/Web, filtrado por
query string (rangos de fechas, `WHERE IN`, notación de puntos sobre relaciones),
búsqueda multicolumna y manejo de soft deletes. No tiene auth, ni billing, ni
tenancy, ni UI. No es comparable; lo dejo fuera de la matriz.

---

## 1. Resumen ejecutivo

**No competimos en el mismo eje que Larafast, JetShip o SaaSykit.** Esos kits
resuelven *"lanza tu SaaS indie el fin de semana"*: landing, pricing, blog, SEO,
Stripe, panel Filament. Su valor está en el go-to-market. `base/tenant` resuelve
*"construye producto B2B multi-tenant sin que se filtren datos entre clientes"*:
aislamiento, permisos por cuenta, metering, feature flags, auditoría. Su valor
está en la arquitectura.

De ahí salen las tres conclusiones:

1. **En el núcleo multi-tenant estamos muy por delante de todos ellos.** Ninguno
   tiene global scope con propagación a jobs, permisos que resuelvan fuera de la
   sesión HTTP, ni un kit de aserciones de aislamiento. SaaSykit Tenancy —el más
   cercano— es scoping de Filament más asientos sincronizados con el proveedor de
   pago; no es una capa de tenancy.
2. **En capacidades de producto (metering, sequences, transfer, connections,
   webhooks salientes, idiomas en runtime, GDPR) no tenemos competencia.** Varias
   de ellas no existen ni en el mundo Node/Next.
3. **Nos falta todo lo que hace vendible un SaaS** —front público, blog, SEO,
   métricas de negocio, panel de plataforma, planes editables— **y todo lo que
   hace vendible un B2B enterprise** —SSO SAML/OIDC, API pública con keys,
   políticas de seguridad por tenant. Lo primero lo tienen todos los
   competidores; lo segundo no lo tiene *ninguno*, y ahí está la oportunidad.

El error a evitar: gastar los próximos meses replicando la landing y el blog de
Larafast. Ese trabajo nos pone al nivel de un kit de 149 $. Los huecos enterprise
—que ningún kit Laravel cubre— nos ponen en otra categoría.

---

## 2. Qué es cada competidor

### Laravel Spark — 99 $/año (1 proyecto) · 199 $/año (ilimitado)
Oficial de Taylor Otwell. **Solo billing**, y a propósito. Se monta encima de
Jetstream/Breeze: planes mensuales/anuales, portal de facturación aislado de la
app, facturas PDF por email, actualización de método de pago, precio por asiento,
Stripe y Paddle (Paddle como merchant of record cubre el IVA europeo y PayPal).
No trae tenancy, ni panel admin, ni landing, ni permisos. Agnóstico de frontend.

### SaaSykit / SaaSykit Tenancy — ~179-199 $ vitalicio
Laravel 13 + Livewire + Alpine + Tailwind + Filament. El más completo del lote
en superficie de negocio: **cinco proveedores de pago** (Stripe, Paddle, Lemon
Squeezy, Polar, Creem), suscripciones planas y por asiento, **pagos únicos**,
planes y descuentos gestionados desde el admin, panel Filament, dashboard de
**métricas SaaS (MRR, churn, ARPU, conversión de trial)**, blog, sitemap y SEO
automáticos, plantillas de email que heredan los colores de marca, login social
(Google, Facebook, X, GitHub, LinkedIn), 2FA, onboarding, anuncios programables
en el sitio, despliegue en 1 clic (Deployer/Forge). La variante *Tenancy* añade
tenants con dashboard propio, invitaciones, roles por tenant, cambio de tenant y
**asientos sincronizados automáticamente con el proveedor de pago**.

### Larafast — ~149-169 $ vitalicio
Elige stack en la compra: **TALL o VILT**. Auth sobre Fortify con social y
**magic links**, Stripe + Paddle + Lemon Squeezy, admin Filament (usuarios, blog,
pedidos, suscripciones, estadísticas), blog, SEO (metatags, sitemap,
schema.org), landing, i18n, S3 e **integración OpenAI lista para usar**.

### JetShip (ThemeSelection) — ~149 $
TALL + FlyonUI + Filament. Lo fuerte es la **UI**: 100+ componentes, landing y
10+ páginas de utilidad, ficheros Figma en el pack de diseño, varios temas, modo
oscuro, personalización de marca. Auth con social amplio (Google, GitHub, X,
LinkedIn, Facebook, GitLab, Bitbucket, Slack), **magic links**, 2FA, vinculación
de cuentas. Stripe y Lemon Squeezy con pagos únicos y suscripciones, portal de
cliente, gestión de webhooks. ACL, **impersonación**, blog SEO, **roadmap con
votación de clientes**, plantillas de email, listo para traducir.

### Wave (DevDojo) — open source
Laravel + Livewire + Alpine + Tailwind + Filament. Auth (DevDojo Auth), planes
Stripe/Paddle ligados a roles, perfiles públicos de usuario, blog, **changelog**,
admin Filament, **sistema de temas** instalables, **sistema de plugins** de la
comunidad, API. Gratis, y esa es su baza.

### Tenancy for Laravel + su SaaS Boilerplate
El paquete `stancl/tenancy` es la referencia libre de **multi-base de datos**. Su
boilerplate de pago añade: subdominios por cliente y dominios propios de segundo
nivel, gestión de dominios desde el dashboard, Cashier integrado, admin en Nova,
**suite de tests consciente del tenant** (central + tenant), onboarding asíncrono
del tenant e integración con Ploi para vhost y HTTPS automáticos.

### Otros del ecosistema
`spatie/laravel-multitenancy` (mono y multi base, libre, sin UI) · Jetstream
(gratis: equipos, 2FA, tokens Sanctum, sesiones — sin billing ni admin) · los
starter kits oficiales de Laravel 12/13 React/Vue/Livewire (auth y poco más) ·
Filament (panel, gratis, base de casi todos los kits de pago) · Genesis,
LaraStarters, Laravel Boilerplate, Voyager, Backpack (admin/scaffolding libres).

---

## 3. Matriz comparativa

Leyenda: **✅** completo · **🟡** parcial · **❌** ausente

### 3.1 Núcleo multi-tenant

| | base/tenant | SaaSykit Tenancy | Spark | Larafast | JetShip | Wave | Tenancy for Laravel |
|---|---|---|---|---|---|---|---|
| Tenant resuelto por dominio / token / sesión / usuario | ✅ | 🟡 | ❌ | ❌ | ❌ | ❌ | ✅ (dominio) |
| Global scope automático (`BelongsToAccount`) | ✅ | 🟡 | ❌ | ❌ | ❌ | ❌ | n/a (BD aparte) |
| Tenant propagado a jobs en cola | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| Tenant en claves de caché y canales de broadcast | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| Base de datos por tenant | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| Subdominio / dominio propio por cliente | 🟡 | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| Aserciones de aislamiento para tu propio código | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | 🟡 |
| Modo single-team / multi-team conmutable | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Ejecutar como otro tenant (`Tenant::runFor`) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |

### 3.2 Identidad, autorización y seguridad

| | base/tenant | SaaSykit | Spark | Larafast | JetShip | Wave | Jetstream |
|---|---|---|---|---|---|---|---|
| Login, registro, reset, verificación | ✅ | ✅ | n/a | ✅ | ✅ | ✅ | ✅ |
| 2FA + códigos de recuperación | ✅ | ✅ | n/a | ✅ | ✅ | 🟡 | ✅ |
| Magic link / passwordless | ❌ | ❌ | n/a | ✅ | ✅ | 🟡 | ❌ |
| Passkeys / WebAuthn | ❌ | ❌ | n/a | ❌ | ❌ | ❌ | ❌ |
| Login social | 🟡 3 (Google, LinkedIn, Entra) | ✅ 5 | n/a | ✅ | ✅ 8 | ✅ | ❌ |
| Vinculación de varias cuentas sociales | ✅ | 🟡 | n/a | 🟡 | ✅ | 🟡 | ❌ |
| **SSO empresarial SAML / OIDC por tenant** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **SCIM (aprovisionamiento Okta/Entra)** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Permisos granulares (no solo roles) | ✅ | ✅ | ❌ | 🟡 | ✅ ACL | 🟡 | ❌ |
| Permisos con `account_id` como team key | ✅ | 🟡 | ❌ | ❌ | ❌ | ❌ | ❌ |
| Permisos que resuelven en jobs / CLI / API | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Roles que define el propio tenant | ✅ | 🟡 | ❌ | ❌ | ❌ | ❌ | 🟡 |
| Editor matriz rol × permiso | ✅ | 🟡 | ❌ | ❌ | 🟡 | ❌ | ❌ |
| Políticas (`Gate`) sobre los modelos del kit | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | 🟡 |
| Impersonación auditada | ✅ | 🟡 | ❌ | 🟡 | ✅ | 🟡 | ❌ |
| Invitaciones con token, email y rol | ✅ | ✅ | ❌ | 🟡 | 🟡 | ❌ | ✅ |
| Cambio de contraseña forzado | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Gestión de sesiones activas / revocar dispositivo | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| Políticas de seguridad por tenant (forzar 2FA, dominios de email, IP allowlist) | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

### 3.3 Monetización

| | base/tenant | SaaSykit | Spark | Larafast | JetShip | Wave |
|---|---|---|---|---|---|---|
| Stripe | ✅ Cashier | ✅ | ✅ | ✅ | ✅ | ✅ |
| Paddle | ❌ | ✅ | ✅ | ✅ | ❌ | ✅ |
| Lemon Squeezy | ❌ | ✅ | ❌ | ✅ | ✅ | ❌ |
| Polar / Creem | ❌ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Merchant of record (IVA delegado) | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Suscripción recurrente | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Pago único / lifetime | ❌ | ✅ | ❌ | ✅ | ✅ | ❌ |
| Precio por asiento sincronizado con el proveedor | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Facturación por uso (metered)** | ✅ Stripe Meters | ❌ | 🟡 | ❌ | ❌ | ❌ |
| **Límites de plan aplicados bajo lock + 402** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Avisos al 80 % y 100 % de consumo | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Planes editables desde el admin (no en config) | ❌ | ✅ | ❌ | ✅ | ✅ | ✅ |
| Cupones / descuentos gestionados | 🟡 (`allowPromotionCodes`) | ✅ | 🟡 | 🟡 | 🟡 | 🟡 |
| Trial configurable | ✅ 14 d global | ✅ por plan | ✅ | ✅ | ✅ | ✅ |
| Portal de facturación | ✅ Stripe portal | ✅ propio | ✅ propio | ✅ | ✅ | ✅ |
| Factura PDF propia con datos fiscales | ❌ | ✅ | ✅ | 🟡 | 🟡 | 🟡 |
| Dunning / recuperación de pagos fallidos | ❌ | 🟡 | 🟡 | ❌ | ❌ | ❌ |
| **Métricas SaaS (MRR, churn, ARPU, conversión)** | ❌ | ✅ | ❌ | 🟡 | 🟡 | 🟡 |
| Feature gates por plan | ✅ | ✅ | ❌ | 🟡 | 🟡 | ✅ vía roles |
| **Feature flags por cuenta con override y caducidad** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

### 3.4 Go-to-market (el bloque donde perdemos)

| | base/tenant | SaaSykit | Larafast | JetShip | Wave |
|---|---|---|---|---|---|
| Landing page de marketing | 🟡 solo presale | ✅ | ✅ | ✅ | ✅ |
| Página de precios pública | 🟡 solo presale | ✅ | ✅ | ✅ | ✅ |
| Páginas legales (términos, privacidad, cookies) | 🟡 aceptación versionada, sin páginas | ✅ | ✅ | ✅ | ✅ |
| Blog / CMS | ❌ | ✅ | ✅ | ✅ | ✅ |
| SEO: metatags, Open Graph, schema.org | ❌ | ✅ | ✅ | ✅ | 🟡 |
| Sitemap automático | ❌ | ✅ | ✅ | 🟡 | 🟡 |
| Plantillas de email con marca | ❌ (markdown por defecto) | ✅ | 🟡 | ✅ | 🟡 |
| Changelog público | ❌ | 🟡 | ❌ | ✅ | ✅ |
| Roadmap / feedback con votación | ❌ | ❌ | ❌ | ✅ | ❌ |
| Anuncios programados en la app | ❌ | ✅ | ❌ | ❌ | ❌ |
| Analítica (GA4 / Plausible / PostHog) | ❌ | 🟡 | 🟡 | 🟡 | 🟡 |
| Programa de afiliados / referidos | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Pre-venta, waitlist y asientos fundadores** | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Onboarding declarativo (checklist)** | ✅ | ✅ plugin | ❌ | 🟡 | ❌ |

### 3.5 Capacidades de producto (el bloque donde ganamos)

| | base/tenant | SaaSykit | Larafast | JetShip | Wave | Spark |
|---|---|---|---|---|---|---|
| **Metering: contadores y gauges por cuenta** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Ficheros: subida directa a S3 sin pasar por PHP** | ✅ | ❌ | 🟡 S3 | ❌ | 🟡 | ❌ |
| Variantes de imagen y cuota por cuenta | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Import/export CSV con mapeo y fichero de rechazos** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Generador de módulos CRUD completo** | ✅ | ❌ | ❌ | ❌ | 🟡 plugins | ❌ |
| **Vault de credenciales de terceros con health check** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Webhooks salientes firmados con reintentos** | ✅ | ❌ | ❌ | 🟡 entrantes | ❌ | ❌ |
| **Idiomas activables en runtime + cobertura** | ✅ | 🟡 i18n | 🟡 i18n | 🟡 i18n | ❌ | ❌ |
| **Numeración correlativa por cuenta (sequences)** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Supresión global de envíos por webhook del proveedor** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **GDPR: export, purga programada, términos versionados** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Navegación en BBDD, reordenable y ocultable por cuenta** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Ajustes tipados por schema** | ✅ | 🟡 | ❌ | ❌ | 🟡 | ❌ |
| Auditoría por cuenta con filtrado de campos sensibles | ✅ | 🟡 | ❌ | ❌ | ❌ | ❌ |
| Notificaciones en BBDD + campana + digest diario | ✅ | 🟡 | 🟡 | 🟡 | 🟡 | ❌ |
| Tiempo real (Reverb / WebSockets) | ❌ polling | ❌ | ❌ | ❌ | ❌ | ❌ |
| Búsqueda global / paleta de comandos | ❌ | 🟡 Filament | 🟡 Filament | 🟡 Filament | 🟡 | ❌ |
| Papelera / restauración de borrados | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

### 3.6 API e integraciones

| | base/tenant | SaaSykit | Larafast | JetShip | Wave | Jetstream |
|---|---|---|---|---|---|---|
| API REST pública | ❌ | 🟡 | 🟡 | ❌ | ✅ | ❌ |
| API keys por cuenta, gestionadas en UI | ❌ | ❌ | ❌ | ❌ | 🟡 | ✅ Sanctum |
| Scopes y rate limit por plan | ❌ | ❌ | ❌ | ❌ | ❌ | 🟡 |
| Resolución de tenant desde token de API | ✅ (existe el resolver) | ❌ | ❌ | ❌ | ❌ | ❌ |
| Documentación OpenAPI | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Integración LLM / OpenAI lista | ❌ | 🟡 | ✅ | 🟡 | ❌ | ❌ |
| Servidor MCP para el producto | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Documentación escrita para agentes IA** | ✅ `docs/agents/` | ❌ | ❌ | ❌ | ❌ | ❌ |

### 3.7 Operación y DX

| | base/tenant | SaaSykit | Larafast | JetShip | Wave |
|---|---|---|---|---|---|
| Panel de administración de plataforma | 🟡 superadmin + switcher | ✅ Filament | ✅ Filament | ✅ Filament | ✅ Filament |
| Instalador guiado | ✅ | 🟡 | 🟡 | 🟡 | 🟡 |
| **Scaffold / eject: quedarte el código y quitar el paquete** | ✅ | ❌ | n/a | n/a | n/a |
| Docker / Sail preconfigurado | ❌ | 🟡 | 🟡 | 🟡 | 🟡 |
| Despliegue 1 clic (Forge / Ploi / Deployer) | ❌ | ✅ | 🟡 | 🟡 | 🟡 |
| Horizon / Pulse / Telescope preconfigurados | ❌ | 🟡 | 🟡 | 🟡 | 🟡 |
| Health checks de la aplicación | 🟡 solo connections | ❌ | ❌ | ❌ | ❌ |
| Backups automatizados | ❌ | ❌ | ❌ | ❌ | ❌ |
| Suite de tests del propio kit | ✅ 727 | 🟡 | 🟡 | 🟡 | 🟡 |
| Sistema de temas | ❌ | ❌ | 🟡 | ✅ | ✅ |
| Sistema de plugins de terceros | ❌ | ❌ | ❌ | ❌ | ✅ |
| Ficheros de diseño (Figma) | ❌ | ❌ | ❌ | ✅ | ❌ |
| Modo oscuro | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## 4. Los huecos, ordenados

### Tier 0 — Bloquean la venta B2B. Van en el paquete.

Ningún competidor Laravel los tiene. Es donde el trabajo rinde más.

1. **Superficie de API + API keys por cuenta.** Ya existe
   `ApiTokenTenantResolver` y Sanctum está en `composer.json`, pero no hay
   rutas de API, ni pantalla de gestión de claves, ni scopes, ni rate limit por
   plan, ni rotación, ni "última vez usada". Media docena de clientes B2B lo
   pedirán antes que ninguna otra cosa. Cierra el círculo con los webhooks
   salientes, que ya tenemos: hoy sabemos empujar eventos pero no dejamos que
   nadie nos consulte.
2. **SSO empresarial por tenant (SAML 2.0 y OIDC).** Es la funcionalidad que
   condiciona los contratos por encima de ~50 k$/año. Cada cuenta configura su
   IdP (Okta, Entra ID, Google Workspace); el `SocialAccount` y la regla
   anti-secuestro que ya tenemos son media base.
3. **Políticas de seguridad por cuenta.** Forzar 2FA a todos los miembros,
   restringir el registro a dominios de email concretos, caducidad de sesión,
   allowlist de IP, y gestión de sesiones activas con revocación. Barato de
   construir sobre lo que hay y aparece en todos los cuestionarios de compra.
4. **Panel de administración de plataforma.** Hoy hay superadmin y switcher,
   pero no un back-office: cuentas, suscripciones, ingresos, colas, feature
   flags globales, envío de emails. Todos los competidores lo venden como
   argumento principal, y todos lo hacen con Filament. Decisión previa:
   adoptar Filament para el panel de staff (rápido, estándar, se integra con
   nuestros permisos) o construirlo con Livewire + Flux como el resto.
5. **Facturación fiscal.** Datos fiscales por cuenta (razón social, NIF/VAT,
   dirección), validación VIES, factura PDF propia y numerada — donde
   `Sequence` encaja exactamente. Para el mercado español, Verifactu/TicketBAI
   sería un diferenciador que ni siquiera existe en el ecosistema.

### Tier 1 — Nos ponen al nivel de un kit comercial. Van en el starter kit.

6. **Front público completo**: landing, precios, FAQ, contacto, términos,
   privacidad y cookies. Hoy solo existe la landing de presale.
7. **SEO**: metatags, Open Graph, `schema.org`, `sitemap.xml`, `robots.txt`.
8. **Blog / CMS ligero.** Lo llevan los cinco. Es el motor de contenido y de
   posicionamiento del producto.
9. **Plantillas de email con marca.** Ahora mismo salen con el markdown por
   defecto de Laravel; los competidores heredan colores y logo.
10. **Métricas SaaS**: MRR, churn, ARPU, LTV, conversión de trial. Es *el*
    argumento diferencial que vende SaaSykit, y sobre Cashier no es difícil.
11. **Planes y cupones en base de datos**, editables desde el panel. Hoy los
    planes viven en `config/base-tenant.php:154` y cambiar precios es un deploy.
12. **Magic links** y ampliar el login social (GitHub, Apple, Facebook, GitLab,
    Slack). Tenemos tres proveedores; JetShip tiene ocho.
13. **Docker/Sail y receta de despliegue** (Forge/Ploi/Deployer).

### Tier 2 — Competitividad y madurez.

14. **Abstraer la pasarela de pago** para admitir Paddle o Lemon Squeezy como
    merchant of record. Delega el IVA y es lo que piden los que venden global.
15. **Pagos únicos** y **asientos sincronizados** con el proveedor.
16. **Dunning**: reintentos, aviso de tarjeta caducada, secuencia de impago.
17. **Tiempo real con Reverb** en lugar del polling de la campana.
18. **Búsqueda global y paleta de comandos.**
19. **Papelera y restauración** de registros borrados por cuenta.
20. **Changelog, roadmap con votos y anuncios programados.**
21. **Horizon, Pulse, health checks y backups** preconfigurados.
22. **Exportación de auditoría a SIEM** (JSON por webhook), retención
    configurable y legal holds. Complemento natural del Tier 0.
23. **SCIM** — solo cuando un cliente de ~1000 asientos lo pida.
24. **Servidor MCP del producto.** Nadie lo tiene y encaja con la apuesta que ya
    hicimos con `docs/agents/`.

### Lo que NO deberíamos construir

- Sistema de temas y de plugins (Wave, JetShip). Encarece el mantenimiento y
  nuestro comprador no lo pide.
- Perfiles públicos de usuario. Es un patrón B2C.
- Programa de afiliados. No lo tiene ninguno y no encaja en B2B.
- Base de datos por tenant. Nuestra apuesta por el global scope es coherente y
  está probada; ir a multi-BD es reescribir el eje entero para un puñado de
  clientes que probablemente no aparezcan.

---

## 5. Lo que tenemos y nadie más tiene

Merece la pena tenerlo listado porque es el argumento de venta, y porque explica
por qué no debemos medirnos con Larafast:

| | Comentario |
|---|---|
| Aislamiento real, propagado y **probado** | El `BelongsToAccount` + `Tenant::current()` + kit de aserciones no tiene equivalente comercial en Laravel |
| Permisos por cuenta que resuelven fuera de la sesión HTTP | En jobs, comandos y API. Los demás comprueban roles en sesión o no comprueban nada |
| Metering con límites bajo lock, 402 y reporte a Stripe Billing Meters | Ni SaaSykit ni Spark lo tienen. Es infraestructura de producto real |
| Feature flags por cuenta con override y caducidad | Los demás tienen gates por plan, que es otra cosa |
| Navegación en BBDD reordenable por cuenta | Nadie |
| Ajustes tipados por schema | Nadie |
| Sequences con bloqueo y reinicio por periodo | Nadie, y es requisito legal en cuanto emites documentos |
| Import/export con mapeo y fichero de rechazos | Nadie |
| Vault de credenciales con health check nocturno | Nadie |
| Webhooks salientes firmados con backoff y auto-desactivado | Nadie |
| Idiomas activables sin deploy con cobertura por locale | Nadie |
| GDPR completo | Nadie |
| Supresión global de envíos | Nadie |
| Pre-venta y waitlist con asientos fundadores | Nadie |
| Generador de módulos CRUD completo con tests y docs | Nadie |
| `scaffold` / `eject`: quedarte el código y borrar el paquete | Nadie. Elimina el miedo al vendor lock-in, que es la primera objeción a comprar un kit |
| Documentación escrita para agentes IA | Nadie |
| 727 tests | Ninguno publica su cobertura |

---

## 6. Recomendación

**Posicionamiento.** Dejar de compararnos con los "ship-fast kits". La frase que
nos describe es *"la base multi-tenant para B2B que además llega lista para
enterprise"*. El comprador no es el indie hacker de 149 $, es el equipo que va a
firmar con clientes que exigen SSO, auditoría y aislamiento demostrable.

**Reparto entre repos.**
- Va en `base/tenant`: API + keys, SSO/OIDC, políticas de seguridad por cuenta,
  facturación fiscal, planes en BBDD, abstracción de pasarela, papelera,
  export de auditoría, Reverb.
- Va en `base-tenant-kit`: landing, precios, legales, blog, SEO, sitemap,
  plantillas de email, dashboard de métricas, Docker y despliegue, analítica.
- Decisión abierta: el panel de plataforma. Si es Filament, es una dependencia
  nueva y pesada del paquete; si es Livewire + Flux, mantiene la coherencia
  visual pero cuesta más. Mi inclinación: Livewire + Flux, porque ya tenemos el
  patrón de tabla resuelto y el panel de staff no justifica arrastrar Filament
  al paquete.

**Orden sugerido.** API y keys → políticas de seguridad por cuenta → panel de
plataforma → front público y SEO → métricas SaaS → planes en BBDD → SSO
SAML/OIDC → facturación fiscal → el resto por demanda.

Los tres primeros desbloquean ventas; el cuarto y el quinto hacen el kit
presentable; el resto se prioriza contra clientes reales, no contra la lista de
funcionalidades de un competidor.

---

## 7. Fuentes

- [JetShip — Laravel Starter Kit PRO (demo y features)](https://demos.themeselection.com/jetship-laravel-starter-kit/)
- [JetShip: Ultimate Laravel SaaS Boilerplate – Features & Benefits](https://themeselection.com/blog/jetship-ultimate-laravel-saas-boilerplate-features-benefits/)
- [Naimul007A/laravel-base-kit](https://github.com/Naimul007A/laravel-base-kit)
- [Laravel Spark](https://spark.laravel.com/)
- [SaaSykit](https://saasykit.com/)
- [SaaSykit Tenancy](https://saasykit.com/multi-tenancy)
- [SaaSykit — Best Laravel Multi-Tenant SaaS Starter Kits](https://saasykit.com/blog/best-laravel-multi-tenant-saas-starter-kits-for-2025)
- [SaaSykit — 10 Best Laravel Starter Kits](https://saasykit.com/blog/10-best-laravel-starter-kits-for-2025)
- [Larafast](https://larafast.com/)
- [LaraCopilot — Laravel Starter Kit 2026: Which One Should You Choose?](https://laracopilot.com/blog/laravel-starter-kit/)
- [StarterPick — Best Laravel SaaS Starters 2026](https://starterpick.com/blog/best-laravel-saas-starters-larafast-saasykit-2026)
- [Wave (DevDojo)](https://devdojo.com/wave)
- [Tenancy for Laravel — SaaS Boilerplate](https://tenancyforlaravel.com/saas-boilerplate)
- [WorkOS — Enterprise readiness checklist 2026](https://workos.com/blog/enterprise-readiness-checklist-2026)
- [SSOJet — Critical audit log events for B2B SaaS](https://ssojet.com/blog/critical-audit-log-events-b2b-saas-enterprise)
- [CIAM Compass — B2B SaaS Identity: Organizations, SSO, SCIM](https://guptadeepak.com/ciam-compass/guides/b2b-saas-identity/)
- [Auth0 B2B SaaS Starter Kit (referencia de features enterprise)](https://github.com/auth0-developer-hub/auth0-b2b-saas-starter)
