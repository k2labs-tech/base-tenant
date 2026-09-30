<?php

declare(strict_types=1);

use Base\Tenant\Facades\Suppression;
use Base\Tenant\Models\EmailSuppression;
use Base\Tenant\Suppressions\MailgunDriver;
use Base\Tenant\Suppressions\PostmarkDriver;
use Base\Tenant\Suppressions\ResendDriver;
use Illuminate\Http\Request;

/**
 * Un secreto de Svix con la forma de los de verdad: `whsec_` y base64.
 */
const SECRETO_RESEND = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';

beforeEach(function () {
    config([
        'base-tenant.suppressions.drivers' => [
            'mailgun' => MailgunDriver::class,
            'postmark' => PostmarkDriver::class,
            'resend' => ResendDriver::class,
        ],
        'base-tenant.suppressions.postmark_webhook_username' => 'postmark',
        'base-tenant.suppressions.postmark_webhook_password' => 'secreto-largo',
        'base-tenant.suppressions.resend_signing_secret' => SECRETO_RESEND,
    ]);
});

// ------------------------------------------------------------------- Postmark

function llamadaPostmark(array $carga, ?string $usuario = 'postmark', ?string $clave = 'secreto-largo')
{
    $cabeceras = $usuario === null ? [] : ['Authorization' => 'Basic '.base64_encode($usuario.':'.$clave)];

    return test()->withHeaders($cabeceras)
        ->postJson(route('base-tenant.webhooks.suppressions', ['driver' => 'postmark']), $carga);
}

test('postmark: un rebote permanente con credenciales buenas suprime', function () {
    llamadaPostmark([
        'RecordType' => 'Bounce',
        'Type' => 'HardBounce',
        'TypeCode' => 1,
        'Email' => 'Rebota@Ejemplo.test',
        'Inactive' => true,
    ])->assertOk();

    expect(Suppression::isSuppressed('rebota@ejemplo.test'))->toBeTrue()
        ->and(EmailSuppression::first()->reason)->toBe(EmailSuppression::BOUNCE)
        ->and(EmailSuppression::first()->source)->toBe('postmark');
});

test('postmark: sin credenciales o con otras no se acepta nada', function (?string $usuario, ?string $clave) {
    llamadaPostmark([
        'RecordType' => 'Bounce', 'Type' => 'HardBounce', 'Email' => 'x@y.test',
    ], $usuario, $clave)->assertStatus(401);

    expect(EmailSuppression::count())->toBe(0);
})->with([
    'sin cabecera' => [null, null],
    'clave mala' => ['postmark', 'otra'],
    'usuario malo' => ['otro', 'secreto-largo'],
]);

test('postmark: sin credenciales configuradas el webhook está cerrado', function () {
    config(['base-tenant.suppressions.postmark_webhook_password' => null]);

    llamadaPostmark(['RecordType' => 'Bounce', 'Type' => 'HardBounce', 'Email' => 'x@y.test'], 'postmark', '')
        ->assertStatus(401);
});

test('postmark: un rebote temporal no suprime', function () {
    $peticion = Request::create('/', 'POST', [
        'RecordType' => 'Bounce', 'Type' => 'SoftBounce', 'Email' => 'llena@ejemplo.test', 'Inactive' => false,
    ]);

    expect((new PostmarkDriver)->extract($peticion))->toBe([]);
});

test('postmark: una queja y una baja en su lista sí suprimen', function () {
    $driver = new PostmarkDriver;

    $queja = Request::create('/', 'POST', [
        'RecordType' => 'SpamComplaint', 'Type' => 'SpamComplaint', 'TypeCode' => 512, 'Email' => 'q@y.test',
    ]);

    $baja = Request::create('/', 'POST', [
        'RecordType' => 'SubscriptionChange', 'Recipient' => 'b@y.test',
        'SuppressSending' => true, 'SuppressionReason' => 'ManualSuppression',
    ]);

    $reactivada = Request::create('/', 'POST', [
        'RecordType' => 'SubscriptionChange', 'Recipient' => 'r@y.test',
        'SuppressSending' => false, 'SuppressionReason' => null,
    ]);

    expect($driver->extract($queja)[0]['reason'])->toBe(EmailSuppression::COMPLAINT)
        ->and($driver->extract($baja)[0]['reason'])->toBe(EmailSuppression::UNSUBSCRIBE)
        ->and($driver->extract($reactivada))->toBe([]);
});

// --------------------------------------------------------------------- Resend

/**
 * Firma como lo hace Svix: HMAC-SHA256 de `id.timestamp.cuerpo` con la parte
 * base64 del secreto como clave.
 */
function firmaResend(string $id, string $momento, string $cuerpo, string $secreto = SECRETO_RESEND): string
{
    $clave = base64_decode(substr($secreto, 6));

    return 'v1,'.base64_encode(hash_hmac('sha256', "{$id}.{$momento}.{$cuerpo}", $clave, true));
}

function llamadaResend(string $cuerpo, array $cabeceras)
{
    $servidor = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

    foreach ($cabeceras as $nombre => $valor) {
        $servidor['HTTP_'.strtoupper(str_replace('-', '_', $nombre))] = $valor;
    }

    return test()->call(
        'POST',
        route('base-tenant.webhooks.suppressions', ['driver' => 'resend']),
        server: $servidor,
        content: $cuerpo,
    );
}

function cuerpoResend(string $tipo, array $datos): string
{
    return json_encode(['type' => $tipo, 'created_at' => now()->toIso8601String(), 'data' => $datos]);
}

test('resend: un rebote firmado suprime a cada destinatario', function () {
    $cuerpo = cuerpoResend('email.bounced', [
        'email_id' => 'abc-123',
        'to' => ['uno@ejemplo.test', 'Dos <dos@ejemplo.test>'],
        'bounce' => ['type' => 'Permanent', 'subType' => 'General', 'message' => 'No existe'],
    ]);
    $momento = (string) time();

    llamadaResend($cuerpo, [
        'svix-id' => 'msg_1',
        'svix-timestamp' => $momento,
        'svix-signature' => 'v1,otra-firma-de-una-clave-rotada '.firmaResend('msg_1', $momento, $cuerpo),
    ])->assertOk();

    expect(Suppression::isSuppressed('uno@ejemplo.test'))->toBeTrue()
        ->and(Suppression::isSuppressed('dos@ejemplo.test'))->toBeTrue()
        ->and(EmailSuppression::where('email', 'dos@ejemplo.test')->first()->source)->toBe('resend');
});

test('resend: una queja firmada suprime como queja', function () {
    $cuerpo = cuerpoResend('email.complained', ['to' => ['q@ejemplo.test']]);
    $momento = (string) time();

    llamadaResend($cuerpo, [
        'svix-id' => 'msg_2',
        'svix-timestamp' => $momento,
        'svix-signature' => firmaResend('msg_2', $momento, $cuerpo),
    ])->assertOk();

    expect(EmailSuppression::first()->reason)->toBe(EmailSuppression::COMPLAINT);
});

test('resend: una firma que no cuadra con el cuerpo se rechaza', function () {
    $firmado = cuerpoResend('email.bounced', ['to' => ['x@ejemplo.test']]);
    $enviado = cuerpoResend('email.bounced', ['to' => ['victima@ejemplo.test']]);
    $momento = (string) time();

    llamadaResend($enviado, [
        'svix-id' => 'msg_3',
        'svix-timestamp' => $momento,
        'svix-signature' => firmaResend('msg_3', $momento, $firmado),
    ])->assertStatus(401);

    expect(EmailSuppression::count())->toBe(0);
});

test('resend: una firma buena pero vieja no se acepta', function () {
    $cuerpo = cuerpoResend('email.bounced', ['to' => ['x@ejemplo.test']]);
    $momento = (string) (time() - 3600);

    llamadaResend($cuerpo, [
        'svix-id' => 'msg_4',
        'svix-timestamp' => $momento,
        'svix-signature' => firmaResend('msg_4', $momento, $cuerpo),
    ])->assertStatus(401);
});

test('resend: sin secreto configurado el webhook está cerrado', function () {
    config(['base-tenant.suppressions.resend_signing_secret' => null]);

    $cuerpo = cuerpoResend('email.bounced', ['to' => ['x@ejemplo.test']]);
    $momento = (string) time();

    llamadaResend($cuerpo, [
        'svix-id' => 'msg_5',
        'svix-timestamp' => $momento,
        'svix-signature' => firmaResend('msg_5', $momento, $cuerpo),
    ])->assertStatus(401);
});

test('resend: un rebote que no es permanente y los demás eventos no suprimen', function () {
    $driver = new ResendDriver;

    $temporal = Request::create('/', 'POST', [
        'type' => 'email.bounced',
        'data' => ['to' => ['x@y.test'], 'bounce' => ['type' => 'Transient']],
    ]);

    $entregado = Request::create('/', 'POST', [
        'type' => 'email.delivered',
        'data' => ['to' => ['x@y.test']],
    ]);

    expect($driver->extract($temporal))->toBe([])
        ->and($driver->extract($entregado))->toBe([]);
});
