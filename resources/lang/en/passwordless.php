<?php

declare(strict_types=1);

return [

    'title' => 'Sign in without a password',
    'subtitle' => 'We will email you a link that signs you in.',
    'send' => 'Email me a link',
    'use_password' => 'Sign in with a password instead',
    'back_to_login' => 'Back to sign in',
    'link_option' => 'Email me a sign-in link',
    'or' => 'or',

    'sent_title' => 'Check your inbox',
    'sent_body' => 'If that address belongs to an account, a sign-in link is on its way. It works once and expires in :minutes minutes.',

    'confirm_title' => 'Almost there',
    'confirm_body' => 'Press the button to finish signing in. The link works once.',
    'confirm_action' => 'Sign me in',

    'link_invalid_title' => 'This link no longer works',
    'link_invalid' => 'That link has already been used or has expired. Ask for a new one.',
    'request_another' => 'Email me a new link',
    'throttled' => 'Too many links requested. Try again in :seconds seconds.',

    'mail' => [
        'subject' => 'Your sign-in link for :app',
        'greeting' => 'Hello',
        'line' => 'Use the button below to sign in. It works once.',
        'action' => 'Sign in',
        'expiry' => 'The link expires in :minutes minutes.',
        'ignore' => 'If you did not ask for this, you can ignore this email — nothing has changed.',
    ],

    'console' => [
        'pruned' => 'Deleted :count sign-in links older than :days days.',
    ],

];
