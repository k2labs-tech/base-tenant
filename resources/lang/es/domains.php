<?php

declare(strict_types=1);

return [

    'title' => 'Dominios',
    'description' => 'Dónde se sirve tu cuenta: un subdominio nuestro, o un dominio tuyo.',
    'currently_served_at' => 'Ahora mismo se sirve en',

    'primary' => 'Principal',

    'subdomain' => [
        'title' => 'Subdominio',
        'description' => 'Tu dirección bajo nuestro dominio. Disponible en cuanto lo guardas.',
        'label' => 'Subdominio',
        'placeholder' => 'tu-empresa',
        'hint' => 'Letras, números y guiones. Entre 3 y 63 caracteres, y no puede empezar ni acabar en guion.',
    ],

    'custom' => [
        'title' => 'Tu propio dominio',
        'description' => 'Apunta un dominio tuyo a tu cuenta. Se sirve en cuanto demuestras que es tuyo.',
        'label' => 'Dominio',
        'add' => 'Añadir dominio',
        'verify' => 'Verificar',
        'make_primary' => 'Hacer principal',
        'remove' => 'Eliminar',
        'remove_title' => 'Eliminar dominio',
        'remove_confirm' => '¿Eliminar :hostname? Dejará de servir tu cuenta de inmediato.',
        'instructions' => 'Publica este registro TXT en tu DNS y pulsa Verificar. Propagarse puede tardar unos minutos.',
        'cname_hint' => 'Apunta también el dominio aquí con un CNAME a :target.',
        'empty_title' => 'Todavía no hay dominios',
        'empty_description' => 'Añade un dominio tuyo para servir la cuenta desde él.',
    ],

    'record' => [
        'type' => 'Tipo',
        'host' => 'Host',
        'value' => 'Valor',
    ],

    'statuses' => [
        'pending' => 'Pendiente de verificar',
        'verified' => 'Verificado',
        'failed' => 'Sin verificar',
    ],

    'subdomain_saved' => 'Subdominio actualizado.',
    'domain_added' => 'Dominio añadido. Publica el registro DNS para verificarlo.',
    'domain_verified' => 'Dominio verificado.',
    'domain_not_verified' => 'El registro todavía no se ve. Los cambios de DNS pueden tardar unos minutos.',
    'domain_removed' => 'Dominio eliminado.',
    'primary_updated' => 'Dominio principal actualizado.',

    'errors' => [
        'subdomain_invalid' => 'Un subdominio usa letras, números y guiones, entre :min y :max caracteres, y no puede empezar ni acabar en guion.',
        'reserved' => '«:subdomain» está reservado y no se puede usar.',
        'subdomain_taken' => '«:subdomain» ya está ocupado.',
        'hostname_invalid' => '«:hostname» no es un dominio válido.',
        'hostname_taken' => '«:hostname» ya está registrado.',
        'hostname_is_central' => '«:hostname» pertenece a este producto y no se puede reclamar.',
        'too_many' => 'Puedes registrar hasta :max dominios.',
        'record_not_found' => 'No se ha encontrado el registro de verificación en el DNS.',
        'account_gone' => 'La cuenta a la que pertenecía este dominio ya no existe.',
        'custom_disabled' => 'Los dominios propios no están disponibles en esta instalación.',
        'subdomains_disabled' => 'Los subdominios no están disponibles en esta instalación.',
    ],

    'console' => [
        'nothing_due' => 'No hay dominios pendientes de verificar.',
        'check_failed' => 'No se pudo comprobar :hostname: :error',
        'custom_disabled' => 'Los dominios propios están apagados; no hay nada que verificar.',
        'now_verified' => ':hostname ya está verificado.',
        'not_verified' => 'No se ha podido verificar :hostname.',
        'summary' => 'Verificados: :verified · Sin verificar: :failed',
    ],

];
