<?php

declare(strict_types=1);

return [

    /*
    | Nombres sueltos que ya usaba el selector de preferencias.
    */
    'english' => 'English',
    'spanish' => 'Spanish',

    'title' => 'Languages',
    'description' => 'Turn a language on or off without a deploy.',

    'search_placeholder' => 'Search by name or code...',

    'status' => 'Status',
    'statuses' => [
        'all' => 'All',
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',
    ],

    'default' => 'Default',
    'make_default' => 'Make default',
    'enable' => 'Enable',
    'disable' => 'Disable',

    'updated' => 'Language updated',
    'default_updated' => 'Default language updated',

    'column' => [
        'language' => 'Language',
        'status' => 'Status',
        'coverage' => 'Translated',
    ],

    'keys_translated' => ':translated of :total keys',
    'measured_against' => 'Measured against :locale',

    'count_summary' => '{0} No languages|{1} 1 language|[2,*] :count languages',
    'incomplete_summary' => '{1} 1 enabled language is incomplete and falls back for the rest|[2,*] :count enabled languages are incomplete and fall back for the rest',

    'empty' => 'No languages yet',
    'empty_hint' => 'Seed the languages table, or add a row for each locale this product ships.',

];
