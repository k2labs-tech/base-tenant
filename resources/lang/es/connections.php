<?php

declare(strict_types=1);

return [

    'title' => 'Conexiones',
    'description' => 'Los servicios externos a los que está conectada esta cuenta.',

    'statuses' => [
        'healthy' => 'Funciona',
        'failing' => 'No funciona',
        'unknown' => 'Sin comprobar',
    ],

    'column' => [
        'provider' => 'Servicio',
        'status' => 'Estado',
        'checked' => 'Última comprobación',
    ],

    'never_checked' => 'Nunca',
    'check_now' => 'Comprobar ahora',
    'checked' => 'Conexión comprobada',
    'disconnect' => 'Desconectar',
    'disconnected' => 'Conexión eliminada',

    'search_placeholder' => 'Buscar por servicio o etiqueta...',

    'summary' => [
        'failing' => '{1} 1 conexión no funciona|[2,*] :count conexiones no funcionan',
    ],

    'empty' => 'Todavía no hay conexiones',
    'empty_hint' => 'Conecta un servicio externo para traer sus datos a esta cuenta.',

    'notification' => [
        'subject' => 'Tu conexión con :provider ha dejado de funcionar',
        'line' => 'La conexión con :provider etiquetada :label ya no la acepta el proveedor. Todo lo que dependa de ella se ha parado.',
        'action' => 'Revisar conexiones',
    ],

];
