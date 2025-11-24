<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña proporcionada es incorrecta.',
    'throttle' => 'Demasiados intentos de inicio de sesión. Por favor intente de nuevo en :seconds segundos.',
    'unauthorized' => 'No estás autorizado para acceder a este recurso.',

    // Cambio Forzado de Contraseña
    'change_password' => 'Cambia Tu Contraseña',
    'change_password_required' => 'Por razones de seguridad, debes cambiar tu contraseña antes de continuar.',
    'password_change_required' => 'Cambio de Contraseña Requerido',
    'account_created_by_admin' => 'Tu cuenta fue creada por un administrador. Por favor crea tu propia contraseña segura.',
    'current_password' => 'Contraseña Actual',
    'new_password' => 'Nueva Contraseña',
    'confirm_new_password' => 'Confirmar Nueva Contraseña',
    'password_requirements' => 'Debe tener al menos 8 caracteres y ser diferente de la contraseña actual',
    'logout_instead' => 'Cerrar Sesión',
    'current_password_incorrect' => 'La contraseña actual es incorrecta.',
    'password_changed' => 'Contraseña Cambiada',
    'password_changed_success' => 'Tu contraseña ha sido actualizada exitosamente. Ahora puedes acceder a la aplicación.',

    // Autenticación de Dos Factores
    '2fa' => [
        'title' => 'Autenticación de Dos Factores',
        'status_enabled' => 'La autenticación de dos factores está actualmente habilitada.',
        'status_disabled' => 'La autenticación de dos factores está actualmente deshabilitada.',
        'enable' => 'Habilitar',
        'disable' => 'Deshabilitar',
        'setup_title' => 'Configurar Autenticación de Dos Factores',
        'setup_step1' => 'Escanea el código QR a continuación con tu aplicación de autenticación:',
        'setup_step2' => 'Ingresa el código de 6 dígitos de tu aplicación de autenticación:',
        'manual_entry' => 'O ingresa este código manualmente:',
        'verification_code' => 'Código de Verificación',
        'confirm_password' => 'Confirmar Contraseña',
        'disable_confirmation_title' => 'Deshabilitar Autenticación de Dos Factores',
        'disable_confirmation_description' => '¿Estás seguro de que quieres deshabilitar la autenticación de dos factores? Esto hará que tu cuenta sea menos segura.',
        'recovery_codes_title' => 'Códigos de Recuperación',
        'recovery_codes_description' => 'Guarda estos códigos de recuperación en un lugar seguro. Pueden usarse para acceder a tu cuenta si pierdes acceso a tu dispositivo de autenticación.',
        'recovery_codes_warning' => 'Estos códigos solo se mostrarán una vez. Guárdalos de manera segura.',
        'view_recovery_codes' => 'Ver Códigos de Recuperación',
        'regenerate_codes' => 'Regenerar Códigos',
        'download_codes' => 'Descargar Códigos',
        'codes_saved' => 'He guardado mis códigos',
        'verification_title' => 'Autenticación de Dos Factores',
        'verification_description' => 'Por favor ingresa el código de autenticación de tu aplicación para continuar.',
        'recovery_description' => 'Por favor ingresa uno de tus códigos de recuperación para continuar.',
        'recovery_code' => 'Código de Recuperación',
        'use_recovery_code' => 'Usar código de recuperación',
        'use_authentication_code' => 'Usar código de autenticación',
        'verify' => 'Verificar',
        'back_to_login' => 'Volver al Inicio de Sesión',
        'invalid_code' => 'El código de verificación es inválido.',
        'enabled_title' => 'Autenticación de Dos Factores Habilitada',
        'enabled_description' => 'Tu cuenta ahora está protegida con autenticación de dos factores.',
        'disabled_title' => 'Autenticación de Dos Factores Deshabilitada',
        'disabled_description' => 'La autenticación de dos factores ha sido deshabilitada para tu cuenta.',
        'recovery_codes_regenerated' => 'Los códigos de recuperación se han regenerado exitosamente.',
    ],

];