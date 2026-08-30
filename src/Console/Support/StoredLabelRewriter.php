<?php

declare(strict_types=1);

namespace Base\Tenant\Console\Support;

use Base\Tenant\Facades\Menu;
use Base\Tenant\Models\MenuItem;
use Illuminate\Support\Facades\DB;

/**
 * Reescribe las claves de traducción que están guardadas en la base de datos.
 *
 * `CodeTransformer` cubre ficheros, pero las etiquetas de los menús no viven en
 * el código: `Menu::sync()` las escribe en `menu_items.label` con el prefijo del
 * paquete. Al separar el código, ese espacio de nombres deja de existir y el
 * menú pasa a mostrar `base-tenant::app.navigation.dashboard` en crudo.
 *
 * Solo se tocan las filas cuyo prefijo coincide: una etiqueta que el usuario
 * haya escrito a mano no es una clave de traducción y se queda como está.
 */
class StoredLabelRewriter
{
    public function __construct(
        protected string $sourceHandle = 'base-tenant',
        protected string $targetHandle = 'tenant',
    ) {}

    /**
     * @return int Filas reescritas.
     */
    public function rewrite(): int
    {
        $prefijo = "{$this->sourceHandle}::";

        $reescritas = MenuItem::query()
            ->where('label', 'like', $prefijo.'%')
            ->update([
                'label' => DB::raw($this->replaceExpression('label', $prefijo)),
            ]);

        if ($reescritas > 0) {
            // El árbol se cachea por cuenta: sin vaciarla, la pantalla sigue
            // mostrando las claves viejas hasta que algo más la invalide.
            Menu::flush();
        }

        return $reescritas;
    }

    /**
     * `REPLACE` existe con la misma firma en SQLite, MySQL, MariaDB y Postgres,
     * que son los cuatro motores que el instalador ofrece.
     */
    protected function replaceExpression(string $column, string $prefijo): string
    {
        $desde = DB::getPdo()->quote($prefijo);
        $hasta = DB::getPdo()->quote("{$this->targetHandle}::");

        return "REPLACE({$column}, {$desde}, {$hasta})";
    }
}
