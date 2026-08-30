<?php

declare(strict_types=1);

use Base\Tenant\Connections\Connector;
use Base\Tenant\Connections\HealthCheck;
use Base\Tenant\Connections\WebhookManager;
use Base\Tenant\Exceptions\ModuleDisabledException;
use Base\Tenant\Facades\Connection;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Facades\Webhook;
use Base\Tenant\Jobs\DeliverWebhook;
use Base\Tenant\Models\AccountConnection;
use Base\Tenant\Models\OutboundWebhook;
use Base\Tenant\Models\OutboundWebhookDelivery;
use Base\Tenant\Notifications\ConnectionFailing;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/**
 * Un conector de prueba cuyo veredicto se puede dirigir desde el test.
 */
class ConectorDePrueba implements Connector
{
    public static string $verdict = 'healthy';

    public function fields(): array
    {
        return ['token' => ['label' => 'Token', 'type' => 'password', 'required' => true]];
    }

    public function healthCheck(AccountConnection $connection): HealthCheck
    {
        return match (self::$verdict) {
            'failing' => HealthCheck::failing('El proveedor ha rechazado el token.'),
            'throws' => throw new RuntimeException('El proveedor no responde.'),
            default => HealthCheck::healthy(),
        };
    }

    public function client(AccountConnection $connection): mixed
    {
        return 'cliente:'.$connection->credentials['token'];
    }
}

beforeEach(function () {
    ConectorDePrueba::$verdict = 'healthy';

    config(['base-tenant.connections.connectors' => ['prueba' => ConectorDePrueba::class]]);
});

test('guardar credenciales devuelve un cliente listo para usar', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        Connection::store('prueba', ['token' => 'abc']);

        expect(Connection::client('prueba'))->toBe('cliente:abc');
    });
});

/**
 * Son las credenciales del cliente en un tercero: un volcado de la base de
 * datos no debería ser un juego de accesos vivos.
 */
test('las credenciales se guardan cifradas', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, fn () => Connection::store('prueba', ['token' => 'secreto-del-cliente']));

    $enBruto = DB::table('account_connections')->value('credentials');

    expect($enBruto)->not->toContain('secreto-del-cliente');

    Tenant::runFor($cuenta, function () {
        expect(Connection::find('prueba')->credentials['token'])->toBe('secreto-del-cliente');
    });
});

test('una cuenta puede tener dos conexiones del mismo proveedor', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        Connection::store('prueba', ['token' => 'uno'], label: 'principal');
        Connection::store('prueba', ['token' => 'dos'], label: 'secundaria');

        expect(Connection::client('prueba', 'principal'))->toBe('cliente:uno')
            ->and(Connection::client('prueba', 'secundaria'))->toBe('cliente:dos')
            ->and(Connection::all())->toHaveCount(2);
    });
});

test('las conexiones de una cuenta no se ven desde otra', function () {
    $una = $this->createAccount();
    $otra = $this->createAccount();

    Tenant::runFor($una, fn () => Connection::store('prueba', ['token' => 'privado']));

    Tenant::runFor($otra, function () {
        expect(Connection::all())->toBeEmpty()
            ->and(Connection::find('prueba'))->toBeNull()
            ->and(fn () => Connection::client('prueba'))->toThrow(RuntimeException::class);
    });
});

/**
 * Arrastrar el veredicto anterior enseñaría una luz verde para un token que
 * nadie ha probado.
 */
test('unas credenciales nuevas vuelven a estado sin comprobar', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        $conexion = Connection::store('prueba', ['token' => 'uno']);

        Connection::check($conexion);

        expect($conexion->fresh()->status)->toBe(AccountConnection::HEALTHY);

        Connection::store('prueba', ['token' => 'dos']);

        expect(Connection::find('prueba')->status)->toBe(AccountConnection::UNKNOWN);
    });
});

/**
 * Que el proveedor esté caído no significa que las credenciales del cliente
 * estén mal, y decírselo es peor que no decir nada.
 */
test('un conector que revienta se anota como desconocido, no como fallo', function () {
    $cuenta = $this->createAccount();

    ConectorDePrueba::$verdict = 'throws';

    Tenant::runFor($cuenta, function () {
        $conexion = Connection::store('prueba', ['token' => 'abc']);

        $resultado = Connection::check($conexion);

        expect($resultado->status)->toBe(AccountConnection::UNKNOWN)
            ->and($conexion->fresh()->status)->toBe(AccountConnection::UNKNOWN);
    });
});

test('el comando avisa al pasar a fallo y no en cada pasada', function () {
    Notification::fake();

    $cuenta = $this->createAccount();
    $duena = $this->createUser($cuenta);
    $cuenta->update(['user_id' => $duena->getKey()]);

    Tenant::runFor($cuenta, fn () => Connection::store('prueba', ['token' => 'abc']));

    ConectorDePrueba::$verdict = 'failing';

    $this->artisan('k2labs-base:check-connections')->assertSuccessful();

    Notification::assertSentToTimes($duena, ConnectionFailing::class, 1);

    // Sigue fallando, pero la noticia ya se dio.
    $this->artisan('k2labs-base:check-connections')->assertSuccessful();

    Notification::assertSentToTimes($duena, ConnectionFailing::class, 1);
});

test('un proveedor que no está declarado no se puede usar', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        expect(fn () => Connection::store('inventado', ['x' => 1]))
            ->toThrow(InvalidArgumentException::class);
    });
});

test('con el módulo apagado no hay conexiones', function () {
    config(['base-tenant.connections.enabled' => false]);

    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        expect(fn () => Connection::store('prueba', ['token' => 'x']))
            ->toThrow(ModuleDisabledException::class);
    });
});

// -----------------------------------------------------------------------
// Webhooks salientes
// -----------------------------------------------------------------------

test('un evento se reparte a los endpoints suscritos y a nadie más', function () {
    Bus::fake();

    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        Webhook::register('https://uno.test/hook', ['booking.*']);
        Webhook::register('https://dos.test/hook', ['invoice.paid']);
        Webhook::register('https://tres.test/hook', ['*']);

        $entregas = Webhook::dispatch('booking.confirmed', ['id' => 1]);

        expect($entregas)->toHaveCount(2);
    });

    Bus::assertDispatchedTimes(DeliverWebhook::class, 2);
});

test('un endpoint apagado no recibe nada', function () {
    Bus::fake();

    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        $webhook = Webhook::register('https://uno.test/hook', ['*']);
        $webhook->update(['enabled' => false]);

        expect(Webhook::dispatch('booking.confirmed'))->toBeEmpty();
    });
});

test('los webhooks de una cuenta no reciben los eventos de otra', function () {
    Bus::fake();

    $una = $this->createAccount();
    $otra = $this->createAccount();

    Tenant::runFor($una, fn () => Webhook::register('https://uno.test/hook', ['*']));

    Tenant::runFor($otra, function () {
        expect(Webhook::dispatch('booking.confirmed'))->toBeEmpty();
    });
});

test('una entrega correcta firma el cuerpo que manda', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $cuenta = $this->createAccount();

    $entrega = Tenant::runFor($cuenta, function () {
        Webhook::register('https://uno.test/hook', ['*']);

        return Webhook::dispatch('booking.confirmed', ['id' => 7])->first();
    });

    (new DeliverWebhook($entrega->getKey()))->handle(app(WebhookManager::class));

    $secreto = OutboundWebhook::query()->acrossAccounts()->first()->secret;

    Http::assertSent(function ($request) use ($secreto, $entrega): bool {
        $cuerpo = $request->body();

        return $request->header(WebhookManager::SIGNATURE_HEADER)[0] === hash_hmac('sha256', $cuerpo, $secreto)
            && $request->header(WebhookManager::EVENT_HEADER)[0] === 'booking.confirmed'
            && $request->header(WebhookManager::DELIVERY_HEADER)[0] === $entrega->getKey()
            // El id va dentro del cuerpo firmado: una petición capturada no se
            // puede reproducir contra otro evento.
            && str_contains($cuerpo, $entrega->getKey());
    });

    expect($entrega->fresh()->status)->toBe(OutboundWebhookDelivery::DELIVERED)
        ->and($entrega->fresh()->response_status)->toBe(200);
});

/**
 * Serializar una vez y firmar y mandar eso mismo. Volver a codificar entre las
 * dos cosas es la razón clásica de que una firma calculada bien no case nunca.
 */
test('el receptor puede verificar la firma con el cuerpo tal cual llega', function () {
    Http::fake(['*' => Http::response('ok', 200)]);

    $cuenta = $this->createAccount();

    $entrega = Tenant::runFor($cuenta, function () {
        Webhook::register('https://uno.test/hook', ['*']);

        return Webhook::dispatch('booking.confirmed', ['id' => 7])->first();
    });

    (new DeliverWebhook($entrega->getKey()))->handle(app(WebhookManager::class));

    $secreto = OutboundWebhook::query()->acrossAccounts()->first()->secret;

    Http::assertSent(fn ($request): bool => Webhook::verify(
        $request->body(),
        $secreto,
        $request->header(WebhookManager::SIGNATURE_HEADER)[0],
    ));
});

test('una entrega fallida se reprograma con espera creciente', function () {
    Bus::fake([DeliverWebhook::class]);
    Http::fake(['*' => Http::response('vaya', 500)]);

    $cuenta = $this->createAccount();

    $entrega = Tenant::runFor($cuenta, function () {
        Webhook::register('https://uno.test/hook', ['*']);

        return Webhook::dispatch('booking.confirmed')->first();
    });

    (new DeliverWebhook($entrega->getKey()))->handle(app(WebhookManager::class));

    $entrega->refresh();

    expect($entrega->status)->toBe(OutboundWebhookDelivery::PENDING)
        ->and($entrega->attempt)->toBe(1)
        ->and($entrega->response_status)->toBe(500)
        ->and($entrega->next_attempt_at)->not->toBeNull();

    Bus::assertDispatched(DeliverWebhook::class);
});

test('agotados los intentos la entrega se da por fallida', function () {
    Bus::fake([DeliverWebhook::class]);
    Http::fake(['*' => Http::response('vaya', 500)]);

    $cuenta = $this->createAccount();

    $entrega = Tenant::runFor($cuenta, function () {
        Webhook::register('https://uno.test/hook', ['*']);

        return Webhook::dispatch('booking.confirmed')->first();
    });

    foreach (OutboundWebhookDelivery::BACKOFF as $ignored) {
        (new DeliverWebhook($entrega->getKey()))->handle(app(WebhookManager::class));
    }

    $entrega->refresh();

    expect($entrega->status)->toBe(OutboundWebhookDelivery::FAILED)
        ->and($entrega->attempt)->toBe(count(OutboundWebhookDelivery::BACKOFF))
        ->and($entrega->next_attempt_at)->toBeNull();
});

/**
 * Una URL que lleva días sin existir no vuelve sola, y cada entrega cuesta un
 * job en cola y un timeout.
 */
test('un endpoint que falla sin parar acaba apagándose', function () {
    Bus::fake([DeliverWebhook::class]);
    Http::fake(['*' => Http::response('vaya', 500)]);

    $cuenta = $this->createAccount();

    $webhook = Tenant::runFor($cuenta, fn () => Webhook::register('https://uno.test/hook', ['*']));

    for ($i = 0; $i < OutboundWebhook::FAILURE_LIMIT; $i++) {
        $entrega = Tenant::runFor($cuenta, fn () => Webhook::dispatch('booking.confirmed')->first());

        foreach (OutboundWebhookDelivery::BACKOFF as $ignored) {
            (new DeliverWebhook($entrega->getKey()))->handle(app(WebhookManager::class));
        }
    }

    expect($webhook->fresh()->enabled)->toBeFalse()
        ->and($webhook->fresh()->disabled_at)->not->toBeNull();
});

test('una entrega correcta pone a cero el contador de fallos', function () {
    Bus::fake([DeliverWebhook::class]);

    $cuenta = $this->createAccount();

    $webhook = Tenant::runFor($cuenta, fn () => Webhook::register('https://uno.test/hook', ['*']));

    // Un solo `fake` con el estado en una variable: llamar a `Http::fake()`
    // otra vez apila un stub más y gana el primero que casa, así que la
    // segunda entrega seguiría recibiendo el 500.
    $responde = 500;

    // Por referencia y no con una flecha: `fn ()` captura por valor en el
    // momento en que se define, así que el stub se quedaría con el 500 para
    // siempre y la segunda entrega fallaría igual.
    Http::fake(function () use (&$responde) {
        return Http::response($responde === 200 ? 'ok' : 'vaya', $responde);
    });

    $entrega = Tenant::runFor($cuenta, fn () => Webhook::dispatch('a.b')->first());

    foreach (OutboundWebhookDelivery::BACKOFF as $ignored) {
        (new DeliverWebhook($entrega->getKey()))->handle(app(WebhookManager::class));
    }

    expect($webhook->fresh()->failure_count)->toBe(1);

    $responde = 200;

    $buena = Tenant::runFor($cuenta, fn () => Webhook::dispatch('a.b')->first());

    (new DeliverWebhook($buena->getKey()))->handle(app(WebhookManager::class));

    expect($webhook->fresh()->failure_count)->toBe(0)
        ->and($webhook->fresh()->last_delivered_at)->not->toBeNull();
});

test('el secreto se guarda cifrado y no se genera desde fuera', function () {
    $cuenta = $this->createAccount();

    $webhook = Tenant::runFor($cuenta, fn () => Webhook::register('https://uno.test/hook'));

    expect($webhook->secret)->toHaveLength(48);

    $enBruto = DB::table('outbound_webhooks')->value('secret');

    expect($enBruto)->not->toBe($webhook->secret);
});

test('con el módulo apagado no sale ningún evento', function () {
    Bus::fake();

    config(['base-tenant.webhooks.enabled' => false]);

    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        expect(Webhook::dispatch('booking.confirmed'))->toBeEmpty();
    });

    Bus::assertNotDispatched(DeliverWebhook::class);
});
