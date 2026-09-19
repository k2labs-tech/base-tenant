<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Support;

/**
 * Conecta el `app.css` de la aplicación con el paquete: importa el tema y
 * declara las rutas que Tailwind tiene que escanear para no descartar las
 * clases que solo aparecen en las vistas del paquete.
 *
 * Las dos direcciones del cableado viven aquí: `apply()` la monta al instalar y
 * `detach()` la desmonta al separar el paquete. Juntas porque el import y las
 * rutas tienen que moverse a la vez; repartidas entre dos comandos, una de las
 * mitades se queda atrás.
 *
 * Idempotente en ambos sentidos: los comandos pueden volver a pasar sobre un
 * fichero ya tocado.
 */
class StylesheetManager
{
    protected const IMPORT = "@import '../../vendor/k2labs/base-tenant/resources/css/base-tenant.css';";

    /**
     * Hasta la 3.0 el paquete se llamaba `base/tenant` y vivía en
     * `vendor/base/tenant/`. Una aplicación instalada entonces conserva estas
     * rutas, que tras el cambio de nombre apuntan a un directorio que ya no
     * existe: se reconocen para migrarlas y para poder separar el paquete.
     */
    protected const LEGACY_IMPORT = "@import '../../vendor/base/tenant/resources/css/base-tenant.css';";

    protected const LEGACY_SOURCE_PATTERN = '~^[^\S\r\n]*@source\s+(["\'])(?:[^"\']*/)?vendor/base/tenant/resources/views[^"\']*\1;[^\S\r\n]*\R?~m';

    /**
     * Flux no inyecta su hoja de estilos con una directiva: la aplicación tiene
     * que importarla, como dice su propio README. Sin ella no existe el
     * `grid-template-areas` que coloca barra lateral, cabecera y contenido, así
     * que los tres se apilan en vertical y ningún componente lleva estilo.
     */
    protected const FLUX_IMPORT = "@import '../../vendor/livewire/flux/dist/flux.css';";

    /**
     * Tailwind v4 resuelve `dark:` con `prefers-color-scheme` si nadie dice lo
     * contrario, así que las utilidades seguirían al sistema operativo mientras
     * el conmutador de Flux pone la clase `.dark` en el `<html>`. El resultado
     * es el peor posible: los componentes de Flux cambian, porque su hoja usa
     * `.dark` por dentro, y el markup propio no.
     */
    protected const DARK_VARIANT = '@custom-variant dark (&:where(.dark, .dark *));';

    /** Junto al `app.css` de la aplicación, una vez el paquete ya no está. */
    protected const LOCAL_IMPORT = "@import './base-tenant.css';";

    /** Enlazado en desarrollo y en vendor en producción: se declaran los dos. */
    protected const SOURCES = [
        "@source '../../base-tenant/resources/views/**/*.blade.php';",
        "@source '../../vendor/k2labs/base-tenant/resources/views/**/*.blade.php';",
    ];

    /**
     * Cubre también las rutas escritas a mano, que no tienen por qué coincidir
     * carácter a carácter con las de `SOURCES`: comillas dobles y sangría
     * incluidas.
     *
     * `base[\/-]tenant` no es un adorno: enlazado el directorio se llama
     * `base-tenant`, dentro de `vendor/` es `k2labs/base-tenant` y antes de la
     * 3.0 era `base/tenant`. Buscar solo una forma deja otra puesta, apuntando
     * a un directorio que ya no existe. El segmento va anclado a un `/` o a la propia comilla
     * para no llevarse por delante un `packages/mybase/tenant/`, que es de otro.
     */
    protected const SOURCE_PATTERN = '~^[^\S\r\n]*@source\s+(["\'])(?:[^"\']*/)?base[/-]tenant/resources/views[^"\']*\1;[^\S\r\n]*\R?~m';

    /** `source(none)` y `theme(...)` son variantes documentadas de Tailwind v4. */
    protected const TAILWIND_PATTERN = '~^[^\S\r\n]*@import\s+["\']tailwindcss["\'][^;]*;~m';

    /** Cualquier `@import`, para saber dónde acaba el bloque de la cabecera. */
    protected const ANY_IMPORT_PATTERN = '~^[^\S\r\n]*@import\b[^;]*;~m';

    public function apply(string $contents): string
    {
        $contents = $this->migrateLegacyPaths($contents);
        $contents = $this->addImport($contents);

        // Después del tema a propósito: ambos se insertan justo detrás de
        // Tailwind, así que el último en entrar queda delante. El orden que
        // resulta es tailwind → flux → tema, que es el que Flux documenta y el
        // que deja al tema del paquete con la última palabra.
        $contents = $this->addFluxImport($contents);
        $contents = $this->addDarkVariant($contents);

        return $this->addSources($contents);
    }

    /**
     * Desconecta el `app.css` del paquete: el import pasa a apuntar a la copia
     * que el comando de separación deja junto a él y las rutas de escaneo se
     * retiran, porque las vistas copiadas caen dentro del `@source` propio de
     * la aplicación.
     *
     * El tema no se elimina: las vistas copiadas siguen usando las escalas de
     * estado, y quitarlo las dejaría sin color en lugar de romper la
     * compilación, que es el fallo más difícil de ver.
     */
    public function detach(string $contents): string
    {
        if (! $this->isWired($contents)) {
            return $contents;
        }

        $contents = $this->localiseImport($contents);

        return preg_replace(self::SOURCE_PATTERN, '', $contents) ?? $contents;
    }

    /**
     * Si no hay ni import ni rutas del paquete, el fichero no lo tocó el
     * instalador y se devuelve intacto.
     */
    protected function isWired(string $contents): bool
    {
        return str_contains($contents, self::IMPORT)
            || str_contains($contents, self::LEGACY_IMPORT)
            || preg_match(self::SOURCE_PATTERN, $contents) === 1;
    }

    /**
     * Lleva un `app.css` escrito por una versión anterior a la 3.0 a las rutas
     * de `vendor/k2labs/base-tenant/`. El import se reescribe en su sitio y las
     * rutas de escaneo antiguas se retiran; `addSources()` pone las nuevas.
     */
    protected function migrateLegacyPaths(string $contents): string
    {
        $contents = str_replace(self::LEGACY_IMPORT, self::IMPORT, $contents);

        return preg_replace(self::LEGACY_SOURCE_PATTERN, '', $contents) ?? $contents;
    }

    protected function localiseImport(string $contents): string
    {
        foreach ([self::IMPORT, self::LEGACY_IMPORT] as $import) {
            if (str_contains($contents, $import)) {
                return str_replace($import, self::LOCAL_IMPORT, $contents);
            }
        }

        if (str_contains($contents, self::LOCAL_IMPORT)) {
            return $contents;
        }

        // Instalaciones anteriores a la publicación del fichero de tema: solo
        // recibieron las rutas de escaneo. Sin el import se quedarían sin las
        // escalas de estado que usan las vistas copiadas.
        return $this->insertAfterTailwind($contents, self::LOCAL_IMPORT);
    }

    /**
     * Va justo detrás del import de Tailwind: antes, las utilidades del tema no
     * llegarían a generarse.
     */
    protected function addImport(string $contents): string
    {
        if (str_contains($contents, self::IMPORT)) {
            return $contents;
        }

        // Volver a instalar sobre una aplicación ya separada: el import apunta
        // a la copia local y hay que devolverlo a vendor/, que es de donde sale
        // el tema mientras el paquete siga puesto.
        if (str_contains($contents, self::LOCAL_IMPORT)) {
            return str_replace(self::LOCAL_IMPORT, self::IMPORT, $contents);
        }

        return $this->insertAfterTailwind($contents, self::IMPORT);
    }

    /**
     * Va detrás de los imports, que es donde CSS exige que estén, y solo si la
     * aplicación no la ha declarado ya con cualquier redacción.
     */
    protected function addDarkVariant(string $contents): string
    {
        if (str_contains($contents, '@custom-variant dark')) {
            return $contents;
        }

        if (preg_match_all(self::ANY_IMPORT_PATTERN, $contents, $matches, PREG_OFFSET_CAPTURE)) {
            $ultimo = end($matches[0]);

            return $this->insertAt($contents, $ultimo[1] + strlen($ultimo[0]), self::DARK_VARIANT);
        }

        return self::DARK_VARIANT.$this->lineEnding($contents).$contents;
    }

    /**
     * La aplicación puede tenerlo ya puesto por su cuenta —el starter kit de
     * Laravel lo trae— así que se comprueba antes de escribir nada.
     */
    protected function addFluxImport(string $contents): string
    {
        if (str_contains($contents, 'flux/dist/flux.css')) {
            return $contents;
        }

        return $this->insertAfterTailwind($contents, self::FLUX_IMPORT);
    }

    protected function insertAfterTailwind(string $contents, string $import): string
    {
        if (preg_match(self::TAILWIND_PATTERN, $contents, $match, PREG_OFFSET_CAPTURE)) {
            return $this->insertAt($contents, $match[0][1] + strlen($match[0][0]), $import);
        }

        // Sin import de Tailwind, detrás del último `@import`: CSS exige que
        // todos vayan al principio de la hoja, antes de cualquier regla.
        if (preg_match_all(self::ANY_IMPORT_PATTERN, $contents, $matches, PREG_OFFSET_CAPTURE)) {
            $ultimo = end($matches[0]);

            return $this->insertAt($contents, $ultimo[1] + strlen($ultimo[0]), $import);
        }

        return $import.$this->lineEnding($contents).$contents;
    }

    protected function insertAt(string $contents, int $offset, string $import): string
    {
        $salto = $this->lineEnding($contents);

        return substr($contents, 0, $offset).$salto.$import.substr($contents, $offset);
    }

    protected function addSources(string $contents): string
    {
        $salto = $this->lineEnding($contents);

        foreach (self::SOURCES as $source) {
            if (! str_contains($contents, $source)) {
                $contents = rtrim($contents).$salto.$source.$salto;
            }
        }

        return $contents;
    }

    /**
     * Un `app.css` con finales CRLF se queda en CRLF: mezclarlos deja el
     * fichero con saltos de dos tipos y ensucia el diff entero.
     */
    protected function lineEnding(string $contents): string
    {
        return str_contains($contents, "\r\n") ? "\r\n" : "\n";
    }
}
