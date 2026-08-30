<?php

declare(strict_types=1);

use Base\Tenant\Livewire\Notifications\Index as NotificationsIndex;
use Illuminate\Notifications\Notification;
use Livewire\Livewire;

beforeEach(function () {
    // El paquete no compila sus assets para las pruebas: sin esto `@vite` del
    // armazón revienta buscando un manifiesto que aquí nunca existe.
    $this->withoutVite();

    $this->syncPermissions();
});

/**
 * Llena la bandeja de quien mira y devuelve el HTML de la pantalla.
 *
 * Los ayudantes de `TestCase` son protegidos, así que se llama al closure desde
 * dentro del caso; y va como closure y no como función suelta porque Pest carga
 * todos los ficheros de prueba en el mismo proceso y dos funciones con el mismo
 * nombre chocarían.
 */
$pintarBandeja = function (int $cuantas): string {
    $cuenta = $this->createAccount();
    $usuario = $this->createUser($cuenta, 'customer-admin');

    $this->actingAsTenant($usuario, $cuenta);

    foreach (range(1, $cuantas) as $numero) {
        $usuario->notify(new AvisoDeLista($numero));
    }

    return Livewire::test(NotificationsIndex::class)->html();
};

/**
 * El esqueleto se pinta en el sitio de la lista, así que una forma que no case
 * con la real hace saltar el bloque entero al aparecer y al desaparecer. Se
 * cuentan las filas de los dos bloques y se comparan; no importa cuántas sean,
 * importa que coincidan.
 */
test('el esqueleto de carga de notificaciones pinta tantas filas como la lista real', function () use ($pintarBandeja) {
    $html = $pintarBandeja->call($this, 3);

    // Anclado a los dos bloques reales: el de carga y el que se ve el resto del
    // tiempo. `wire:loading.remove.delay` no casa con el patrón del primero
    // porque tras `wire:loading.` viene `remove`.
    preg_match('/<div wire:loading\.delay.*?(?=<div wire:loading\.remove)/s', $html, $esqueleto);
    preg_match('/<div wire:loading\.remove\.delay.*/s', $html, $lista);

    expect($esqueleto)->not->toBeEmpty()->and($lista)->not->toBeEmpty();

    $filasDeEsqueleto = substr_count($esqueleto[0], 'data-skeleton-row');
    $filasReales = substr_count($lista[0], 'data-notification-row');

    // Con el número delante: comparar dos ceros entre sí daría verde sin que se
    // pinte una sola fila.
    expect($filasReales)->toBe(3)
        ->and($filasDeEsqueleto)->toBe($filasReales);

    // Y la misma anatomía dentro de la fila: casilla, punto de prioridad,
    // bloque de texto de tres líneas y hueco de acciones.
    preg_match('/<div data-skeleton-row.*?(?=<div data-skeleton-row|$)/s', $esqueleto[0], $filaDeEsqueleto);

    expect(substr_count($filaDeEsqueleto[0], 'data-flux-skeleton'))->toBe(6);
});

/**
 * El fallo que deja el esqueleto sin aparecer nunca y no lo nota nadie: un
 * `wire:target` que nombra una propiedad o una acción que el componente no
 * tiene. Livewire no avisa, simplemente no hay nada que esperar.
 */
test('el esqueleto de notificaciones apunta solo a acciones que existen', function () use ($pintarBandeja) {
    $html = $pintarBandeja->call($this, 1);

    preg_match('/<div wire:loading\.delay wire:target="([^"]+)"/', $html, $encontrado);

    expect($encontrado)->not->toBeEmpty();

    $objetivos = explode(',', $encontrado[1]);

    // Que la lista no se haya quedado en un nombre suelto: con uno solo, la
    // comprobación de abajo pasaría sin cubrir casi nada.
    expect($objetivos)->toHaveCount(11);

    $reflexion = new ReflectionClass(NotificationsIndex::class);
    $huerfanos = [];

    foreach ($objetivos as $objetivo) {
        $nombre = trim($objetivo);

        if ($reflexion->hasMethod($nombre) || $reflexion->hasProperty($nombre)) {
            continue;
        }

        $huerfanos[] = $nombre;
    }

    expect($huerfanos)->toBe([]);
});

/**
 * Notificación mínima para poder llenar la bandeja, con título distinto en cada
 * una para que las filas no se confundan entre sí.
 */
class AvisoDeLista extends Notification
{
    public function __construct(private int $numero) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title' => 'Aviso '.$this->numero,
            'message' => 'Cuerpo del aviso '.$this->numero,
            'priority' => 'high',
        ];
    }
}
