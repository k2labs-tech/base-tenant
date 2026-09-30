<?php

declare(strict_types=1);

return [

    'audit' => [
        'passed' => 'Tenancy audit passed: :scanned model(s) scanned, :scoped scoped by account.',
        'failed' => 'Tenancy audit failed: :count model(s) hold account data outside the isolation boundary.',
        'missing_path' => 'Directory [:path] does not exist; skipped.',
        'missing_trait' => 'account_id column present, BelongsToAccount missing',
        'missing_column' => 'uses BelongsToAccount but the table has no :column column',
        'model' => 'Model',
        'table' => 'Table',
        'finding' => 'Finding',
    ],

];
