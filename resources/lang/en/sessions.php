<?php

declare(strict_types=1);

return [

    'title' => 'Active sessions',
    'description' => 'Where you are signed in. End any session you do not recognise.',
    'description_admin' => 'Where this person is signed in. Ending a session signs them out of that device.',

    'this_device' => 'This device',
    'unknown_browser' => 'Unknown browser',
    'last_active' => 'last active :when',

    'revoke' => 'End session',
    'revoke_title' => 'End this session',
    'revoke_confirm' => 'End the session on :device from :ip?',
    'revoke_others' => 'End all other sessions',
    'revoke_others_confirm' => 'End every session except the one you are using right now?',
    'revoke_all' => 'End all sessions',
    'revoke_all_confirm' => 'End every session this person has open, including the one they may be using?',
    'revoke_delay_notice' => 'A session ends on its next request, which is usually immediate but not guaranteed to be.',

    'revoked' => 'Session ended.',
    'revoked_many' => '{0} No other sessions were open.|{1} :count session ended.|[2,*] :count sessions ended.',
    'revoked_notice' => 'This session was ended from another device.',

    'empty_title' => 'No other sessions',
    'empty_description' => 'This is the only device signed in right now.',

    'console' => [
        'pruned' => 'Deleted :count session records older than :days days.',
    ],

];
