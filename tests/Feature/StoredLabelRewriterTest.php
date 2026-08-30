<?php

declare(strict_types=1);

use Base\Tenant\Console\Support\StoredLabelRewriter;
use Base\Tenant\Facades\Menu;
use Base\Tenant\Models\MenuItem;

/**
 * Las etiquetas de los menús no viven en el código: `Menu::sync()` las escribe
 * en la base de datos con el prefijo del paquete. El scaffold reescribe código
 * y vistas, así que sin este paso la aplicación separada queda con claves
 * `base-tenant::` que ya no resuelven y el menú muestra la clave en crudo.
 */
beforeEach(function () {
    $this->syncPermissions();
    Menu::sync();
});

test('reescribe el prefijo de las etiquetas guardadas', function () {
    expect(MenuItem::where('label', 'like', 'base-tenant::%')->count())->toBeGreaterThan(0);

    $reescritas = (new StoredLabelRewriter)->rewrite();

    expect($reescritas)->toBeGreaterThan(0)
        ->and(MenuItem::where('label', 'like', 'base-tenant::%')->count())->toBe(0)
        ->and(MenuItem::where('key', 'dashboard')->value('label'))
        ->toBe('tenant::app.navigation.dashboard');
});

test('no toca las etiquetas que el usuario ha escrito a mano', function () {
    $propia = MenuItem::where('key', 'users')->first();
    $propia->update(['label' => 'Mi gente']);

    (new StoredLabelRewriter)->rewrite();

    expect($propia->fresh()->label)->toBe('Mi gente');
});

test('aplicarlo dos veces no cambia nada la segunda', function () {
    $reescritor = new StoredLabelRewriter;

    $reescritor->rewrite();

    expect($reescritor->rewrite())->toBe(0);
});

/**
 * El árbol se cachea, así que reescribir las filas sin vaciar la caché deja la
 * pantalla mostrando las claves viejas hasta que algo más la invalide.
 */
test('vacía la caché del menú', function () {
    Menu::tree('main');

    (new StoredLabelRewriter)->rewrite();

    expect(Menu::tree('main')->firstWhere('key', 'dashboard')['title'])
        ->not->toContain('base-tenant::');
});
