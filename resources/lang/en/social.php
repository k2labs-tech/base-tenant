<?php

declare(strict_types=1);

return [

    'continue_with' => 'or continue with',
    'sign_up_with' => 'or sign up with',

    'title' => 'Connected accounts',
    'description' => 'Sign in with a provider instead of a password.',

    'connect' => 'Connect',
    'disconnect' => 'Disconnect',
    'connected' => 'Connected',
    'not_connected' => 'Not connected',

    'linked' => ':provider connected',
    'unlinked' => ':provider disconnected',

    'none_configured' => 'No provider is configured for this installation.',

    'errors' => [
        'cancelled' => 'The sign-in was not completed.',
        'no_email' => ':provider did not share an email address, so there is nothing to match an account against.',
        'email_taken' => 'There is already an account for :email. Sign in with your password first, then connect :provider from your profile.',
        'domain_not_allowed' => 'Accounts cannot be created for the :domain domain.',
        'already_linked' => 'That :provider account is already connected to another user.',
        'would_lock_out' => 'Disconnecting :provider would leave no way to sign in. Set a password first.',
        'not_configured' => ':provider is not configured for this installation.',
    ],

];
