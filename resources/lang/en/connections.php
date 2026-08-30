<?php

declare(strict_types=1);

return [

    'title' => 'Connections',
    'description' => 'The external services this account is connected to.',

    'statuses' => [
        'healthy' => 'Working',
        'failing' => 'Not working',
        'unknown' => 'Not checked yet',
    ],

    'column' => [
        'provider' => 'Service',
        'status' => 'Status',
        'checked' => 'Last checked',
    ],

    'never_checked' => 'Never',
    'check_now' => 'Check now',
    'checked' => 'Connection checked',
    'disconnect' => 'Disconnect',
    'disconnected' => 'Connection removed',

    'search_placeholder' => 'Search by service or label...',

    'summary' => [
        'failing' => '{1} 1 connection is not working|[2,*] :count connections are not working',
    ],

    'empty' => 'No connections yet',
    'empty_hint' => 'Connect an external service to bring its data into this account.',

    'notification' => [
        'subject' => 'Your :provider connection stopped working',
        'line' => 'The :provider connection labelled :label is no longer accepted by the provider. Anything that depends on it has stopped.',
        'action' => 'Review connections',
    ],

];
