<?php

declare(strict_types=1);

namespace Base\Tenant\Http\Controllers\Auth;

use Base\Tenant\Facades\Passkey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;

/**
 * The two WebAuthn ceremonies, as JSON endpoints the browser talks to.
 *
 * The options are held in the session between the two halves of each ceremony.
 * They carry the challenge, and a challenge the server does not remember is a
 * challenge an attacker can choose.
 */
class PasskeyController
{
    protected const CREATE_KEY = 'base-tenant.passkey.creation';

    protected const REQUEST_KEY = 'base-tenant.passkey.request';

    /**
     * Ceremony options as JSON, through the library's serializer.
     */
    protected function options(
        PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options,
    ): JsonResponse {
        // The fifth argument, not the fourth: Illuminate's JsonResponse adds
        // `$options` where Symfony's has `$json`, so passing `true` in fourth
        // position silently encodes the JSON string a second time.
        return new JsonResponse(Passkey::optionsToJson($options), 200, [], 0, true);
    }

    public function registerOptions(Request $request): JsonResponse
    {
        abort_unless(Passkey::enabled(), 404);
        abort_unless(Auth::check(), 401);

        $options = Passkey::creationOptions(Auth::user());

        $request->session()->put(self::CREATE_KEY, serialize($options));

        return $this->options($options);
    }

    public function register(Request $request): JsonResponse
    {
        abort_unless(Passkey::enabled(), 404);
        abort_unless(Auth::check(), 401);

        $validated = $request->validate([
            'credential' => ['required', 'string'],
            'name' => ['required', 'string', 'max:60'],
        ]);

        $stored = $request->session()->pull(self::CREATE_KEY);

        if (! is_string($stored)) {
            return response()->json(['message' => __('base-tenant::passkeys.no_challenge')], 422);
        }

        $options = unserialize($stored, ['allowed_classes' => true]);

        if (! $options instanceof PublicKeyCredentialCreationOptions) {
            return response()->json(['message' => __('base-tenant::passkeys.no_challenge')], 422);
        }

        try {
            Passkey::register(
                Auth::user(),
                $validated['credential'],
                $options,
                $validated['name'],
                $request->getHost(),
            );
        } catch (Throwable) {
            return response()->json(['message' => __('base-tenant::passkeys.register_failed')], 422);
        }

        return response()->json(['message' => __('base-tenant::passkeys.registered')]);
    }

    public function loginOptions(Request $request): JsonResponse
    {
        abort_unless(Passkey::enabled(), 404);

        // No user is looked up and none is needed: the browser offers what it
        // holds for this origin. It also means this endpoint cannot be used to
        // ask whether an address belongs to a customer.
        $options = Passkey::requestOptions();

        $request->session()->put(self::REQUEST_KEY, serialize($options));

        return $this->options($options);
    }

    public function login(Request $request): JsonResponse
    {
        abort_unless(Passkey::enabled(), 404);

        $validated = $request->validate([
            'credential' => ['required', 'string'],
        ]);

        $stored = $request->session()->pull(self::REQUEST_KEY);

        if (! is_string($stored)) {
            return response()->json(['message' => __('base-tenant::passkeys.no_challenge')], 422);
        }

        $options = unserialize($stored, ['allowed_classes' => true]);

        if (! $options instanceof PublicKeyCredentialRequestOptions) {
            return response()->json(['message' => __('base-tenant::passkeys.no_challenge')], 422);
        }

        $passkey = Passkey::verify($validated['credential'], $options, $request->getHost());

        if ($passkey === null || $passkey->user === null) {
            return response()->json(['message' => __('base-tenant::passkeys.login_failed')], 422);
        }

        $user = $passkey->user;

        // Same rule as the magic link, and for the same reason: the account's
        // administrator asked for a second factor, and this flow is not the
        // place to decide their policy does not apply. A passkey with user
        // verification arguably is two factors already -- refining that is
        // noted in the docs and deliberately not guessed at here.
        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put([
                '2fa.user_id' => $user->getKey(),
                '2fa.remember' => false,
            ]);

            return response()->json(['redirect' => route('base-tenant.two-factor.challenge')]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'redirect' => route(config('base-tenant.home_url', 'base-tenant.dashboard')),
        ]);
    }
}
