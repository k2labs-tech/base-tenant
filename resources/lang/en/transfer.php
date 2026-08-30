<?php

declare(strict_types=1);

return [

    'title' => 'Imports and exports',
    'description' => 'Bring data in from a file, or take it out as one.',

    'error_column' => 'Why it was rejected',
    'line_column' => 'Line',

    'types' => [
        'import' => 'Import',
        'export' => 'Export',
    ],

    'statuses' => [
        'pending' => 'Queued',
        'processing' => 'Running',
        'completed' => 'Finished',
        'failed' => 'Failed',
    ],

    'column' => [
        'name' => 'File',
        'type' => 'Type',
        'status' => 'Status',
        'rows' => 'Rows',
        'started' => 'Started',
    ],

    'rows_summary' => ':processed of :total',
    'failed_rows' => ':count rejected',
    'download' => 'Download',
    'download_errors' => 'Download rejected rows',

    'search_placeholder' => 'Search by file name...',

    'summary' => [
        'running' => 'Running',
        'failed' => 'Failed',
    ],

    'empty' => 'Nothing imported or exported yet',
    'empty_hint' => 'Imports and exports started from anywhere in the application appear here.',

    'running_summary' => '{1} 1 transfer is running|[2,*] :count transfers are running',
    'failed_summary' => '{1} 1 transfer failed|[2,*] :count transfers failed',

    'partial' => 'Some rows were rejected. Download them, correct them and upload the file again.',

];
