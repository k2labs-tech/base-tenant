<?php

declare(strict_types=1);

use Base\Tenant\Connections\WebhookManager;
use Base\Tenant\Connections\Webhooks\PayloadBuilder;
use Base\Tenant\Events\WebhookEndpointFailing;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Facades\Webhook;
use Base\Tenant\Jobs\DeliverWebhook;
use Base\Tenant\Models\OutboundWebhook;
use Base\Tenant\Models\OutboundWebhookDelivery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * Un sobre propio, como el de un contrato público con los clientes.
 */
class SobreDePrueba implements PayloadBuilder
{
    public function build(OutboundWebhookDelivery $delivery, OutboundWebhook $webhook): array
    {
        return [
            'type' => $delivery->event,
            'id' => $delivery->getKey(),
            'payload' => $delivery->payload,
        ];
    }
}

class NoEsUnSobre {}

class EndpointDeLaApp extends OutboundWebhook {}

class EntregaDeLaApp extends OutboundWebhookDelivery {}

/**
 * Registra un endpoint y lanza un evento en una cuenta nueva.
 *
 * @return array{0: OutboundWebhook, 1: OutboundWebhookDelivery}
 */
function endpointConEntrega(string $evento = 'booking.confirmed', array $datos = ['id' => 7]): array
{
    $cuenta = test()->createAccount();

    return Tenant::runFor($cuenta, function () use ($evento, $datos): array {
        $webhook = Webhook::register('https://uno.test/hook', ['*']);

        return [$webhook, Webhook::dispatch($evento, $datos)->first()];
    });
}

function entregar(OutboundWebhookDelivery|string $entrega): void
{
    $id = $entrega instanceof OutboundWebhookDelivery ? $entrega->getKey() : $entrega;

    (new DeliverWebhook($id))->handle(app(WebhookManager::class));
}

// -----------------------------------------------------------------------
// Sin configurar: lo mismo que en 3.0
// -----------------------------------------------------------------------

test('sin configurar, cabeceras, firma y sobre son los de 3.0', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    [$webhook, $entrega] = endpointConEntrega();

    entregar($entrega);

    $secreto = $webhook->fresh()->secret;

    Http::assertSent(function (Request $request) use ($secreto, $entrega): bool {
        $cuerpo = json_decode($request->body(), true);

        return $request->header('X-BaseTenant-Signature')[0] === hash_hmac('sha256', $request->body(), $secreto)
            && $request->header('X-BaseTenant-Event')[0] === 'booking.confirmed'
            && $request->header('X-BaseTenant-Delivery')[0] === $entrega->getKey()
            && array_keys($cuerpo) === ['event', 'delivery', 'occurred_at', 'data']
            && $cuerpo['delivery'] === $entrega->getKey()
            && $cuerpo['data'] === ['id' => 7];
    });
});

test('sin configurar, reintentos, timeout y límite son los de 3.0', function () {
    $webhooks = app(WebhookManager::class);

    expect($webhooks->attempts())->toBe(5)
        ->and(array_map(fn (int $intento): ?int => $webhooks->retryDelay($intento), [1, 2, 3, 4, 5]))
        ->toBe([300, 1800, 7200, 43200, null])
        ->and($webhooks->timeout())->toBe(15)
        ->and((new DeliverWebhook('x'))->timeout)->toBe(30)
        ->and($webhooks->failureLimit())->toBe(20)
        ->and($webhooks->failureAction())->toBe('disable')
        ->and($webhooks->endpointModel())->toBe(OutboundWebhook::class)
        ->and($webhooks->deliveryModel())->toBe(OutboundWebhookDelivery::class);

    // El 3.0 leía BACKOFF[$attempt] con el contador ya incrementado: el
    // horario efectivo era éste, y la constante sigue dando lo mismo.
    foreach ([1, 2, 3, 4, 5] as $intento) {
        expect($webhooks->retryDelay($intento))->toBe(OutboundWebhookDelivery::BACKOFF[$intento] ?? null);
    }
});

test('una aplicación con la configuración de 3.0 publicada sigue funcionando', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    // El array `webhooks` de una config publicada en 3.0 sustituye entero al
    // del paquete: sólo trae el interruptor.
    config(['base-tenant.webhooks' => ['enabled' => true]]);

    [, $entrega] = endpointConEntrega();

    entregar($entrega);

    expect(app(WebhookManager::class)->headers())->toBe([
        'signature' => 'X-BaseTenant-Signature',
        'event' => 'X-BaseTenant-Event',
        'delivery' => 'X-BaseTenant-Delivery',
    ])->and(app(WebhookManager::class)->failureLimit())->toBe(20)
        ->and($entrega->fresh()->status)->toBe(OutboundWebhookDelivery::DELIVERED);
});

// -----------------------------------------------------------------------
// Cabeceras y firma
// -----------------------------------------------------------------------

test('los nombres de las cabeceras y el prefijo de la firma se configuran', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    config([
        'base-tenant.webhooks.headers' => [
            'signature' => 'X-Check-Signature',
            'event' => 'X-Check-Event',
            'delivery' => 'X-Check-Delivery',
        ],
        'base-tenant.webhooks.signature_prefix' => 'sha256=',
    ]);

    [$webhook, $entrega] = endpointConEntrega();

    entregar($entrega);

    $secreto = $webhook->fresh()->secret;

    Http::assertSent(function (Request $request) use ($secreto, $entrega): bool {
        return $request->header('X-Check-Signature')[0] === 'sha256='.hash_hmac('sha256', $request->body(), $secreto)
            && $request->header('X-Check-Event')[0] === 'booking.confirmed'
            && $request->header('X-Check-Delivery')[0] === $entrega->getKey()
            && ! $request->hasHeader('X-BaseTenant-Signature')
            && Webhook::verify($request->body(), $secreto, $request->header('X-Check-Signature')[0]);
    });
});

test('verify rechaza lo que no es la firma exacta del cuerpo crudo', function () {
    config(['base-tenant.webhooks.signature_prefix' => 'sha256=']);

    $cuerpo = '{"event":"a.b"}';
    $firma = Webhook::sign($cuerpo, 'secreto');

    expect($firma)->toBe('sha256='.hash_hmac('sha256', $cuerpo, 'secreto'))
        ->and(Webhook::verify($cuerpo, 'secreto', $firma))->toBeTrue()
        ->and(Webhook::verify($cuerpo, 'secreto', null))->toBeFalse()
        ->and(Webhook::verify($cuerpo, 'secreto', ''))->toBeFalse()
        ->and(Webhook::verify($cuerpo, 'secreto', hash_hmac('sha256', $cuerpo, 'secreto')))->toBeFalse()
        ->and(Webhook::verify($cuerpo, 'otro', $firma))->toBeFalse()
        // Volver a codificar cambia los bytes: la firma es del cuerpo crudo.
        ->and(Webhook::verify(json_encode(json_decode($cuerpo), JSON_PRETTY_PRINT), 'secreto', $firma))->toBeFalse();
});

// -----------------------------------------------------------------------
// Sobre
// -----------------------------------------------------------------------

test('un constructor de cuerpo propio define el sobre, y se firma lo que se manda', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    config(['base-tenant.webhooks.payload_builder' => SobreDePrueba::class]);

    [$webhook, $entrega] = endpointConEntrega();

    entregar($entrega);

    $secreto = $webhook->fresh()->secret;

    Http::assertSent(function (Request $request) use ($secreto, $entrega): bool {
        return $request->body() === json_encode(['type' => 'booking.confirmed', 'id' => $entrega->getKey(), 'payload' => ['id' => 7]])
            && Webhook::verify($request->body(), $secreto, $request->header(WebhookManager::SIGNATURE_HEADER)[0]);
    });
});

test('un constructor que no implementa la interfaz se rechaza', function () {
    config(['base-tenant.webhooks.payload_builder' => NoEsUnSobre::class]);

    expect(fn () => app(WebhookManager::class)->payloadBuilder())
        ->toThrow(InvalidArgumentException::class);
});

test('los reintentos mandan los mismos bytes que el primer intento', function () {
    Bus::fake([DeliverWebhook::class]);

    $responde = 500;

    Http::fake(function () use (&$responde) {
        return Http::response('x', $responde);
    });

    [, $entrega] = endpointConEntrega();

    entregar($entrega);

    // Cambiar el sobre entre intentos no cambia lo que ya se firmó y se mandó.
    config(['base-tenant.webhooks.payload_builder' => SobreDePrueba::class]);
    $responde = 200;

    entregar($entrega);

    $cuerpos = Http::recorded()->map(fn (array $par): string => $par[0]->body())->all();

    expect($cuerpos)->toHaveCount(2)
        ->and($cuerpos[0])->toBe($cuerpos[1])
        ->and($entrega->fresh()->body)->toBe($cuerpos[0]);
});

// -----------------------------------------------------------------------
// Reintentos
// -----------------------------------------------------------------------

test('el número de intentos, la espera y el timeout se configuran', function () {
    Bus::fake([DeliverWebhook::class]);
    Http::fake(['*' => Http::response('vaya', 500)]);
    $this->freezeSecond();

    config([
        'base-tenant.webhooks.attempts' => 3,
        'base-tenant.webhooks.backoff' => [10],
        'base-tenant.webhooks.timeout' => 60,
    ]);

    expect((new DeliverWebhook('x'))->timeout)->toBe(75);

    [, $entrega] = endpointConEntrega();

    entregar($entrega);
    expect($entrega->fresh()->next_attempt_at->diffInSeconds(now()->addSeconds(10)))->toEqual(0);

    entregar($entrega);
    expect($entrega->fresh()->next_attempt_at->diffInSeconds(now()->addSeconds(10)))->toEqual(0)
        ->and($entrega->fresh()->hasAttemptsLeft())->toBeTrue();

    entregar($entrega);

    $entrega->refresh();

    expect($entrega->status)->toBe(OutboundWebhookDelivery::FAILED)
        ->and($entrega->attempt)->toBe(3)
        ->and($entrega->hasAttemptsLeft())->toBeFalse()
        ->and($entrega->next_attempt_at)->toBeNull();

    Bus::assertDispatchedTimes(DeliverWebhook::class, 1 + 2);
});

// -----------------------------------------------------------------------
// Límite de fallos
// -----------------------------------------------------------------------

/**
 * Agota los intentos de tantas entregas como se pidan.
 */
function fallarEntregas(OutboundWebhook $webhook, int $veces): void
{
    for ($i = 0; $i < $veces; $i++) {
        $entrega = Tenant::runFor($webhook->account_id, fn () => Webhook::dispatch('a.b')->first());

        for ($intento = 0; $intento < app(WebhookManager::class)->attempts(); $intento++) {
            entregar($entrega);
        }
    }
}

test('al llegar al límite se apaga el endpoint y se avisa una vez', function () {
    Bus::fake([DeliverWebhook::class]);
    Event::fake([WebhookEndpointFailing::class]);
    Http::fake(['*' => Http::response('vaya', 500)]);

    config(['base-tenant.webhooks.failure_limit' => 2, 'base-tenant.webhooks.attempts' => 1]);

    [$webhook] = endpointConEntrega();

    fallarEntregas($webhook, 1);

    expect($webhook->fresh()->enabled)->toBeTrue();
    Event::assertNotDispatched(WebhookEndpointFailing::class);

    fallarEntregas($webhook, 1);

    expect($webhook->fresh()->enabled)->toBeFalse()
        ->and($webhook->fresh()->disabled_at)->not->toBeNull();

    Event::assertDispatchedTimes(WebhookEndpointFailing::class, 1);
    Event::assertDispatched(WebhookEndpointFailing::class, fn (WebhookEndpointFailing $e): bool => $e->action === 'disable'
        && $e->failures === 2
        && $e->webhook->is($webhook));
});

test('con degrade el endpoint sigue recibiendo, marcado, y se avisa una vez', function () {
    Bus::fake([DeliverWebhook::class]);
    Event::fake([WebhookEndpointFailing::class]);

    $responde = 500;

    Http::fake(function () use (&$responde) {
        return Http::response('x', $responde);
    });

    config([
        'base-tenant.webhooks.failure_limit' => 2,
        'base-tenant.webhooks.on_failure_limit' => 'degrade',
        'base-tenant.webhooks.attempts' => 1,
    ]);

    [$webhook] = endpointConEntrega();

    fallarEntregas($webhook, 4);

    $webhook->refresh();

    expect($webhook->enabled)->toBeTrue()
        ->and($webhook->isDegraded())->toBeTrue()
        ->and($webhook->disabled_at)->toBeNull()
        ->and($webhook->failure_count)->toBe(4)
        ->and(Webhook::subscribers('a.b', $webhook->account_id))->toHaveCount(1);

    Event::assertDispatchedTimes(WebhookEndpointFailing::class, 1);
    Event::assertDispatched(WebhookEndpointFailing::class, fn (WebhookEndpointFailing $e): bool => $e->action === 'degrade'
        && $e->failures === 2);

    // Una entrega buena cierra el episodio.
    $responde = 200;
    fallarEntregas($webhook, 1);

    expect($webhook->fresh()->isDegraded())->toBeFalse()
        ->and($webhook->fresh()->failure_count)->toBe(0);
});

test('sin límite de fallos el endpoint no se toca nunca', function () {
    Bus::fake([DeliverWebhook::class]);
    Event::fake([WebhookEndpointFailing::class]);
    Http::fake(['*' => Http::response('vaya', 500)]);

    config(['base-tenant.webhooks.failure_limit' => null, 'base-tenant.webhooks.attempts' => 1]);

    [$webhook] = endpointConEntrega();

    fallarEntregas($webhook, 3);

    expect($webhook->fresh()->enabled)->toBeTrue()
        ->and($webhook->fresh()->isDegraded())->toBeFalse();

    Event::assertNotDispatched(WebhookEndpointFailing::class);
});

test('una acción desconocida al llegar al límite es un error de configuración', function () {
    config(['base-tenant.webhooks.on_failure_limit' => 'borrar']);

    expect(fn () => app(WebhookManager::class)->failureAction())
        ->toThrow(InvalidArgumentException::class);
});

// -----------------------------------------------------------------------
// Reenvío manual
// -----------------------------------------------------------------------

test('reenviar crea una entrega nueva con el mismo cuerpo exacto', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    [$webhook, $entrega] = endpointConEntrega();

    entregar($entrega);

    // Aunque el sobre haya cambiado desde entonces.
    config(['base-tenant.webhooks.payload_builder' => SobreDePrueba::class]);

    Bus::fake([DeliverWebhook::class]);

    $copia = Webhook::redeliver($entrega->fresh());

    Bus::assertDispatched(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->deliveryId === $copia->getKey());

    entregar($copia);

    $peticiones = Http::recorded()->map(fn (array $par): Request => $par[0])->values();
    $secreto = $webhook->fresh()->secret;

    expect($copia->getKey())->not->toBe($entrega->getKey())
        ->and($copia->redelivery_of)->toBe($entrega->getKey())
        ->and($copia->isRedelivery())->toBeTrue()
        ->and($copia->original->is($entrega))->toBeTrue()
        ->and($peticiones)->toHaveCount(2)
        ->and($peticiones[1]->body())->toBe($peticiones[0]->body())
        ->and($peticiones[1]->header(WebhookManager::DELIVERY_HEADER)[0])->toBe($copia->getKey())
        ->and(Webhook::verify($peticiones[1]->body(), $secreto, $peticiones[1]->header(WebhookManager::SIGNATURE_HEADER)[0]))->toBeTrue()
        ->and($copia->fresh()->status)->toBe(OutboundWebhookDelivery::DELIVERED)
        ->and($copia->fresh()->attempt)->toBe(1)
        ->and($entrega->fresh()->status)->toBe(OutboundWebhookDelivery::DELIVERED);
});

test('reenviar una entrega que nunca salió manda el cuerpo que habría mandado', function () {
    Bus::fake([DeliverWebhook::class]);

    [, $entrega] = endpointConEntrega();

    $copia = Webhook::redeliver($entrega);

    $cuerpo = json_decode($copia->body, true);

    expect($cuerpo['delivery'])->toBe($entrega->getKey())
        ->and($cuerpo['data'])->toBe(['id' => 7])
        ->and($entrega->fresh()->body)->toBeNull();
});

// -----------------------------------------------------------------------
// Modelos
// -----------------------------------------------------------------------

test('el gestor y el job usan los modelos configurados', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    config([
        'base-tenant.webhooks.models.endpoint' => EndpointDeLaApp::class,
        'base-tenant.webhooks.models.delivery' => EntregaDeLaApp::class,
    ]);

    [$webhook, $entrega] = endpointConEntrega();

    expect($webhook)->toBeInstanceOf(EndpointDeLaApp::class)
        ->and($entrega)->toBeInstanceOf(EntregaDeLaApp::class)
        ->and(Webhook::findDelivery($entrega->getKey()))->toBeInstanceOf(EntregaDeLaApp::class)
        ->and(Webhook::findEndpoint($webhook->getKey()))->toBeInstanceOf(EndpointDeLaApp::class)
        ->and($webhook->deliveries()->acrossAccounts()->first())->toBeInstanceOf(EntregaDeLaApp::class)
        ->and($entrega->webhook()->acrossAccounts()->first())->toBeInstanceOf(EndpointDeLaApp::class)
        ->and(Webhook::redeliver($entrega))->toBeInstanceOf(EntregaDeLaApp::class);

    entregar($entrega);

    expect($entrega->fresh()->status)->toBe(OutboundWebhookDelivery::DELIVERED);
});

test('un modelo configurado que no extiende el del paquete se rechaza', function () {
    config(['base-tenant.webhooks.models.endpoint' => NoEsUnSobre::class]);

    expect(fn () => app(WebhookManager::class)->endpointModel())
        ->toThrow(InvalidArgumentException::class);
});
