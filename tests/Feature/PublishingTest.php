<?php

declare(strict_types=1);

use Base\Tenant\BaseTenantServiceProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Una etiqueta de publicación que no copia nada no falla: `vendor:publish`
 * termina en verde, no escribe un solo fichero y nadie se entera. Así vivió
 * meses `base-tenant-assets`, apuntando a un `public/` que no existe, con el
 * instalador ejecutándola en cada instalación.
 *
 * El fallo se persigue por el origen, no por el nombre de la etiqueta: se
 * exige que toda ruta registrada exista Y tenga contenido. Comprobar sólo que
 * `base-tenant-assets` no vuelva dejaría pasar la misma avería con otro
 * nombre, y comprobar sólo la existencia daría por buena una carpeta vacía,
 * que copia exactamente lo mismo: nada.
 */
test('toda ruta publicable del paquete existe y tiene contenido', function () {
    $grupos = array_filter(
        ServiceProvider::publishableGroups(),
        fn (string $grupo): bool => str_starts_with($grupo, 'base-tenant')
    );

    expect($grupos)->not->toBeEmpty('El proveedor no registró ninguna etiqueta de publicación.');

    foreach ($grupos as $grupo) {
        $rutas = ServiceProvider::pathsToPublish(BaseTenantServiceProvider::class, $grupo);

        expect($rutas)->not->toBeEmpty("La etiqueta `{$grupo}` no declara ningún origen.");

        foreach (array_keys($rutas) as $origen) {
            expect(file_exists($origen))->toBeTrue(
                "La etiqueta `{$grupo}` publica `{$origen}`, que no existe: copiaría cero ficheros."
            );

            if (is_dir($origen)) {
                expect(glob($origen.'/*') ?: [])->not->toBeEmpty(
                    "La etiqueta `{$grupo}` publica `{$origen}`, un directorio vacío: copiaría cero ficheros."
                );
            }
        }
    }
});
