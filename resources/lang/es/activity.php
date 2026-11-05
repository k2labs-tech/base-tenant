<?php

return [

    'title' => 'Registro de actividad',

    'search_placeholder' => 'Buscar actividad...',
    'all_actions' => 'Todas las acciones',
    'all_users' => 'Todos los usuarios',
    'date_range' => 'Rango de fechas',
    'date_from' => 'Desde',
    'date_until' => 'Hasta',

    'date' => 'Fecha',
    'user' => 'Usuario',
    'action' => 'Acción',
    'subject' => 'Elemento',
    'description' => 'Descripción',

    'system' => 'Sistema',
    'no_activities_found' => 'No se ha encontrado actividad.',
    'description_line' => 'Todo lo que ha pasado en la cuenta, quién lo hizo y sobre qué.',
    'empty_description' => 'Las entradas aparecen aquí a medida que se trabaja en la cuenta.',

    // Recorded actions; an app adds its own here (ActivityLog::actionLabel()).
    'actions' => [
        'created' => 'Creado',
        'updated' => 'Actualizado',
        'deleted' => 'Eliminado',
        'account' => [
            'entered' => 'Entrada en la cuenta',
        ],
    ],

    // What an entry is about, by class basename; an app names its own models here.
    'subjects' => [
        'Account' => 'Cuenta',
        'User' => 'Usuario',
        'File' => 'Fichero',
    ],
];
