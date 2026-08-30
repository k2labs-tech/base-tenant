<?php

declare(strict_types=1);

namespace Base\Tenant\Tests\Fixtures;

use Base\Tenant\Models\User as BaseUser;

/**
 * El modelo de usuario que pone la aplicación anfitriona.
 *
 * Existe para una sola cosa: en la suite del paquete el modelo configurado es
 * el del paquete, así que un componente que consulte su propia clase en lugar
 * de la configurada funciona en los tests y falla en cualquier instalación
 * real. Las relaciones polimórficas —los roles de spatie, entre ellas— guardan
 * el nombre de la clase, y ahí es donde se nota.
 */
class User extends BaseUser
{
    protected $table = 'users';
}
