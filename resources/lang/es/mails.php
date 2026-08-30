<?php

return [
    // Email subjects
    'welcome_subject' => 'Bienvenido a :app_name',
    'password_reset_subject' => 'Notificación de Restablecimiento de Contraseña',
    'email_verification_subject' => 'Verifica tu Dirección de Correo Electrónico',
    'account_activated_subject' => 'Tu Cuenta ha sido Activada',
    'invoice_subject' => 'Factura #:invoice_number',

    // Email greetings
    'greeting' => 'Hola :name',
    'greeting_generic' => 'Hola',
    'farewell' => 'Saludos',
    'regards' => 'Saludos cordiales',
    'best_regards' => 'Mejores deseos',

    // Welcome email
    'welcome' => [
        'title' => 'Bienvenido a :app_name',
        'intro' => 'Gracias por registrarte en nuestra plataforma. Estamos emocionados de tenerte con nosotros.',
        'get_started' => 'Aquí está cómo comenzar:',
        'step_1' => 'Completa tu perfil',
        'step_2' => 'Explora las características',
        'step_3' => 'Invita a los miembros del equipo',
        'action' => 'Ir al Panel de Control',
        'questions' => 'Si tienes alguna pregunta, no dudes en contactarnos.',
    ],

    // Password reset email
    'password_reset' => [
        'intro' => 'Estás recibiendo este correo porque recibimos una solicitud de restablecimiento de contraseña para tu cuenta.',
        'action' => 'Restablecer Contraseña',
        'expire' => 'Este enlace de restablecimiento de contraseña expirará en :count minutos.',
        'no_action' => 'Si no solicitaste un restablecimiento de contraseña, no se requiere ninguna acción adicional.',
    ],

    // Email verification
    'email_verification' => [
        'intro' => 'Por favor haz clic en el botón a continuación para verificar tu dirección de correo electrónico.',
        'action' => 'Verificar Dirección de Correo',
        'no_action' => 'Si no creaste una cuenta, no se requiere ninguna acción adicional.',
    ],

    // Common email content
    'trouble_clicking' => 'Si tienes problemas haciendo clic en el botón ":action", copia y pega la URL a continuación en tu navegador web:',
    'all_rights_reserved' => 'Todos los derechos reservados.',
    'unsubscribe' => 'Cancelar suscripción',
    'preferences' => 'Preferencias de correo',
    'view_in_browser' => 'Ver en el navegador',

    // Invitación de usuario
    'user_invite' => [
        'title' => '¡Te han invitado a :app!',
        'body' => '¡Te han invitado a unirte a :app! Haz clic en el enlace de abajo para crear tu cuenta y empezar.',
        'button' => 'Aceptar invitación',
        'thanks_message' => '¡Gracias por usar :app!',
    ],

    // Footer
    'footer' => [
        'company' => 'Tu Empresa',
        'address' => 'Tu dirección',
        'phone' => 'Teléfono: :phone',
        'email' => 'Correo: :email',
        'website' => 'Sitio web: :website',
    ],
];
