<?php

declare(strict_types=1);

return [

    'title' => 'Security',
    'description' => 'The rules that apply to everybody in this account.',
    'saved' => 'Security policy saved.',

    'two_factor' => [
        'title' => 'Two-factor authentication',
        'description' => 'Require a second factor from every member of the account.',
        'require' => 'Require two-factor authentication',
        'grace_hours' => 'Grace period (hours)',
        'grace_hint' => 'How long members have to set it up before they are made to.',
        'affected' => '{1} :count member has no second factor yet and will be asked to set one up.|[2,*] :count members have no second factor yet and will be asked to set one up.',
    ],

    'email_domains' => [
        'title' => 'Allowed email domains',
        'description' => 'Restrict who can be invited into this account.',
        'label' => 'Domains',
        'hint' => 'One per line. Leave empty to allow any address.',
    ],

    'ip' => [
        'title' => 'IP allowlist',
        'description' => 'Restrict where this account can be reached from.',
        'mode' => 'Mode',
        'modes' => [
            'off' => 'Off',
            'off_hint' => 'Any address is allowed.',
            'warn' => 'Warn only',
            'warn_hint' => 'Record what would be blocked, block nothing. Start here.',
            'enforce' => 'Enforce',
            'enforce_hint' => 'Refuse requests from outside the list.',
        ],
        'label' => 'Allowed addresses',
        'hint' => 'One per line. Single addresses or CIDR ranges.',
        'your_address' => 'This request came from :ip.',
        'add_mine' => 'Add it',
    ],

    'session' => [
        'title' => 'Sessions',
        'description' => 'How long an idle session stays open.',
        'timeout' => 'Idle timeout (minutes)',
        'timeout_hint' => '0 keeps the installation default.',
    ],

    'two_factor_required' => 'This account requires two-factor authentication. Set it up to continue.',
    'ip_blocked' => 'This account cannot be reached from :ip.',
    'ip_warn_logged' => 'A request from :ip would have been blocked by the IP allowlist.',
    'session_expired' => 'Your session ended after a period of inactivity.',
    'email_domain_not_allowed' => 'This account only accepts addresses at: :domains.',

    'errors' => [
        'invalid_entries' => 'Not valid addresses or ranges: :entries',
        'would_lock_you_out' => 'Enforcing this list would lock you out: your address (:ip) is not in it. Add it first.',
    ],

];
