<?php

declare(strict_types=1);

return [

    'title' => 'Sesiones activas',
    'description' => 'Dónde tienes la sesión abierta. Cierra cualquiera que no reconozcas.',
    'description_admin' => 'Dónde tiene la sesión abierta esta persona. Cerrar una la saca de ese dispositivo.',

    'this_device' => 'Este dispositivo',
    'unknown_browser' => 'Navegador desconocido',
    'last_active' => 'activa :when',

    'revoke' => 'Cerrar sesión',
    'revoke_title' => 'Cerrar esta sesión',
    'revoke_confirm' => '¿Cerrar la sesión en :device desde :ip?',
    'revoke_others' => 'Cerrar las demás sesiones',
    'revoke_others_confirm' => '¿Cerrar todas las sesiones salvo la que estás usando ahora mismo?',
    'revoke_all' => 'Cerrar todas las sesiones',
    'revoke_all_confirm' => '¿Cerrar todas las sesiones de esta persona, incluida la que puede estar usando?',
    'revoke_delay_notice' => 'Una sesión se cierra en su siguiente petición, que suele ser inmediata pero no está garantizado.',

    'revoked' => 'Sesión cerrada.',
    'revoked_many' => '{0} No había otras sesiones abiertas.|{1} :count sesión cerrada.|[2,*] :count sesiones cerradas.',
    'revoked_notice' => 'Esta sesión se ha cerrado desde otro dispositivo.',

    'empty_title' => 'No hay otras sesiones',
    'empty_description' => 'Este es el único dispositivo con la sesión abierta ahora mismo.',

    'console' => [
        'pruned' => 'Eliminados :count registros de sesión con más de :days días.',
    ],

];
