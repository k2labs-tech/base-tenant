<?php

declare(strict_types=1);

use Base\Tenant\Connections\WebhookManager;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Facades\Webhook;
use Base\Tenant\Jobs\DeliverWebhook;
use Base\Tenant\Models\Account;
use Base\Tenant\Models\OutboundWebhook;
use Base\Tenant\Models\OutboundWebhookAttempt;
use Base\Tenant\Models\OutboundWebhookDelivery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

/*
 * Lo que llega en 3.2: historial por intento, endpoints sin firma, pausa del
 * endpoint degradado, flags de JSON y el job bajo la cuenta de su entrega.
 */

class IntentoDeLaApp extends OutboundWebhookAttempt {}

/**
 * Un endpoint en una cuenta nueva, registrado fuera de cualquier contexto.
 *
 * @return array{0: Account, 1: OutboundWebhook}
 */
function cuentaConEndpoint(bool $firmado = true): array
{
    $cuenta = test()->createAccount();

    $webhook = Tenant::runFor($cuenta, fn (): OutboundWebhook => Webhook::register('https://uno.test/hook', ['*'], signed: $firmado));

    return [$cuenta, $webhook];
}

function despachar(Account $cuenta, string $evento = 'booking.confirmed', array $datos = ['id' => 7]): OutboundWebhookDelivery
{
    return Tenant::runFor($cuenta, fn () => Webhook::dispatch($evento, $datos)->first());
}

function ejecutar(OutboundWebhookDelivery|string $entrega): void
{
    $id = $entrega instanceof OutboundWebhookDelivery ? $entrega->getKey() : $entrega;

    (new DeliverWebhook($id))->handle(app(WebhookManager::class));
}

/**
 * @return list<OutboundWebhookAttempt>
 */
function intentosDe(OutboundWebhookDelivery $entrega): array
{
    return Tenant::runFor($entrega->account_id, fn (): array => $entrega->attempts()->with('delivery')->get()->all());
}

function tenenciaEstricta(): void
{
    config([
        'base-tenant.tenancy.strict' => true,
        'base-tenant.tenancy.on_missing_tenant' => 'throw',
    ]);

    Tenant::forget();
}

// -----------------------------------------------------------------------
// Sin configurar: lo mismo que en 3.1
// -----------------------------------------------------------------------

test('sin configurar, las opciones nuevas dejan el comportamiento de 3.1', function () {
    $webhooks = app(WebhookManager::class);

    expect($webhooks->jsonFlags())->toBe(0)
        ->and($webhooks->allowsUnsigned())->toBeFalse()
        ->and($webhooks->degradedCooldown())->toBeNull()
        ->and($webhooks->redactsErrors())->toBeFalse()
        ->and($webhooks->logsAttempts())->toBeTrue()
        ->and($webhooks->attemptModel())->toBe(OutboundWebhookAttempt::class);

    // Una config publicada en 3.1 no trae las claves nuevas.
    config(['base-tenant.webhooks' => ['enabled' => true]]);

    expect($webhooks->jsonFlags())->toBe(0)
        ->and($webhooks->allowsUnsigned())->toBeFalse()
        ->and($webhooks->degradedCooldown())->toBeNull()
        ->and($webhooks->logsAttempts())->toBeTrue()
        ->and($webhooks->attemptModel())->toBe(OutboundWebhookAttempt::class);
});

// -----------------------------------------------------------------------
// Tenencia estricta
// -----------------------------------------------------------------------

test('con tenencia estricta, dispatch y redeliver funcionan sin contexto con cola sync', function () {
    Http::fake(['*' => Http::response('ok', 200)]);
    config(['queue.default' => 'sync']);

    [$cuenta] = cuentaConEndpoint();

    tenenciaEstricta();

    $entrega = Webhook::dispatch('booking.confirmed', ['id' => 7], $cuenta)->first();

    expect(Tenant::currentId())->toBeNull()
        ->and($entrega->fresh()->status)->toBe(OutboundWebhookDelivery::DELIVERED)
        ->and($entrega->fresh()->account_id)->toBe($cuenta->getKey());

    $copia = Webhook::redeliver($entrega->fresh());

    expect($copia->fresh()->status)->toBe(OutboundWebhookDelivery::DELIVERED)
        ->and(intentosDe($copia))->toHaveCount(1);

    Http::assertSentCount(2);
});

test('con tenencia estricta, el job fija la cuenta de su entrega en un worker', function () {
    Http::fake(['*' => Http::response('ok', 200)]);
    config(['queue.default' => 'database']);

    [$cuenta] = cuentaConEndpoint();
    $otra = $this->createAccount();

    tenenciaEstricta();

    // Sin contexto previo.
    $primera = Webhook::dispatch('booking.confirmed', ['id' => 1], $cuenta)->first();

    // Desde el contexto de otra cuenta, pasando la cuenta explícitamente.
    $segunda = Tenant::runFor($otra, fn () => Webhook::dispatch('booking.confirmed', ['id' => 2], $cuenta)->first());

    // Un job empujado a mano sin ninguna cuenta en contexto: el caso de 3.1
    // que fallaba en el worker.
    $tercera = Tenant::runFor($cuenta, fn () => OutboundWebhookDelivery::create([
        'outbound_webhook_id' => Webhook::subscribers('booking.confirmed', $cuenta->getKey())->first()->getKey(),
        'event' => 'booking.confirmed',
        'payload' => ['id' => 3],
        'status' => OutboundWebhookDelivery::PENDING,
    ]));
    DeliverWebhook::dispatch($tercera->getKey());

    Http::assertNothingSent();

    $this->artisan('queue:work', ['--stop-when-empty' => true])->run();

    foreach ([$primera, $segunda, $tercera] as $entrega) {
        expect($entrega->fresh()->status)->toBe(OutboundWebhookDelivery::DELIVERED)
            ->and($entrega->fresh()->attempt)->toBe(1);
    }

    $copia = Webhook::redeliver($primera->fresh());

    $this->artisan('queue:work', ['--stop-when-empty' => true])->run();

    expect($copia->fresh()->status)->toBe(OutboundWebhookDelivery::DELIVERED)
        ->and(Tenant::currentId())->toBeNull();

    Http::assertSentCount(4);
});

// -----------------------------------------------------------------------
// Historial por intento
// -----------------------------------------------------------------------

test('cada intento deja su fila con estado, código y duración', function () {
    Bus::fake([DeliverWebhook::class]);

    $responde = 503;

    Http::fake(function () use (&$responde) {
        return Http::response('x', $responde);
    });

    [$cuenta] = cuentaConEndpoint();
    $entrega = despachar($cuenta);

    ejecutar($entrega);
    $responde = 200;
    ejecutar($entrega);

    $intentos = intentosDe($entrega);

    expect($intentos)->toHaveCount(2)
        ->and(array_map(fn (OutboundWebhookAttempt $i): array => [$i->attempt, $i->outcome, $i->status_code], $intentos))
        ->toBe([[1, 'failed', 503], [2, 'delivered', 200]])
        ->and($intentos[0]->duration_ms)->toBeInt()->toBeGreaterThanOrEqual(0)
        ->and($intentos[0]->attempted_at)->not->toBeNull()
        ->and($intentos[0]->account_id)->toBe($cuenta->getKey())
        ->and($intentos[0]->delivery->is($entrega))->toBeTrue()
        ->and($intentos[1]->succeeded())->toBeTrue()
        // La fila de la entrega sigue como en 3.1: sólo el último resultado.
        ->and($entrega->fresh()->response_status)->toBe(200);
});

test('un error de conexión queda en el intento, y se redacta si se pide', function () {
    Bus::fake([DeliverWebhook::class]);

    Http::fake(function () {
        throw new ConnectionException('cURL error 7: Failed to connect for https://user:pass@uno.test/hook/tok_123?key=secreta');
    });

    [$cuenta] = cuentaConEndpoint();

    $sinRedactar = despachar($cuenta);
    ejecutar($sinRedactar);

    expect(intentosDe($sinRedactar)[0]->error)->toContain('key=secreta')
        ->and($sinRedactar->fresh()->error)->toContain('key=secreta');

    config(['base-tenant.webhooks.redact_errors' => true]);

    $redactada = despachar($cuenta);
    ejecutar($redactada);

    $intento = intentosDe($redactada)[0];

    expect($intento->outcome)->toBe('failed')
        ->and($intento->status_code)->toBeNull()
        ->and($intento->error)->toBe('cURL error 7: Failed to connect for https://uno.test/[redacted]')
        ->and($redactada->fresh()->error)->toBe($intento->error);
});

test('sin log_attempts no se escribe historial', function () {
    Http::fake(['*' => Http::response('ok', 200)]);
    config(['base-tenant.webhooks.log_attempts' => false]);

    [$cuenta] = cuentaConEndpoint();
    $entrega = despachar($cuenta);

    ejecutar($entrega);

    expect($entrega->fresh()->status)->toBe(OutboundWebhookDelivery::DELIVERED)
        ->and(intentosDe($entrega))->toBeEmpty();
});

test('el historial usa el modelo configurado', function () {
    Http::fake(['*' => Http::response('ok', 200)]);
    config(['base-tenant.webhooks.models.attempt' => IntentoDeLaApp::class]);

    [$cuenta] = cuentaConEndpoint();
    $entrega = despachar($cuenta);

    ejecutar($entrega);

    expect(intentosDe($entrega)[0])->toBeInstanceOf(IntentoDeLaApp::class);

    config(['base-tenant.webhooks.models.attempt' => OutboundWebhook::class]);

    expect(fn () => app(WebhookManager::class)->attemptModel())->toThrow(InvalidArgumentException::class);
});

// -----------------------------------------------------------------------
// Endpoints sin secreto
// -----------------------------------------------------------------------

test('sin allow_unsigned no se registra ni se envía a un endpoint sin secreto', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    expect(fn () => cuentaConEndpoint(firmado: false))->toThrow(InvalidArgumentException::class);

    [$cuenta, $webhook] = cuentaConEndpoint();

    Tenant::runFor($cuenta, fn () => $webhook->forceFill(['secret' => null])->save());

    $entrega = despachar($cuenta);
    ejecutar($entrega);

    Http::assertNothingSent();

    expect($entrega->fresh()->status)->toBe(OutboundWebhookDelivery::FAILED)
        ->and($entrega->fresh()->error)->toBe(__('base-tenant::connections.webhooks.unsigned_refused'))
        ->and($entrega->fresh()->attempt)->toBe(0);
});

test('con allow_unsigned se envía sin cabecera de firma', function () {
    Http::fake(['*' => Http::response('ok', 200)]);
    config(['base-tenant.webhooks.allow_unsigned' => true]);

    [$cuenta, $webhook] = cuentaConEndpoint(firmado: false);

    expect($webhook->fresh()->secret)->toBeNull()
        ->and($webhook->fresh()->isSigned())->toBeFalse();

    $entrega = despachar($cuenta);
    ejecutar($entrega);

    Http::assertSent(fn (Request $request): bool => ! $request->hasHeader(WebhookManager::SIGNATURE_HEADER)
        && $request->header(WebhookManager::EVENT_HEADER)[0] === 'booking.confirmed'
        && $request->header(WebhookManager::DELIVERY_HEADER)[0] === $entrega->getKey());

    expect($entrega->fresh()->status)->toBe(OutboundWebhookDelivery::DELIVERED);

    // Un endpoint con secreto sigue firmando aunque se permitan los otros.
    [$firmada] = cuentaConEndpoint();
    ejecutar(despachar($firmada));

    Http::assertSent(fn (Request $request): bool => $request->hasHeader(WebhookManager::SIGNATURE_HEADER));
});

// -----------------------------------------------------------------------
// Pausa del endpoint degradado
// -----------------------------------------------------------------------

/**
 * Un endpoint degradado por un único fallo, con la pausa dada.
 *
 * @return array{0: Account, 1: OutboundWebhook}
 */
function endpointDegradado(?int $pausa): array
{
    config([
        'base-tenant.webhooks.failure_limit' => 1,
        'base-tenant.webhooks.on_failure_limit' => 'degrade',
        'base-tenant.webhooks.attempts' => 1,
        'base-tenant.webhooks.degraded_cooldown' => $pausa,
    ]);

    [$cuenta, $webhook] = cuentaConEndpoint();

    $entrega = despachar($cuenta);

    // Con la cola sync ya se ha intentado al despachar.
    if ($entrega->fresh()->attempt === 0) {
        ejecutar($entrega);
    }

    return [$cuenta, $webhook->fresh()];
}

test('un endpoint degradado en pausa pospone la entrega sin gastar intentos', function () {
    Bus::fake([DeliverWebhook::class]);
    $this->freezeSecond();

    $responde = 500;

    Http::fake(function () use (&$responde) {
        return Http::response('x', $responde);
    });

    [$cuenta, $webhook] = endpointDegradado(600);

    expect($webhook->isDegraded())->toBeTrue()
        ->and($webhook->last_failed_at->equalTo(now()))->toBeTrue()
        ->and($webhook->pausedUntil()->equalTo(now()->addSeconds(600)))->toBeTrue();

    Http::assertSentCount(1);

    $this->travel(60)->seconds();
    $responde = 200;

    $entrega = despachar($cuenta);
    ejecutar($entrega);

    Http::assertSentCount(1);

    $entrega->refresh();
    $intento = intentosDe($entrega)[0];

    expect($entrega->status)->toBe(OutboundWebhookDelivery::PENDING)
        ->and($entrega->attempt)->toBe(0)
        ->and($entrega->next_attempt_at->equalTo($webhook->last_failed_at->copy()->addSeconds(600)))->toBeTrue()
        ->and($intento->outcome)->toBe('postponed')
        ->and($intento->attempt)->toBe(1)
        ->and($intento->status_code)->toBeNull();

    Bus::assertDispatched(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->deliveryId === $entrega->getKey()
        && $job->delay instanceof DateTimeInterface
        && $job->delay->getTimestamp() === $entrega->next_attempt_at->getTimestamp());

    // Acabada la pausa, sale con su primer intento.
    $this->travel(541)->seconds();
    ejecutar($entrega);

    expect($entrega->fresh()->status)->toBe(OutboundWebhookDelivery::DELIVERED)
        ->and($entrega->fresh()->attempt)->toBe(1)
        ->and($webhook->fresh()->isDegraded())->toBeFalse()
        ->and(array_column(array_map(fn ($i) => $i->toArray(), intentosDe($entrega)), 'outcome'))->toBe(['postponed', 'delivered']);
});

test('sin degraded_cooldown un endpoint degradado sigue recibiendo', function () {
    Bus::fake([DeliverWebhook::class]);
    Http::fake(['*' => Http::response('x', 500)]);

    [$cuenta, $webhook] = endpointDegradado(null);

    expect($webhook->isDegraded())->toBeTrue()
        ->and($webhook->pausedUntil())->toBeNull();

    ejecutar(despachar($cuenta));

    Http::assertSentCount(2);
});

test('la pausa no se aplica a un endpoint que no está degradado', function () {
    Http::fake(['*' => Http::response('ok', 200)]);
    config(['base-tenant.webhooks.degraded_cooldown' => 600]);

    [$cuenta, $webhook] = cuentaConEndpoint();

    Tenant::runFor($cuenta, fn () => $webhook->forceFill(['last_failed_at' => now()])->save());

    $entrega = despachar($cuenta);
    ejecutar($entrega);

    expect($entrega->fresh()->status)->toBe(OutboundWebhookDelivery::DELIVERED);
});

test('con cola sync no hay más tarde que esperar y la pausa no se aplica', function () {
    $responde = 500;

    Http::fake(function () use (&$responde) {
        return Http::response('x', $responde);
    });

    config(['queue.default' => 'sync']);

    [$cuenta] = endpointDegradado(600);

    $responde = 200;

    expect(despachar($cuenta)->fresh()->status)->toBe(OutboundWebhookDelivery::DELIVERED);
});

// -----------------------------------------------------------------------
// Flags de JSON
// -----------------------------------------------------------------------

test('json_flags cambia los bytes, se firman esos, y reintentos y reenvíos mandan los mismos', function () {
    Bus::fake([DeliverWebhook::class]);

    $responde = 500;

    Http::fake(function () use (&$responde) {
        return Http::response('x', $responde);
    });

    config(['base-tenant.webhooks.json_flags' => JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE]);

    [$cuenta, $webhook] = cuentaConEndpoint();
    $entrega = despachar($cuenta, 'booking.confirmed', ['url' => 'https://x.test/a/b', 'nombre' => 'Muñoz']);

    ejecutar($entrega);

    // Volver a los flags de 3.1 no cambia lo que ya se firmó y se mandó.
    config(['base-tenant.webhooks.json_flags' => 0]);
    $responde = 200;

    ejecutar($entrega);

    $copia = Webhook::redeliver($entrega->fresh());
    ejecutar($copia);

    $peticiones = Http::recorded()->map(fn (array $par): Request => $par[0])->values();
    $secreto = $webhook->fresh()->secret;

    expect($peticiones)->toHaveCount(3)
        ->and($peticiones[0]->body())->toContain('"url":"https://x.test/a/b"')
        ->and($peticiones[0]->body())->toContain('"nombre":"Muñoz"')
        ->and($peticiones[1]->body())->toBe($peticiones[0]->body())
        ->and($peticiones[2]->body())->toBe($peticiones[0]->body());

    foreach ($peticiones as $peticion) {
        expect(Webhook::verify($peticion->body(), $secreto, $peticion->header(WebhookManager::SIGNATURE_HEADER)[0]))->toBeTrue();
    }
});

test('sin json_flags el cuerpo escapa barras y unicode, como en 3.1', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    [$cuenta] = cuentaConEndpoint();
    ejecutar(despachar($cuenta, 'booking.confirmed', ['url' => 'https://x.test/a', 'nombre' => 'Muñoz']));

    Http::assertSent(fn (Request $request): bool => str_contains($request->body(), 'https:\/\/x.test\/a')
        && str_contains($request->body(), json_encode('Muñoz'))
        && ! str_contains($request->body(), 'Muñoz'));
});
