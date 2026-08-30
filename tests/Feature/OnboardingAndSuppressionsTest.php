<?php

declare(strict_types=1);

use Base\Tenant\Exceptions\ModuleDisabledException;
use Base\Tenant\Facades\Onboarding;
use Base\Tenant\Facades\Suppression;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Livewire\Onboarding\Checklist;
use Base\Tenant\Models\EmailSuppression;
use Base\Tenant\Suppressions\BlockSuppressedRecipients;
use Base\Tenant\Suppressions\MailgunDriver;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\Mime\Email;

/**
 * Un paso que el test dirige.
 */
class PasoDirigido
{
    public static bool $done = false;

    public function __invoke($account): bool
    {
        return self::$done;
    }
}

beforeEach(function () {
    PasoDirigido::$done = false;

    config([
        'base-tenant.onboarding.steps' => [
            'uno' => ['label' => 'Paso uno', 'completed' => PasoDirigido::class],
            'dos' => ['label' => 'Paso dos'],
        ],
        'base-tenant.suppressions.mailgun_signing_key' => 'clave-de-firma',
    ]);
});

// ---------------------------------------------------------------- Onboarding

test('el progreso cuenta los pasos hechos', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        expect(Onboarding::progress())->toBe(0);

        PasoDirigido::$done = true;
        Onboarding::refresh();

        expect(Onboarding::progress())->toBe(50);
    });
});

/**
 * Un paso sin comprobación no se puede dar por hecho: hacerlo escondería
 * trabajo que no ha ocurrido.
 */
test('un paso sin comprobación nunca se marca solo', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        $dos = Onboarding::steps()->firstWhere('step.key', 'dos');

        expect($dos['complete'])->toBeFalse();
    });
});

test('descartar esconde la lista para siempre', function () {
    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () use ($cuenta) {
        expect(Onboarding::shouldShow())->toBeTrue();

        Onboarding::dismiss();

        expect(Onboarding::shouldShow())->toBeFalse()
            ->and($cuenta->fresh()->onboarded_at)->not->toBeNull();
    });
});

/**
 * Cada comprobación cuesta una consulta y el widget las pide todas: sin la
 * memoria, la lista es lo más caro de la página.
 */
test('las comprobaciones se ejecutan una vez por petición', function () {
    $cuenta = $this->createAccount();

    $veces = 0;

    config(['base-tenant.onboarding.steps' => [
        'contado' => ['label' => 'x', 'completed' => PasoDirigido::class],
    ]]);

    Tenant::runFor($cuenta, function () {
        Onboarding::steps();
        Onboarding::steps();
        Onboarding::progress();

        // Si se recalculara, cambiar la respuesta se vería sin refrescar.
        PasoDirigido::$done = true;

        expect(Onboarding::progress())->toBe(0);

        Onboarding::refresh();

        expect(Onboarding::progress())->toBe(100);
    });
});

/**
 * Una lista con todo tachado ocupa el mejor sitio de la página para recordar
 * trabajo ya hecho.
 */
test('el widget no pinta nada cuando ya no hace falta', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta);

    $this->actingAsTenant($usuaria, $cuenta);

    Livewire::test(Checklist::class)->assertSee('Paso uno');

    Livewire::test(Checklist::class)->call('dismiss');

    Livewire::test(Checklist::class)->assertDontSee('Paso uno');
});

test('con el módulo apagado el widget no aparece', function () {
    config(['base-tenant.onboarding.enabled' => false]);

    $cuenta = $this->createAccount();

    Tenant::runFor($cuenta, function () {
        expect(Onboarding::shouldShow())->toBeFalse();
    });
});

// -------------------------------------------------------------- Supresiones

/**
 * Comparar direcciones distinguiendo mayúsculas deja pasar `Ada@Example.test`
 * por una guarda que tiene `ada@example.test`.
 */
test('la dirección se guarda y se compara en minúsculas', function () {
    Suppression::suppress('Ada@Example.TEST', EmailSuppression::BOUNCE);

    expect(Suppression::isSuppressed('ada@example.test'))->toBeTrue()
        ->and(Suppression::isSuppressed('ADA@EXAMPLE.TEST'))->toBeTrue()
        ->and(EmailSuppression::first()->email)->toBe('ada@example.test');
});

/**
 * La guarda de verdad: no hace falta acordarse de nada en el punto de envío.
 */
test('ningún correo sale hacia una dirección suprimida', function () {
    Mail::fake();

    Suppression::suppress('bloqueada@ejemplo.test', EmailSuppression::COMPLAINT);

    // `Mail::fake()` no dispara MessageSending, así que se comprueba el
    // listener directamente sobre un mensaje real.
    $listener = app(BlockSuppressedRecipients::class);

    $bloqueado = new Email;
    $bloqueado->to('bloqueada@ejemplo.test')->subject('Hola')->text('x');

    $permitido = new Email;
    $permitido->to('libre@ejemplo.test')->subject('Hola')->text('x');

    expect($listener->handle(new MessageSending($bloqueado)))->toBeFalse()
        ->and($listener->handle(new MessageSending($permitido)))->toBeTrue();
});

test('una copia oculta suprimida también corta el envío', function () {
    Suppression::suppress('oculta@ejemplo.test', EmailSuppression::BOUNCE);

    $listener = app(BlockSuppressedRecipients::class);

    $mensaje = new Email;
    $mensaje->to('libre@ejemplo.test')->bcc('oculta@ejemplo.test')->subject('Hola')->text('x');

    expect($listener->handle(new MessageSending($mensaje)))->toBeFalse();
});

test('soltar una dirección la devuelve a la circulación', function () {
    Suppression::suppress('vuelve@ejemplo.test', EmailSuppression::BOUNCE);

    expect(Suppression::isSuppressed('vuelve@ejemplo.test'))->toBeTrue();

    Suppression::release('vuelve@ejemplo.test');

    expect(Suppression::isSuppressed('vuelve@ejemplo.test'))->toBeFalse();
});

test('el webhook de Mailgun rechaza una firma que no cuadra', function () {
    $this->postJson(route('base-tenant.webhooks.suppressions', ['driver' => 'mailgun']), [
        'signature' => ['timestamp' => (string) time(), 'token' => 'abc', 'signature' => 'inventada'],
        'event-data' => ['event' => 'failed', 'severity' => 'permanent', 'recipient' => 'x@y.test'],
    ])->assertStatus(401);

    expect(EmailSuppression::count())->toBe(0);
});

/**
 * Sin ventana temporal, una petición capturada vale para siempre y se puede
 * reproducir para suprimir cualquier dirección.
 */
test('una firma buena pero vieja no se acepta', function () {
    $timestamp = (string) (time() - 3600);
    $token = 'abc';

    $this->postJson(route('base-tenant.webhooks.suppressions', ['driver' => 'mailgun']), [
        'signature' => [
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => hash_hmac('sha256', $timestamp.$token, 'clave-de-firma'),
        ],
        'event-data' => ['event' => 'failed', 'severity' => 'permanent', 'recipient' => 'x@y.test'],
    ])->assertStatus(401);
});

test('un rebote permanente suprime la dirección', function () {
    $timestamp = (string) time();
    $token = 'abc';

    $this->postJson(route('base-tenant.webhooks.suppressions', ['driver' => 'mailgun']), [
        'signature' => [
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => hash_hmac('sha256', $timestamp.$token, 'clave-de-firma'),
        ],
        'event-data' => ['event' => 'failed', 'severity' => 'permanent', 'recipient' => 'rebota@ejemplo.test'],
    ])->assertOk();

    expect(Suppression::isSuppressed('rebota@ejemplo.test'))->toBeTrue()
        ->and(EmailSuppression::first()->reason)->toBe(EmailSuppression::BOUNCE);
});

/**
 * Un buzón que estaba lleno esta mañana no es una dirección que se haya ido.
 * Suprimir por eso pierde clientes por una bandeja de entrada llena.
 */
test('un fallo temporal no suprime nada', function () {
    $driver = new MailgunDriver;

    $peticion = Request::create('/', 'POST', [
        'event-data' => ['event' => 'failed', 'severity' => 'temporary', 'recipient' => 'llena@ejemplo.test'],
    ]);

    expect($driver->extract($peticion))->toBe([]);
});

test('una queja y una baja sí suprimen', function () {
    $driver = new MailgunDriver;

    foreach ([['complained', EmailSuppression::COMPLAINT], ['unsubscribed', EmailSuppression::UNSUBSCRIBE]] as [$evento, $motivo]) {
        $peticion = Request::create('/', 'POST', [
            'event-data' => ['event' => $evento, 'recipient' => 'x@y.test'],
        ]);

        expect($driver->extract($peticion)[0]['reason'])->toBe($motivo);
    }
});

test('importar carga una lista y cuenta lo que no sirve', function () {
    $ruta = tempnam(sys_get_temp_dir(), 'sup').'.csv';

    file_put_contents($ruta, "email,reason\nuna@ejemplo.test,bounce\nno-es-un-correo,bounce\n,\ndos@ejemplo.test,complaint\n");

    $this->artisan('k2labs-base:import-suppressions', ['file' => $ruta])
        ->expectsOutputToContain('Suppressed 2 addresses')
        ->assertSuccessful();

    expect(Suppression::isSuppressed('una@ejemplo.test'))->toBeTrue()
        ->and(EmailSuppression::where('email', 'dos@ejemplo.test')->first()->reason)
        ->toBe(EmailSuppression::COMPLAINT);

    @unlink($ruta);
});

test('con el módulo apagado no se suprime nada', function () {
    config(['base-tenant.suppressions.enabled' => false]);

    expect(Suppression::isSuppressed('cualquiera@ejemplo.test'))->toBeFalse()
        ->and(fn () => Suppression::suppress('x@y.test', 'manual'))
        ->toThrow(ModuleDisabledException::class);
});
