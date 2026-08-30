<?php

declare(strict_types=1);

return [

    'title' => 'Importaciones y exportaciones',
    'description' => 'Trae datos desde un fichero, o sácalos a uno.',

    'error_column' => 'Motivo del rechazo',
    'line_column' => 'Línea',

    'types' => [
        'import' => 'Importación',
        'export' => 'Exportación',
    ],

    'statuses' => [
        'pending' => 'En cola',
        'processing' => 'En marcha',
        'completed' => 'Terminada',
        'failed' => 'Fallida',
    ],

    'column' => [
        'name' => 'Fichero',
        'type' => 'Tipo',
        'status' => 'Estado',
        'rows' => 'Filas',
        'started' => 'Iniciada',
    ],

    'rows_summary' => ':processed de :total',
    'failed_rows' => ':count rechazadas',
    'download' => 'Descargar',
    'download_errors' => 'Descargar las filas rechazadas',

    'search_placeholder' => 'Buscar por nombre de fichero...',

    'summary' => [
        'running' => 'En marcha',
        'failed' => 'Fallidas',
    ],

    'empty' => 'Todavía no se ha importado ni exportado nada',
    'empty_hint' => 'Aquí aparecen las importaciones y exportaciones lanzadas desde cualquier parte de la aplicación.',

    'running_summary' => '{1} 1 traspaso en marcha|[2,*] :count traspasos en marcha',
    'failed_summary' => '{1} 1 traspaso fallido|[2,*] :count traspasos fallidos',

    'partial' => 'Se han rechazado algunas filas. Descárgalas, corrígelas y vuelve a subir el fichero.',

];
