<?php

declare(strict_types=1);

return [

    'title' => 'Entrar sin contraseña',
    'subtitle' => 'Te enviamos por email un enlace que te deja dentro.',
    'send' => 'Enviarme un enlace',
    'use_password' => 'Entrar con contraseña',
    'back_to_login' => 'Volver a entrar',

    'sent_title' => 'Mira tu correo',
    'sent_body' => 'Si esa dirección pertenece a una cuenta, el enlace ya va de camino. Sirve una sola vez y caduca en :minutes minutos.',

    'link_invalid' => 'Ese enlace ya se ha usado o ha caducado. Pide otro.',
    'throttled' => 'Demasiados enlaces pedidos. Inténtalo de nuevo en :seconds segundos.',

    'mail' => [
        'subject' => 'Tu enlace de acceso a :app',
        'greeting' => 'Hola',
        'line' => 'Usa el botón de abajo para entrar. Sirve una sola vez.',
        'action' => 'Entrar',
        'expiry' => 'El enlace caduca en :minutes minutos.',
        'ignore' => 'Si no lo has pedido tú, puedes ignorar este correo: no ha cambiado nada.',
    ],

    'console' => [
        'pruned' => 'Eliminados :count enlaces de acceso con más de :days días.',
    ],

];
