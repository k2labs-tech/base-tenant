# Frontend

## Cómo se compilan los estilos

El paquete no compila nada por su cuenta: los estilos los construye la
aplicación con Tailwind v4, y el paquete solo aporta dos cosas al `app.css`.

El instalador las añade automáticamente:

```css
@import 'tailwindcss';
@import '../../vendor/base/tenant/resources/css/base-tenant.css';

@source '../../base-tenant/resources/views/**/*.blade.php';
@source '../../vendor/base/tenant/resources/views/**/*.blade.php';
```

El `@import` trae el tema. Los `@source` le dicen a Tailwind que mire dentro de
las vistas del paquete, o descartaría todas las clases que solo aparecen ahí.
Se declaran las dos rutas porque el paquete puede estar enlazado en desarrollo
o instalado en `vendor/`.

## Vocabulario de color

| Uso | Escala | Quién la define |
|---|---|---|
| Superficies y texto | `zinc` | Tailwind |
| Acción principal | `accent` | El paquete (violeta) |
| Estados | `success`, `warning`, `danger`, `info` | El paquete |

Flux no trae escala numérica de `accent`: define tres tokens planos
(`--color-accent`, `--color-accent-content`, `--color-accent-foreground`) que
usan sus propios componentes. Las vistas del paquete usan tonos numéricos, así
que la escala la aporta el tema.

En las vistas no se usan escalas crudas de Tailwind (`gray`, `red`, `green`…):
hay un test que falla si aparece una, y otro que falla si se usa una escala que
el tema no declara.

## Cambiar la paleta

Redefine los tokens en el `@theme` de tu aplicación, después del import.
Tailwind fusiona los bloques `@theme` en orden de aparición, así que gana el
último:

```css
@theme {
    --color-accent-500: var(--color-emerald-500);
    --color-accent-600: var(--color-emerald-600);
}
```

## Modo claro y oscuro

Los componentes de Flux traen ambos modos resueltos, y las vistas del paquete
también: `DarkModeTest` recorre todas y exige que cada superficie clara lleve su
pareja oscura dentro del mismo ámbito de clases.

## Componentes

Los formularios, tablas, modales y navegación son componentes de
[Flux UI](https://fluxui.dev) en su versión gratuita: `flux:input`,
`flux:select`, `flux:checkbox`, `flux:radio`, `flux:textarea`, `flux:button`,
`flux:modal`, `flux:callout`, `flux:separator`, `flux:heading` y
`flux:subheading`.

Un campo de Flux con `label` monta su propio `flux:field`, y con él la etiqueta
y el hueco del error. Un control **sin** etiqueta —un grupo de casillas, por
ejemplo— no lo monta, así que su mensaje de validación no se pinta en ninguna
parte: en esos casos hay que pedir el hueco a mano con
`<flux:error name="campo" />`.

De los componentes Blade propios sólo queda uno en
`resources/views/components/`: `application-logo`. El resto los sustituyen las
primitivas de Flux.

No se registra ninguna ruta de componentes anónimos a propósito: el espacio de
nombres de vistas ya resuelve `<x-base-tenant::application-logo>`, mientras que
una ruta registrada respondería también al `<x-application-logo>` a secas, un
nombre que la aplicación anfitriona puede querer para sí.
