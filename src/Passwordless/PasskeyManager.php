<?php

declare(strict_types=1);

namespace Base\Tenant\Passwordless;

use Base\Tenant\Models\Passkey;
use Base\Tenant\Models\User;
use Base\Tenant\Services\ActivityLogService;
use Base\Tenant\Support\Module;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Passkeys: signing in with what the device holds rather than what the user
 * remembers.
 *
 * TOTP is better than nothing but it is phishable -- a code typed into a fake
 * page is a code the attacker now has. A passkey is bound to the origin by the
 * browser and simply cannot be handed to a site that is not this one.
 *
 * The cryptography is `web-auth/webauthn-lib`. The flow is ours, so that it
 * lands in the same place as every other way of signing in and obeys the same
 * account rules.
 */
class PasskeyManager
{
    public function enabled(): bool
    {
        return Module::enabled(Module::PASSWORDLESS)
            && (bool) config('base-tenant.passwordless.passkeys.enabled', true);
    }

    // -----------------------------------------------------------------
    // Ceremony options
    // -----------------------------------------------------------------

    /**
     * Options for registering a new passkey.
     *
     * `excludeCredentials` carries what the user already has, so an
     * authenticator that is already registered says so instead of silently
     * creating a second credential the user cannot tell apart from the first.
     */
    public function creationOptions(User $user): PublicKeyCredentialCreationOptions
    {
        $existing = Passkey::query()
            ->where('user_id', $user->getKey())
            ->pluck('credential_id')
            ->map(fn (string $id): PublicKeyCredentialDescriptor => PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                Passkey::decodeId($id),
            ))
            ->all();

        return PublicKeyCredentialCreationOptions::create(
            rp: $this->relyingParty(),
            user: PublicKeyCredentialUserEntity::create(
                $user->email,
                (string) $user->getKey(),
                $user->name ?? $user->email,
            ),
            challenge: random_bytes(32),
            pubKeyCredParams: $this->algorithms(),
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            ),
            excludeCredentials: $existing,
            timeout: 60_000,
        );
    }

    /**
     * Options for signing in.
     *
     * With no user, the list of allowed credentials is left empty so the
     * browser can offer whatever it holds for this site -- which is the whole
     * point of a passkey, and also means the endpoint cannot be used to ask
     * whether a given address is a customer.
     */
    public function requestOptions(?User $user = null): PublicKeyCredentialRequestOptions
    {
        $allowed = [];

        if ($user !== null) {
            $allowed = Passkey::query()
                ->where('user_id', $user->getKey())
                ->pluck('credential_id')
                ->map(fn (string $id): PublicKeyCredentialDescriptor => PublicKeyCredentialDescriptor::create(
                    PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                    Passkey::decodeId($id),
                ))
                ->all();
        }

        return PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32),
            rpId: $this->relyingPartyId(),
            allowCredentials: $allowed,
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            timeout: 60_000,
        );
    }

    // -----------------------------------------------------------------
    // Registration
    // -----------------------------------------------------------------

    /**
     * Verify what the browser sent and store the credential.
     *
     * @throws Throwable when the response does not verify
     */
    public function register(
        User $user,
        string $credentialJson,
        PublicKeyCredentialCreationOptions $options,
        string $name,
        string $host,
    ): Passkey {
        $credential = $this->serializer()->deserialize(
            $credentialJson,
            PublicKeyCredential::class,
            'json'
        );

        $response = $credential->response;

        if (! $response instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('The credential is not a registration response.');
        }

        $record = $this->attestationValidator()->check($response, $options, $host);

        $passkey = Passkey::create([
            'user_id' => $user->getKey(),
            'credential_id' => Passkey::encodeId($record->publicKeyCredentialId),
            'name' => $name,
            'record' => json_decode($this->serializer()->serialize($record, 'json'), true),
        ]);

        ActivityLogService::log(
            subject: $user,
            action: 'auth.passkey_registered',
            newValues: ['name' => $name],
        );

        return $passkey;
    }

    // -----------------------------------------------------------------
    // Assertion
    // -----------------------------------------------------------------

    /**
     * Verify a sign-in and return the passkey it belongs to, or null.
     *
     * The counter and the origin are checked inside the library's ceremony;
     * what happens here is the lookup, the storage of the advanced counter and
     * the refusal when anything at all went wrong. `null` rather than an
     * exception, because every failure mode has the same answer for the user
     * and distinguishing them out loud would be a hint.
     */
    public function verify(
        string $credentialJson,
        PublicKeyCredentialRequestOptions $options,
        string $host,
    ): ?Passkey {
        try {
            $credential = $this->serializer()->deserialize(
                $credentialJson,
                PublicKeyCredential::class,
                'json'
            );

            $response = $credential->response;

            if (! $response instanceof AuthenticatorAssertionResponse) {
                return null;
            }

            $passkey = Passkey::query()
                ->where('credential_id', Passkey::encodeId($credential->rawId))
                ->first();

            if ($passkey === null) {
                return null;
            }

            $record = $this->recordFor($passkey);

            $updated = $this->assertionValidator()->check(
                $record,
                $response,
                $options,
                $host,
                $response->userHandle,
            );

            // The counter only ever goes up. The library refuses a replay, and
            // storing what it returned is what keeps that true next time.
            $passkey->forceFill([
                'record' => json_decode($this->serializer()->serialize($updated, 'json'), true),
                'last_used_at' => now(),
            ])->save();

            ActivityLogService::log(
                subject: $passkey->user,
                action: 'auth.passkey_used',
                newValues: ['name' => $passkey->name],
            );

            return $passkey;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The options, as the JSON the browser expects.
     *
     * Not `json_encode()`: the challenge and the user id are raw bytes, and
     * encoding them as a PHP string produces malformed UTF-8. The library's
     * own serializer knows they are base64url in the wire format.
     */
    public function optionsToJson(
        PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options,
    ): string {
        return $this->serializer()->serialize($options, 'json');
    }

    // -----------------------------------------------------------------
    // Wiring
    // -----------------------------------------------------------------

    public function relyingPartyId(): ?string
    {
        $configured = config('base-tenant.passwordless.passkeys.relying_party_id');

        if ($configured) {
            return $configured;
        }

        // Falls back to the application host. Never to a customer's custom
        // domain: a passkey is bound to the origin it was created on, and
        // moving the relying party per tenant would silently invalidate every
        // key the moment somebody changed their domain.
        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: null;
    }

    protected function relyingParty(): PublicKeyCredentialRpEntity
    {
        return PublicKeyCredentialRpEntity::create(
            config('base-tenant.passwordless.passkeys.relying_party_name') ?? config('app.name', 'Application'),
            $this->relyingPartyId(),
        );
    }

    protected function recordFor(Passkey $passkey): CredentialRecord
    {
        return $this->serializer()->deserialize(
            json_encode($passkey->record),
            CredentialRecord::class,
            'json'
        );
    }

    /**
     * @return array<int, PublicKeyCredentialParameters>
     */
    protected function algorithms(): array
    {
        // ES256 first because it is what every platform authenticator does;
        // RS256 for the older security keys that only speak it.
        return [
            PublicKeyCredentialParameters::create('public-key', -7),
            PublicKeyCredentialParameters::create('public-key', -257),
        ];
    }

    protected function serializer(): SerializerInterface
    {
        return once(fn (): SerializerInterface => (new WebauthnSerializerFactory(
            $this->attestationSupport()
        ))->create());
    }

    protected function attestationSupport(): AttestationStatementSupportManager
    {
        $manager = AttestationStatementSupportManager::create();

        // `none` only. Verifying an attestation chain means shipping and
        // updating the FIDO metadata service, and the answer it gives -- which
        // make of authenticator is this -- is not one this product acts on.
        $manager->add(NoneAttestationStatementSupport::create());

        return $manager;
    }

    protected function ceremonyFactory(): CeremonyStepManagerFactory
    {
        $factory = new CeremonyStepManagerFactory;
        $factory->setAttestationStatementSupportManager($this->attestationSupport());

        return $factory;
    }

    protected function attestationValidator(): AuthenticatorAttestationResponseValidator
    {
        return AuthenticatorAttestationResponseValidator::create(
            $this->ceremonyFactory()->creationCeremony()
        );
    }

    protected function assertionValidator(): AuthenticatorAssertionResponseValidator
    {
        return AuthenticatorAssertionResponseValidator::create(
            $this->ceremonyFactory()->requestCeremony()
        );
    }
}
