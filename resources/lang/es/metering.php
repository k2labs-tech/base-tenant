<?php

declare(strict_types=1);

return [

    'title' => 'Consumo',
    'description' => 'Lo que esta cuenta ha consumido de su plan.',

    'limit_reached' => 'Has alcanzado el límite de :metric que incluye tu plan.',
    'limit_reached_title' => 'Límite del plan alcanzado',
    'limit_reached_body' => 'Esta cuenta ha usado :current de los :limit que incluye su plan. Ampliar el plan sube el límite al momento.',
    'upgrade' => 'Ver planes',
    'go_back' => 'Volver',

    'unlimited' => 'Sin límite',
    'scope' => 'Ámbito',
    'search_placeholder' => 'Buscar por nombre o clave...',
    'of_limit' => 'de :limit',
    'count_summary' => '{0} Ninguna métrica|{1} 1 métrica|[2,*] :count métricas',
    'at_limit_summary' => '{1} 1 métrica ha llegado a su límite|[2,*] :count métricas han llegado a su límite',
    'approaching_summary' => '{1} 1 métrica está cerca de su límite|[2,*] :count métricas están cerca de su límite',
    'no_limit' => 'Sin tope',
    'used_of' => ':used de :limit',
    'remaining' => 'Quedan :count',

    'column' => [
        'metric' => 'Métrica',
        'usage' => 'Consumo',
        'limit' => 'Incluido en el plan',
        'remaining' => 'Disponible',
        'period' => 'Periodo',
    ],

    'period' => [
        'none' => 'Total',
        'day' => 'Hoy',
        'month' => 'Este mes',
        'year' => 'Este año',
    ],

    'summary' => [
        'metrics' => 'Métricas',
        'at_limit' => 'En el límite',
        'approaching' => 'Cerca del límite',
    ],

    'empty' => 'Todavía no se mide nada',
    'empty_hint' => 'Declara las métricas de este producto en config/base-tenant.php y aparecerán aquí.',

    'filter' => [
        'all' => 'Todas',
        'capped' => 'Con tope del plan',
        'uncapped' => 'Solo medidas',
    ],

    'notification' => [
        'approaching_subject' => 'Has usado el :percentage % de tu límite de :metric',
        'approaching_line' => 'Esta cuenta ha usado :value de los :limit de :metric que incluye su plan (:percentage %).',
        'reached_subject' => 'Has alcanzado tu límite de :metric',
        'reached_line' => 'Esta cuenta ha usado los :limit de :metric que incluye su plan. A partir de aquí se rechazará lo que exceda hasta que cambie el plan o se reinicie el periodo.',
        'action' => 'Ver consumo',
        'database_title' => 'Límite de :metric',
        'database_message' => ':value de :limit usado (:percentage %).',
    ],

    /*
    | Nombres de métrica que se ven en la interfaz. Una métrica sin entrada
    | aquí cae en su clave, que se lee lo bastante bien como para no bloquear.
    */
    'metrics' => [
        'storage.bytes' => 'Almacenamiento',
    ],

];
