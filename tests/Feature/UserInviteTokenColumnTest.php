<?php

declare(strict_types=1);

use Base\Tenant\Models\UserInvite;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('la columna token declara los 64 caracteres que genera el servicio', function () {
    $columna = collect(Schema::getColumns('user_invites'))
        ->firstWhere('name', 'token');

    expect($columna)->not->toBeNull()
        ->and($columna['type'])->toContain('64');
})->skip(
    fn (): bool => DB::connection()->getDriverName() === 'sqlite',
    'SQLite no guarda la longitud de las columnas: correr con DB_CONNECTION=pgsql.'
);

test('un token de 64 caracteres se guarda entero', function () {
    $token = Str::random(64);

    $invitacion = UserInvite::create([
        'email' => 'ana@ejemplo.test',
        'token' => $token,
        'expires_at' => now()->addWeek(),
    ]);

    expect($invitacion->fresh()->token)->toBe($token);
});

test('el token sigue siendo único después de ensanchar la columna', function () {
    $token = Str::random(64);

    UserInvite::create([
        'email' => 'ana@ejemplo.test',
        'token' => $token,
        'expires_at' => now()->addWeek(),
    ]);

    expect(fn () => UserInvite::create([
        'email' => 'bruno@ejemplo.test',
        'token' => $token,
        'expires_at' => now()->addWeek(),
    ]))->toThrow(QueryException::class);
});
