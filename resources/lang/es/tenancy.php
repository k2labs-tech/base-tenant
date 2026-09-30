<?php

declare(strict_types=1);

return [

    'audit' => [
        'passed' => 'Auditoría de tenancy superada: :scanned modelo(s) revisados, :scoped acotados por cuenta.',
        'failed' => 'Auditoría de tenancy fallida: :count modelo(s) guardan datos de cuenta fuera del aislamiento.',
        'missing_path' => 'El directorio [:path] no existe; se omite.',
        'missing_trait' => 'tiene columna account_id pero no usa BelongsToAccount',
        'missing_column' => 'usa BelongsToAccount pero la tabla no tiene la columna :column',
        'model' => 'Modelo',
        'table' => 'Tabla',
        'finding' => 'Hallazgo',
    ],

];
