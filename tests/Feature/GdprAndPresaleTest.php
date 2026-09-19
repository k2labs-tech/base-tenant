<?php

declare(strict_types=1);

use Base\Tenant\Facades\Presale;
use Base\Tenant\Facades\Tenant;
use Base\Tenant\Gdpr\DataErasureService;
use Base\Tenant\Gdpr\DataExportService;
use Base\Tenant\Livewire\AcceptTerms;
use Base\Tenant\Livewire\Auth\Register;
use Base\Tenant\Livewire\Presale\WaitlistForm;
use Base\Tenant\Models\ActivityLog;
use Base\Tenant\Models\File;
use Base\Tenant\Models\MagicLink;
use Base\Tenant\Models\Passkey;
use Base\Tenant\Models\SocialAccount;
use Base\Tenant\Models\User;
use Base\Tenant\Models\UserInvite;
use Base\Tenant\Models\UserSession;
use Base\Tenant\Models\WaitlistSignup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

// ---------------------------------------------------------------------- RGPD

test('la exportación trae un fichero por dominio y un manifiesto', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta, null, ['name' => 'Ada', 'email' => 'ada@ejemplo.test']);

    Tenant::runFor($cuenta, fn () => ActivityLog::create([
        'action' => 'created',
        'description' => 'hizo algo',
        'causer_id' => $usuaria->getKey(),
        'causer_type' => $usuaria->getMorphClass(),
    ]));

    $ruta = app(DataExportService::class)->build($usuaria);

    $zip = new ZipArchive;
    $zip->open($ruta);

    $perfil = json_decode($zip->getFromName('profile.json'), true);
    $actividad = json_decode($zip->getFromName('activity.json'), true);
    $manifiesto = json_decode($zip->getFromName('manifest.json'), true);

    $zip->close();
    @unlink($ruta);

    expect($perfil['email'])->toBe('ada@ejemplo.test')
        ->and($actividad)->toHaveCount(1)
        ->and($actividad[0]['description'])->toBe('hizo algo')
        // «Vacío» y «no se preguntó» son respuestas distintas a una petición
        // legal, y el manifiesto es lo que las separa.
        ->and(collect($manifiesto['domains'])->pluck('name')->all())->toBe(['profile', 'activity', 'sessions']);
});

/**
 * Un hash sigue siendo una credencial, y una exportación es un fichero que
 * viaja.
 */
test('la exportación no incluye la contraseña ni el secreto de dos factores', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta);
    $usuaria->update(['two_factor_secret' => encrypt('SECRETO')]);

    $ruta = app(DataExportService::class)->build($usuaria->fresh());

    $contenido = file_get_contents($ruta);

    @unlink($ruta);

    expect($contenido)->not->toContain('password')
        ->and($contenido)->not->toContain('two_factor');
});

test('el comando escribe el archivo donde se le pida', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta, null, ['email' => 'ada@ejemplo.test']);

    $ruta = sys_get_temp_dir().'/export-'.uniqid().'.zip';

    $this->artisan('k2labs-base:export-user-data', ['user' => 'ada@ejemplo.test', '--path' => $ruta])
        ->assertSuccessful();

    expect(file_exists($ruta))->toBeTrue();

    @unlink($ruta);
});

test('el comando avisa cuando no encuentra a nadie', function () {
    $this->artisan('k2labs-base:export-user-data', ['user' => 'nadie@ejemplo.test'])->assertFailed();
});

/**
 * El borrado blando es un periodo de gracia, no un archivador.
 */
test('la purga destruye lo que lleva borrado más de la retención', function () {
    $cuenta = $this->createAccount();
    $vieja = $this->createUser($cuenta, null, ['email' => 'vieja@ejemplo.test']);
    $reciente = $this->createUser($cuenta, null, ['email' => 'reciente@ejemplo.test']);

    $vieja->delete();
    $reciente->delete();

    User::withTrashed()->whereKey($vieja->getKey())->update(['deleted_at' => now()->subDays(60)]);

    $this->artisan('k2labs-base:purge-deleted', ['--days' => 30])->assertSuccessful();

    expect(User::withTrashed()->whereKey($vieja->getKey())->exists())->toBeFalse()
        ->and(User::withTrashed()->whereKey($reciente->getKey())->exists())->toBeTrue();
});

/**
 * Sesiones, enlaces de acceso y passkeys son datos de la persona -- una
 * dirección, la huella del navegador, una credencial -- y ninguna de las
 * tablas cascada desde `users`. Sin esto la purga daba al usuario por borrado
 * mientras sus filas seguían respondiendo a una consulta.
 */
test('la purga se lleva las sesiones, los enlaces de acceso y las passkeys', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta, null, ['email' => 'ada@ejemplo.test']);

    UserSession::factory()->create(['user_id' => $usuaria->getKey()]);
    MagicLink::factory()->create(['user_id' => $usuaria->getKey(), 'email' => 'ada@ejemplo.test']);
    MagicLink::factory()->create(['user_id' => null, 'email' => 'ada@ejemplo.test']);
    Passkey::create([
        'user_id' => $usuaria->getKey(),
        'credential_id' => 'Y3JlZGVuY2lhbA',
        'name' => 'MacBook',
        'record' => ['publicKeyCredentialId' => 'Y3JlZGVuY2lhbA'],
    ]);
    SocialAccount::create([
        'user_id' => $usuaria->getKey(),
        'provider' => 'google',
        'provider_id' => '123',
        'token' => 'secreto',
    ]);
    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'Bienvenida',
        'notifiable_type' => $usuaria->getMorphClass(),
        'notifiable_id' => $usuaria->getKey(),
        'data' => json_encode(['password' => 'inicial']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Tenant::runFor($cuenta, fn () => UserInvite::create([
        'email' => 'ada@ejemplo.test',
        'account_id' => $cuenta->getKey(),
        'token' => Str::random(64),
        'invited_by' => $usuaria->getKey(),
        'expires_at' => now()->addDay(),
    ]));

    $usuaria->delete();
    User::withTrashed()->whereKey($usuaria->getKey())->update(['deleted_at' => now()->subDays(60)]);

    $this->artisan('k2labs-base:purge-deleted', ['--days' => 30])->assertSuccessful();

    expect(UserSession::query()->where('user_id', $usuaria->getKey())->exists())->toBeFalse()
        ->and(MagicLink::query()->where('email', 'ada@ejemplo.test')->exists())->toBeFalse()
        ->and(Passkey::query()->where('user_id', $usuaria->getKey())->exists())->toBeFalse()
        ->and(SocialAccount::query()->where('user_id', $usuaria->getKey())->exists())->toBeFalse()
        ->and(DB::table('account_user')->where('user_id', $usuaria->getKey())->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('notifiable_id', $usuaria->getKey())->exists())->toBeFalse()
        ->and(DB::table('user_invites')->where('email', 'ada@ejemplo.test')->exists())->toBeFalse();
});

/**
 * Un borrador que no implementa la interfaz es un error de configuración, no
 * un dominio que se salta en silencio.
 */
test('un borrador que no es tal se rechaza al arrancar la purga', function () {
    config(['base-tenant.gdpr.erasers' => [stdClass::class]]);

    expect(fn () => app(DataErasureService::class)->erasers())
        ->toThrow(RuntimeException::class);
});

/**
 * En el modelo y no sólo en el comando: cualquier camino que destruya a un
 * usuario tiene que limpiar tras él, no sólo el que se acordó de hacerlo.
 */
test('un borrado definitivo directo también se lleva los datos personales', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta);

    UserSession::factory()->create(['user_id' => $usuaria->getKey()]);

    $usuaria->forceDelete();

    expect(UserSession::query()->where('user_id', $usuaria->getKey())->exists())->toBeFalse();
});

test('el ensayo de la purga no destruye nada', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta);

    $usuaria->delete();
    User::withTrashed()->whereKey($usuaria->getKey())->update(['deleted_at' => now()->subDays(60)]);

    $this->artisan('k2labs-base:purge-deleted', ['--days' => 30, '--dry-run' => true])->assertSuccessful();

    expect(User::withTrashed()->whereKey($usuaria->getKey())->exists())->toBeTrue();
});

/**
 * Un registro de actividad sin la persona sigue siendo un registro de
 * actividad; borrarlo destruiría la constancia de lo que se hizo con los datos
 * de los demás.
 */
test('la purga anonimiza la actividad en vez de borrarla', function () {
    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta);

    Tenant::runFor($cuenta, fn () => ActivityLog::create([
        'action' => 'deleted',
        'description' => 'borró la factura 42',
        'causer_id' => $usuaria->getKey(),
        'causer_type' => $usuaria->getMorphClass(),
    ]));

    $usuaria->delete();
    User::withTrashed()->whereKey($usuaria->getKey())->update(['deleted_at' => now()->subDays(60)]);

    $this->artisan('k2labs-base:purge-deleted', ['--days' => 30])->assertSuccessful();

    $entrada = ActivityLog::query()->acrossAccounts()->first();

    expect($entrada)->not->toBeNull()
        ->and($entrada->description)->toBe('borró la factura 42')
        ->and($entrada->causer_id)->toBeNull();
});

test('la purga se lleva los bytes antes que la fila', function () {
    Storage::fake('s3');

    config(['base-tenant.files.disk' => 's3', 'base-tenant.files.driver' => 'local']);

    $cuenta = $this->createAccount();

    $fichero = Tenant::runFor($cuenta, fn () => File::create([
        'collection' => 'library',
        'disk' => 's3',
        'path' => "accounts/{$cuenta->getKey()}/files/x/doc.pdf",
        'name' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'size' => 10,
    ]));

    Storage::disk('s3')->put($fichero->path, 'contenido');

    $fichero->delete();
    File::withTrashed()->acrossAccounts()->whereKey($fichero->getKey())->update(['deleted_at' => now()->subDays(60)]);

    $this->artisan('k2labs-base:purge-deleted', ['--days' => 30])->assertSuccessful();

    Storage::disk('s3')->assertMissing($fichero->path);

    expect(File::withTrashed()->acrossAccounts()->whereKey($fichero->getKey())->exists())->toBeFalse();
});

test('quien no ha aceptado la versión actual va a la pantalla de términos', function () {
    config(['base-tenant.gdpr.terms_version' => '2.0']);

    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta, null, ['terms_version' => '1.0']);

    $this->actingAsTenant($usuaria, $cuenta);

    Livewire::test(AcceptTerms::class)
        ->set('accepted', true)
        ->call('accept')
        ->assertHasNoErrors();

    expect($usuaria->fresh()->terms_version)->toBe('2.0')
        ->and($usuaria->fresh()->terms_accepted_at)->not->toBeNull();
});

test('sin marcar la casilla no se acepta nada', function () {
    config(['base-tenant.gdpr.terms_version' => '2.0']);

    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta, null, ['terms_version' => '1.0']);

    $this->actingAsTenant($usuaria, $cuenta);

    Livewire::test(AcceptTerms::class)->call('accept')->assertHasErrors('accepted');

    expect($usuaria->fresh()->terms_version)->toBe('1.0');
});

// ------------------------------------------------------------------ Preventa

test('la lista de espera admite a la misma persona dos veces sin quejarse', function () {
    Presale::join('ada@ejemplo.test', ['source' => 'landing']);
    Presale::join('ADA@ejemplo.test', ['name' => 'Ada']);

    expect(WaitlistSignup::count())->toBe(1)
        ->and(WaitlistSignup::first()->email)->toBe('ada@ejemplo.test')
        ->and(WaitlistSignup::first()->name)->toBe('Ada');
});

test('el formulario da las gracias y no dice si ya estabas', function () {
    Livewire::test(WaitlistForm::class)
        ->set('email', 'ada@ejemplo.test')
        ->call('join')
        ->assertSet('joined', true)
        ->assertSee(__('base-tenant::presale.waitlist_thanks'));

    Livewire::test(WaitlistForm::class)
        ->set('email', 'ada@ejemplo.test')
        ->call('join')
        ->assertSet('joined', true)
        ->assertHasNoErrors();
});

/**
 * El número de una landing es una promesa: contarlo desde las cuentas de
 * verdad y no desde un contador guardado evita que discrepen en cuanto haya
 * una devolución.
 */
test('las plazas restantes se cuentan desde las cuentas con cliente de pago', function () {
    config(['base-tenant.presale.enabled' => true, 'base-tenant.presale.seats' => 3]);

    expect(Presale::seatsLeft())->toBe(3);

    $this->createAccount()->update(['stripe_id' => 'cus_1']);
    $this->createAccount()->update(['stripe_id' => 'cus_2']);

    expect(Presale::seatsLeft())->toBe(1)
        ->and(Presale::soldOut())->toBeFalse();

    $this->createAccount()->update(['stripe_id' => 'cus_3']);

    expect(Presale::seatsLeft())->toBe(0)
        ->and(Presale::soldOut())->toBeTrue();
});

/**
 * Dejar el formulario en pie y rechazar al enviarlo desperdicia el rato que la
 * persona ha pasado rellenándolo.
 */
test('con la pre-venta activa el registro estándar está cerrado', function () {
    config(['base-tenant.presale.enabled' => true]);

    Livewire::test(Register::class)->assertRedirect(route('base-tenant.home'));
});

/**
 * Una petición POST no pasa por el montaje: sin la segunda comprobación el
 * registro seguiría abierto para quien lo llamara directamente.
 */
test('el registro tampoco se puede forzar llamándolo directamente', function () {
    config(['base-tenant.presale.enabled' => true]);

    // Sobre el componente directamente: `Livewire::test()` monta primero, y el
    // montaje ya redirige, así que por ahí nunca se llegaría a la guarda que
    // este test existe para comprobar.
    $componente = new Register;
    $componente->name = 'Ada';
    $componente->companyName = 'Empresa';
    $componente->email = 'ada@ejemplo.test';
    $componente->password = 'contrasena-larga';
    $componente->password_confirmation = 'contrasena-larga';

    expect(fn () => $componente->register())
        ->toThrow(HttpException::class);

    expect(User::where('email', 'ada@ejemplo.test')->exists())->toBeFalse();
});

test('sin pre-venta el registro funciona igual que siempre', function () {
    Livewire::test(Register::class)->assertOk();
});

test('abrir convierte la lista en cuentas e invitaciones', function () {
    config(['base-tenant.presale.enabled' => true]);

    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $this->createUser($cuenta, null, ['is_admin' => true, 'email' => 'admin@ejemplo.test']);

    Presale::join('una@ejemplo.test');
    Presale::join('otra@ejemplo.test');

    $this->artisan('k2labs-base:presale-open', ['--batch' => 1])->assertSuccessful();

    expect(WaitlistSignup::whereNotNull('invited_at')->count())->toBe(1)
        ->and(WaitlistSignup::waiting()->count())->toBe(1)
        ->and(UserInvite::query()->acrossAccounts()->count())->toBe(1);
});

test('el ensayo de apertura no invita a nadie', function () {
    config(['base-tenant.presale.enabled' => true]);

    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $this->createUser($cuenta, null, ['is_admin' => true]);

    Presale::join('una@ejemplo.test');

    $this->artisan('k2labs-base:presale-open', ['--dry-run' => true])->assertSuccessful();

    expect(WaitlistSignup::waiting()->count())->toBe(1);
});

test('con la pre-venta apagada abrir no hace nada', function () {
    Presale::join('una@ejemplo.test');

    $this->artisan('k2labs-base:presale-open')->assertSuccessful();

    expect(WaitlistSignup::waiting()->count())->toBe(1);
});

test('con el módulo de RGPD apagado los comandos no tocan nada', function () {
    config(['base-tenant.gdpr.enabled' => false]);

    $cuenta = $this->createAccount();
    $usuaria = $this->createUser($cuenta);
    $usuaria->delete();
    User::withTrashed()->whereKey($usuaria->getKey())->update(['deleted_at' => now()->subDays(60)]);

    $this->artisan('k2labs-base:purge-deleted')->assertSuccessful();

    expect(User::withTrashed()->whereKey($usuaria->getKey())->exists())->toBeTrue();
});
