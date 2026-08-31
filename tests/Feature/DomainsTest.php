<?php

declare(strict_types=1);

use Base\Tenant\Domains\Contracts\DnsLookup;
use Base\Tenant\Domains\DomainManager;
use Base\Tenant\Domains\DomainVerifier;
use Base\Tenant\Exceptions\DomainException;
use Base\Tenant\Exceptions\ModuleDisabledException;
use Base\Tenant\Facades\Domain;
use Base\Tenant\Livewire\DomainManager as DomainManagerComponent;
use Base\Tenant\Models\AccountDomain;
use Base\Tenant\Tenancy\Resolvers\DomainTenantResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Livewire\Livewire;

/**
 * Un DNS de mentira: publica lo que se le diga y nada más.
 */
function dnsFalso(array $registros = []): void
{
    app()->bind(DnsLookup::class, fn (): DnsLookup => new class($registros) implements DnsLookup
    {
        public function __construct(private array $registros) {}

        public function txt(string $host): array
        {
            return $this->registros[$host] ?? [];
        }
    });

    // El verificador y el manager son singletons y la fachada además cachea
    // el que ya resolvió, así que hay que olvidar los tres o el falso no llega
    // a usarse nunca.
    app()->forgetInstance(DomainVerifier::class);
    app()->forgetInstance(DomainManager::class);
    Domain::clearResolvedInstances();
}

beforeEach(function () {
    config(['base-tenant.tenancy.central_domains' => ['tuapp.test']]);
});

// ---------------------------------------------------------------------
// Subdominios
// ---------------------------------------------------------------------

test('un subdominio bien formado se puede reclamar', function () {
    $cuenta = $this->createAccount();

    Domain::claimSubdomain($cuenta, 'Mi-Empresa');

    expect($cuenta->fresh()->subdomain)->toBe('mi-empresa');
});

test('un subdominio reservado se rechaza nombrándolo', function () {
    $cuenta = $this->createAccount();

    expect(fn () => Domain::claimSubdomain($cuenta, 'admin'))
        ->toThrow(DomainException::class);

    expect($cuenta->fresh()->subdomain)->toBeNull();
});

/**
 * La lista de reservados es la que impide que un cliente se quede con `www` o
 * con `api` y tire por tierra la propia instalación. Se comprueban varias, no
 * una, porque el fallo típico es reservar sólo las obvias.
 */
test('las reservas cubren los nombres que colisionan con la instalación', function (string $nombre) {
    expect(Domain::isSubdomainReserved($nombre))->toBeTrue();
})->with(['www', 'api', 'admin', 'mail', 'app', 'billing', 'status']);

test('un subdominio mal formado se rechaza', function (string $nombre) {
    expect(Domain::isSubdomainWellFormed($nombre))->toBeFalse();
})->with([
    'ab',                 // por debajo del mínimo
    '-empresa',           // empieza en guion
    'empresa-',           // acaba en guion
    'mi empresa',         // espacio
    'mi_empresa',         // guion bajo
    'MI.EMPRESA',         // punto: sería otro nivel de dominio
    '12345',              // sólo dígitos
]);

test('dos cuentas no pueden tener el mismo subdominio', function () {
    $primera = $this->createAccount();
    $segunda = $this->createAccount();

    Domain::claimSubdomain($primera, 'compartido');

    expect(Domain::isSubdomainAvailable('compartido', $segunda))->toBeFalse();
    expect(fn () => Domain::claimSubdomain($segunda, 'compartido'))
        ->toThrow(DomainException::class);
});

test('reclamar el subdominio que ya tienes no es una colisión consigo mismo', function () {
    $cuenta = $this->createAccount();

    Domain::claimSubdomain($cuenta, 'mi-empresa');

    expect(Domain::isSubdomainAvailable('mi-empresa', $cuenta))->toBeTrue();
});

test('el cambio de subdominio queda en el registro de actividad', function () {
    $cuenta = $this->createAccount();

    Domain::claimSubdomain($cuenta, 'antes');
    Domain::claimSubdomain($cuenta, 'despues');

    $this->assertDatabaseHas('activity_log', ['action' => 'subdomain.changed']);
});

test('un subdominio resuelve la cuenta desde el host', function () {
    $cuenta = $this->createAccount();
    Domain::claimSubdomain($cuenta, 'acme');

    $resuelta = (new DomainTenantResolver)->resolve(
        Request::create('https://acme.tuapp.test/dashboard')
    );

    expect($resuelta?->getKey())->toBe($cuenta->getKey());
});

// ---------------------------------------------------------------------
// Dominios propios
// ---------------------------------------------------------------------

test('un dominio se añade pendiente y con su token', function () {
    $cuenta = $this->createAccount();

    $dominio = Domain::addDomain($cuenta, 'app.micliente.com');

    expect($dominio->status)->toBe(AccountDomain::STATUS_PENDING)
        ->and($dominio->verification_token)->toStartWith('base-tenant-verify=')
        ->and($dominio->is_primary)->toBeFalse();
});

test('pegar una URL entera en vez de un dominio se entiende', function () {
    $cuenta = $this->createAccount();

    $dominio = Domain::addDomain($cuenta, 'https://App.MiCliente.com/panel');

    expect($dominio->hostname)->toBe('app.micliente.com');
});

test('un dominio mal formado se rechaza', function (string $nombre) {
    $cuenta = $this->createAccount();

    expect(fn () => Domain::addDomain($cuenta, $nombre))->toThrow(DomainException::class);
})->with(['sinpunto', 'espacio .com', '.com', 'micliente.', '-mal.com']);

/**
 * Un cliente que reclama el dominio central se queda con la puerta de entrada
 * del producto para todo el mundo.
 */
test('el dominio central del producto no se puede reclamar', function (string $nombre) {
    $cuenta = $this->createAccount();

    expect(fn () => Domain::addDomain($cuenta, $nombre))->toThrow(DomainException::class);
})->with(['tuapp.test', 'cualquiera.tuapp.test']);

test('un dominio ya registrado por otra cuenta se rechaza', function () {
    $primera = $this->createAccount();
    $segunda = $this->createAccount();

    Domain::addDomain($primera, 'app.micliente.com');

    expect(fn () => Domain::addDomain($segunda, 'app.micliente.com'))
        ->toThrow(DomainException::class);
});

test('el número de dominios por cuenta tiene tope', function () {
    config(['base-tenant.domains.custom.max_per_account' => 2]);

    $cuenta = $this->createAccount();

    Domain::addDomain($cuenta, 'uno.micliente.com');
    Domain::addDomain($cuenta, 'dos.micliente.com');

    expect(fn () => Domain::addDomain($cuenta, 'tres.micliente.com'))
        ->toThrow(DomainException::class);
});

// ---------------------------------------------------------------------
// Verificación
// ---------------------------------------------------------------------

test('con el registro TXT publicado el dominio se verifica', function () {
    $cuenta = $this->createAccount();
    $dominio = Domain::addDomain($cuenta, 'app.micliente.com');

    dnsFalso(['_base-tenant-verify.app.micliente.com' => [$dominio->verification_token]]);

    expect(Domain::verify($dominio))->toBeTrue()
        ->and($dominio->fresh()->status)->toBe(AccountDomain::STATUS_VERIFIED)
        ->and($dominio->fresh()->is_primary)->toBeTrue();
});

test('los proveedores que entrecomillan el valor TXT se entienden igual', function () {
    $cuenta = $this->createAccount();
    $dominio = Domain::addDomain($cuenta, 'app.micliente.com');

    dnsFalso(['_base-tenant-verify.app.micliente.com' => ['"'.$dominio->verification_token.'"']]);

    expect(Domain::verify($dominio))->toBeTrue();
});

test('sin el registro el dominio queda sin verificar y con el motivo', function () {
    $cuenta = $this->createAccount();
    $dominio = Domain::addDomain($cuenta, 'app.micliente.com');

    dnsFalso();

    expect(Domain::verify($dominio))->toBeFalse()
        ->and($dominio->fresh()->status)->toBe(AccountDomain::STATUS_FAILED)
        ->and($dominio->fresh()->last_error)->not->toBeNull();
});

test('un token que no es el suyo no verifica', function () {
    $cuenta = $this->createAccount();
    $dominio = Domain::addDomain($cuenta, 'app.micliente.com');

    dnsFalso(['_base-tenant-verify.app.micliente.com' => ['base-tenant-verify=otracosa']]);

    expect(Domain::verify($dominio))->toBeFalse();
});

/**
 * El DNS falla de forma transitoria. Sacar de servicio el dominio de
 * producción de un cliente por una respuesta mala sería peor que el problema
 * que resuelve.
 */
test('un dominio ya verificado no se degrada por un fallo puntual', function () {
    $cuenta = $this->createAccount();
    $dominio = AccountDomain::factory()->verified()->create([
        'account_id' => $cuenta->getKey(),
        'hostname' => 'app.micliente.com',
    ]);

    dnsFalso();

    Domain::verify($dominio);

    expect($dominio->fresh()->status)->toBe(AccountDomain::STATUS_VERIFIED);
});

// ---------------------------------------------------------------------
// La guarda
// ---------------------------------------------------------------------

/**
 * LA prueba de esta capacidad.
 *
 * Un dominio sin verificar es un nombre que alguien escribió en un formulario,
 * no un nombre que posea. Si el resolutor lo aceptara, cualquiera que pueda
 * apuntar DNS a este producto sería servido como la cuenta que lo reclamó.
 *
 * Quitar el `verified()` de `DomainTenantResolver::fromCustomDomain()` tiene
 * que poner este test en rojo. Si pasa con y sin la guarda, no es cobertura.
 */
test('un dominio sin verificar NO resuelve la cuenta', function () {
    $cuenta = $this->createAccount();

    AccountDomain::factory()->create([
        'account_id' => $cuenta->getKey(),
        'hostname' => 'robado.micliente.com',
        'status' => AccountDomain::STATUS_PENDING,
    ]);

    $resuelta = (new DomainTenantResolver)->resolve(
        Request::create('https://robado.micliente.com/dashboard')
    );

    expect($resuelta)->toBeNull();
});

test('un dominio verificado sí resuelve la cuenta', function () {
    $cuenta = $this->createAccount();

    AccountDomain::factory()->verified()->create([
        'account_id' => $cuenta->getKey(),
        'hostname' => 'app.micliente.com',
    ]);

    $resuelta = (new DomainTenantResolver)->resolve(
        Request::create('https://app.micliente.com/dashboard')
    );

    expect($resuelta?->getKey())->toBe($cuenta->getKey());
});

test('un dominio en estado fallido tampoco resuelve', function () {
    $cuenta = $this->createAccount();

    AccountDomain::factory()->failed()->create([
        'account_id' => $cuenta->getKey(),
        'hostname' => 'caido.micliente.com',
    ]);

    expect((new DomainTenantResolver)->resolve(
        Request::create('https://caido.micliente.com/')
    ))->toBeNull();
});

// ---------------------------------------------------------------------
// El host que sirve la cuenta
// ---------------------------------------------------------------------

test('el host es el dominio principal si lo hay, y el subdominio si no', function () {
    $cuenta = $this->createAccount();

    expect(Domain::hostFor($cuenta))->toBe('tuapp.test');

    Domain::claimSubdomain($cuenta, 'acme');
    expect(Domain::hostFor($cuenta->fresh()))->toBe('acme.tuapp.test');

    AccountDomain::factory()->primary()->create([
        'account_id' => $cuenta->getKey(),
        'hostname' => 'app.micliente.com',
    ]);

    expect(Domain::hostFor($cuenta->fresh()))->toBe('app.micliente.com')
        ->and(Domain::urlFor($cuenta->fresh()))->toBe('https://app.micliente.com');
});

// ---------------------------------------------------------------------
// La pantalla
// ---------------------------------------------------------------------

test('la pantalla exige permiso de lectura', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $rol = $this->createRole($cuenta, 'sin-dominios');
    $usuario = $this->createUser($cuenta, 'sin-dominios');

    $this->actingAsTenant($usuario, $cuenta);

    Livewire::test(DomainManagerComponent::class)->assertForbidden();
});

test('con permiso de lectura pero no de escritura no se puede guardar', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $this->createRole($cuenta, 'solo-lectura', ['domains.view']);
    $usuario = $this->createUser($cuenta, 'solo-lectura');

    $this->actingAsTenant($usuario, $cuenta);

    Livewire::test(DomainManagerComponent::class)
        ->set('subdomain', 'acme')
        ->call('saveSubdomain')
        ->assertForbidden();
});

test('un administrador de la cuenta reclama su subdominio desde la pantalla', function () {
    $this->syncPermissions();

    $cuenta = $this->createAccount();
    $this->createRole($cuenta, 'admin-dominios', ['domains.view', 'domains.update']);
    $usuario = $this->createUser($cuenta, 'admin-dominios');

    $this->actingAsTenant($usuario, $cuenta);

    Livewire::test(DomainManagerComponent::class)
        ->set('subdomain', 'acme')
        ->call('saveSubdomain')
        ->assertHasNoErrors();

    expect($cuenta->fresh()->subdomain)->toBe('acme');
});

/**
 * `AccountDomain` no lleva scope global a propósito -- el resolutor lo lee
 * antes de que haya tenant --, así que el filtrado por cuenta lo hace la
 * pantalla a mano. Esta es la prueba de que lo hace.
 */
test('una cuenta no puede tocar el dominio de otra desde la pantalla', function () {
    $this->syncPermissions();

    $mia = $this->createAccount();
    $ajena = $this->createAccount();

    $this->createRole($mia, 'admin-dominios', ['domains.view', 'domains.update']);
    $usuario = $this->createUser($mia, 'admin-dominios');

    $delOtro = AccountDomain::factory()->verified()->create([
        'account_id' => $ajena->getKey(),
        'hostname' => 'ajeno.micliente.com',
    ]);

    $this->actingAsTenant($usuario, $mia);

    // `findOrFail` sobre la consulta ya acotada por cuenta: la fila existe,
    // pero no dentro del alcance de quien pregunta. Sobre HTTP esto es un 404.
    expect(fn () => Livewire::test(DomainManagerComponent::class)
        ->call('removeDomain', $delOtro->id))
        ->toThrow(ModelNotFoundException::class);

    expect(AccountDomain::find($delOtro->id))->not->toBeNull();
});

// ---------------------------------------------------------------------
// El comando
// ---------------------------------------------------------------------

test('el comando de verificación repasa los dominios pendientes', function () {
    $cuenta = $this->createAccount();
    $dominio = Domain::addDomain($cuenta, 'app.micliente.com');

    dnsFalso(['_base-tenant-verify.app.micliente.com' => [$dominio->verification_token]]);

    $this->artisan('k2labs-base:verify-domains')->assertSuccessful();

    expect($dominio->fresh()->status)->toBe(AccountDomain::STATUS_VERIFIED);
});

test('el comando falla si el módulo está apagado', function () {
    config(['base-tenant.domains.enabled' => false]);

    $this->artisan('k2labs-base:verify-domains')
        ->assertFailed();
})->throws(ModuleDisabledException::class);
