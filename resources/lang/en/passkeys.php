<?php

declare(strict_types=1);

return [

    'title' => 'Passkeys',
    'description' => 'Sign in with your fingerprint, face or a security key instead of a password.',
    'why' => 'A passkey is tied to this site and cannot be handed to a fake one, which is what makes it stronger than a code you type.',

    'add' => 'Add a passkey',
    'sign_in' => 'Sign in with a passkey',
    'name_placeholder' => 'MacBook, YubiKey, phone…',
    'name_label' => 'Name this passkey',
    'rename' => 'Rename',
    'remove' => 'Remove',
    'remove_title' => 'Remove passkey',
    'remove_confirm' => 'Remove ":name"? You will no longer be able to sign in with it.',

    'added' => 'Added :when',
    'last_used' => 'last used :when',
    'never_used' => 'never used',

    'empty_title' => 'No passkeys yet',
    'empty_description' => 'Add one and you can sign in without typing anything.',

    'registered' => 'Passkey added.',
    'renamed' => 'Passkey renamed.',
    'removed' => 'Passkey removed.',

    'unsupported' => 'This browser does not support passkeys.',
    'no_challenge' => 'That took too long. Try again.',
    'register_failed' => 'That passkey could not be registered.',
    'login_failed' => 'That passkey was not recognised.',
    'cancelled' => 'Cancelled.',

    'still_needs_totp' => 'This account also asks for your authenticator code after a passkey sign-in.',

];
