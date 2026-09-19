<?php

declare(strict_types=1);

namespace Base\Tenant\Facades;

use Base\Tenant\Models\Passkey as PasskeyModel;
use Base\Tenant\Models\User;
use Base\Tenant\Passwordless\PasskeyManager;
use Illuminate\Support\Facades\Facade;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * @method static bool enabled()
 * @method static string|null relyingPartyId()
 * @method static PublicKeyCredentialCreationOptions creationOptions(User $user)
 * @method static PublicKeyCredentialRequestOptions requestOptions(User|null $user = null)
 * @method static PasskeyModel register(User $user, string $credentialJson, PublicKeyCredentialCreationOptions $options, string $name, string $host)
 * @method static string optionsToJson(PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options)
 * @method static PasskeyModel|null verify(string $credentialJson, PublicKeyCredentialRequestOptions $options, string $host)
 *
 * @see PasskeyManager
 */
class Passkey extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PasskeyManager::class;
    }
}
