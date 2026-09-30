<?php

declare(strict_types=1);

use Base\Tenant\Models\User;
use Base\Tenant\Support\Home;
use Base\Tenant\Support\RouteGroup;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

/**
 * Los ficheros de rutas se leen al arrancar la aplicación, antes de que el
 * test pueda tocar la configuración. Esto aplica la configuración y vuelve a
 * leerlos sobre una colección sin las rutas del paquete, que es lo que vería
 * una aplicación arrancada con esa configuración.
 *
 * @param  array<string, mixed>  $config
 */
function recargarRutasDelPaquete(array $config = []): void
{
    config($config);

    $router = app('router');
    $ajenas = new RouteCollection;

    foreach ($router->getRoutes() as $route) {
        if (! str_starts_with((string) $route->getName(), 'base-tenant.')) {
            $ajenas->add($route);
        }
    }

    $router->setRoutes($ajenas);

    foreach (['web', 'auth', 'subscriptions'] as $fichero) {
        require dirname(__DIR__, 2)."/routes/{$fichero}.php";
    }

    $router->getRoutes()->refreshNameLookups();
}

// ------------------------------------------------------ Interruptores por grupo

test('sin configurar nada se registran los mismos grupos que en 3.0', function () {
    recargarRutasDelPaquete(['base-tenant.subscription.enabled' => true]);

    expect(Route::has('base-tenant.login'))->toBeTrue()
        ->and(Route::has('base-tenant.dashboard'))->toBeTrue()
        ->and(Route::has('base-tenant.checkout'))->toBeTrue()
        ->and(Route::has('login'))->toBeFalse()
        ->and(Route::has('verification.notice'))->toBeFalse();
});

test('routes.enabled a false sigue apagándolo todo', function () {
    recargarRutasDelPaquete([
        'base-tenant.routes.enabled' => false,
        'base-tenant.subscription.enabled' => true,
    ]);

    expect(Route::has('base-tenant.login'))->toBeFalse()
        ->and(Route::has('base-tenant.dashboard'))->toBeFalse()
        ->and(Route::has('base-tenant.checkout'))->toBeFalse()
        ->and(Route::has('base-tenant.webhooks.suppressions'))->toBeFalse();
});

test('una aplicación puede quedarse sólo con el acceso', function () {
    recargarRutasDelPaquete([
        'base-tenant.routes.enabled' => false,
        'base-tenant.routes.auth.enabled' => true,
        'base-tenant.subscription.enabled' => true,
    ]);

    expect(Route::has('base-tenant.login'))->toBeTrue()
        ->and(Route::has('base-tenant.register'))->toBeTrue()
        ->and(Route::has('base-tenant.two-factor.challenge'))->toBeTrue()
        ->and(Route::has('base-tenant.dashboard'))->toBeFalse()
        ->and(Route::has('base-tenant.home'))->toBeFalse()
        ->and(Route::has('base-tenant.users.index'))->toBeFalse()
        ->and(Route::has('base-tenant.checkout'))->toBeFalse();
});

test('apagar la aplicación deja el acceso y los webhooks', function () {
    recargarRutasDelPaquete(['base-tenant.routes.app.enabled' => false]);

    expect(Route::has('base-tenant.login'))->toBeTrue()
        ->and(Route::has('base-tenant.dashboard'))->toBeFalse()
        ->and(RouteGroup::enabled(RouteGroup::WEBHOOKS))->toBeTrue();
});

test('el checkout tiene su propio interruptor además de subscription.enabled', function () {
    recargarRutasDelPaquete([
        'base-tenant.subscription.enabled' => true,
        'base-tenant.routes.subscriptions.enabled' => false,
    ]);

    expect(Route::has('base-tenant.checkout'))->toBeFalse()
        ->and(Route::has('base-tenant.dashboard'))->toBeTrue();
});

test('un interruptor sin valor hereda el general', function () {
    config([
        'base-tenant.routes.enabled' => false,
        'base-tenant.routes.auth.enabled' => null,
    ]);

    expect(RouteGroup::enabled(RouteGroup::AUTH))->toBeFalse();

    config(['base-tenant.routes.enabled' => true]);

    expect(RouteGroup::enabled(RouteGroup::AUTH))->toBeTrue();

    // `BASE_TENANT_ROUTES_AUTH_ENABLED=` en el .env llega como cadena vacía.
    config(['base-tenant.routes.auth.enabled' => '']);

    expect(RouteGroup::enabled(RouteGroup::AUTH))->toBeTrue();

    config(['base-tenant.routes.auth.enabled' => false]);

    expect(RouteGroup::enabled(RouteGroup::AUTH))->toBeFalse();
});

// ------------------------------------------------------ Nombres de Laravel

test('los nombres de Laravel apuntan a las pantallas del paquete', function () {
    recargarRutasDelPaquete(['base-tenant.routes.auth.laravel_names' => true]);

    expect(route('login'))->toBe(route('base-tenant.login'))
        ->and(route('register'))->toBe(route('base-tenant.register'))
        ->and(route('logout'))->toBe(route('base-tenant.logout'))
        ->and(route('password.request'))->toBe(route('base-tenant.password.request'))
        ->and(route('password.reset', ['token' => 'abc']))->toBe(route('base-tenant.password.reset', ['token' => 'abc']))
        ->and(route('password.confirm'))->toBe(route('base-tenant.password.confirm'))
        ->and(route('verification.notice'))->toBe(route('base-tenant.verification.notice'))
        ->and(route('verification.verify', ['id' => 1, 'hash' => 'h']))->toBe(route('base-tenant.verification.verify', ['id' => 1, 'hash' => 'h']))
        ->and(route('two-factor.login'))->toBe(route('base-tenant.two-factor.challenge'));
});

test('el alias no le quita la petición a la pantalla del paquete', function () {
    recargarRutasDelPaquete(['base-tenant.routes.auth.laravel_names' => true]);

    $get = app('router')->getRoutes()->match(Request::create('/login', 'GET'));
    $post = app('router')->getRoutes()->match(Request::create('/logout', 'POST'));

    expect($get->getName())->toBe('base-tenant.login')
        ->and($post->getName())->toBe('base-tenant.logout');

    $this->call('OPTIONS', '/login')->assertRedirect(route('base-tenant.login'));
});

test('con los alias, los middleware de Laravel llevan a las pantallas del paquete', function () {
    recargarRutasDelPaquete(['base-tenant.routes.auth.laravel_names' => true]);

    Route::middleware(['web', 'auth', 'verified'])->get('solo-verificados', fn () => 'dentro');
    Route::middleware(['web', 'auth', 'password.confirm'])->get('solo-confirmados', fn () => 'dentro');
    app('router')->getRoutes()->refreshNameLookups();

    $this->get('solo-verificados')->assertRedirect(route('base-tenant.login'));

    // `verified` sólo actúa sobre un modelo que implementa MustVerifyEmail,
    // que es lo que hace el de una aplicación que lo exige.
    $sinVerificar = (new class extends User implements MustVerifyEmail
    {
        protected $table = 'users';
    })->newFromBuilder($this->createUser(attributes: ['email_verified_at' => null])->getAttributes());

    $this->actingAs($sinVerificar)
        ->get('solo-verificados')
        ->assertRedirect(route('base-tenant.verification.notice'));

    $this->actingAs($this->createUser())
        ->get('solo-confirmados')
        ->assertRedirect(route('base-tenant.password.confirm'));
});

// ------------------------------------------------------ Destino tras entrar

test('home_url admite un nombre de ruta, una ruta relativa o una URL', function () {
    expect(Home::url())->toBe(route('base-tenant.dashboard'))
        ->and(Home::url(absolute: false))->toBe('/dashboard');

    config(['base-tenant.home_url' => 'base-tenant.profile']);
    expect(Home::url())->toBe(route('base-tenant.profile'));

    config(['base-tenant.home_url' => '/panel']);
    expect(Home::url())->toBe(url('/panel'))
        ->and(Home::url(absolute: false))->toBe('/panel');

    config(['base-tenant.home_url' => 'https://app.example.com/inicio']);
    expect(Home::url())->toBe('https://app.example.com/inicio');
});

test('un home_url que no existe como ruta cae en la raíz en lugar de romper el acceso', function () {
    recargarRutasDelPaquete(['base-tenant.routes.app.enabled' => false]);

    expect(Home::url())->toBe(url('/'))
        ->and(Home::url(absolute: false))->toBe('/')
        ->and(Home::routeOrHome('base-tenant.profile'))->toBe(url('/'));
});

test('la raíz no se redirige a sí misma cuando home_url es la raíz', function () {
    config(['base-tenant.home_url' => '/']);

    $this->actingAs($this->createUser())
        ->get(route('base-tenant.home'))
        ->assertRedirect(route('base-tenant.dashboard'));
});
