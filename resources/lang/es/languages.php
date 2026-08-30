<?php

declare(strict_types=1);

return [

    /*
    | Nombres sueltos que ya usaba el selector de preferencias.
    */
    'english' => 'Inglés',
    'spanish' => 'Español',

    'title' => 'Idiomas',
    'description' => 'Activa o desactiva un idioma sin desplegar.',

    'search_placeholder' => 'Buscar por nombre o código...',

    'status' => 'Estado',
    'statuses' => [
        'all' => 'Todos',
        'enabled' => 'Activo',
        'disabled' => 'Inactivo',
    ],

    'default' => 'Por defecto',
    'make_default' => 'Poner por defecto',
    'enable' => 'Activar',
    'disable' => 'Desactivar',

    'updated' => 'Idioma actualizado',
    'default_updated' => 'Idioma por defecto actualizado',

    'column' => [
        'language' => 'Idioma',
        'status' => 'Estado',
        'coverage' => 'Traducido',
    ],

    'keys_translated' => ':translated de :total claves',
    'measured_against' => 'Medido contra :locale',

    'count_summary' => '{0} Ningún idioma|{1} 1 idioma|[2,*] :count idiomas',
    'incomplete_summary' => '{1} 1 idioma activo está incompleto y tira del respaldo para el resto|[2,*] :count idiomas activos están incompletos y tiran del respaldo para el resto',

    'empty' => 'Todavía no hay idiomas',
    'empty_hint' => 'Siembra la tabla de idiomas, o añade una fila por cada locale que sirva este producto.',

];
