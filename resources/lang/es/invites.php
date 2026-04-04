<?php

return [
    'title' => 'Invitar Usuarios',
    'description' => 'Invita usuarios a unirse a tu cuenta. Recibirán un email con un enlace de registro.',
    'emails_label' => 'Direcciones de email',
    'emails_placeholder' => "usuario1@ejemplo.com, usuario2@ejemplo.com\nusuario3@ejemplo.com",
    'emails_help' => 'Separa múltiples emails con comas, punto y coma, o saltos de línea.',
    'send_button' => 'Enviar Invitaciones',
    'list_title' => 'Invitaciones Enviadas',
    'no_invitations' => 'No se han enviado invitaciones todavía.',
    'invitations_sent' => 'Invitaciones Enviadas',
    'results' => ':sent invitaciones enviadas, :skipped omitidas.',
    'invalid_emails' => ':count emails inválidos ignorados.',
    'error' => 'Error',
    'no_account' => 'No se encontró contexto de cuenta.',
    'email_mismatch' => 'El email debe coincidir con el de la invitación.',
    'token_already_used' => 'Esta invitación ya ha sido utilizada. Contacta con el administrador de tu cuenta para una nueva invitación.',

    'table' => [
        'email' => 'Email',
        'status' => 'Estado',
        'sent_at' => 'Enviada',
        'used_at' => 'Usada',
    ],

    'status' => [
        'pending' => 'Pendiente',
        'used' => 'Registrado',
    ],

    'email_subject' => 'Has sido invitado a :account',
    'email_line1' => 'Has sido invitado a unirte a :account. Haz clic en el botón para crear tu cuenta.',
    'email_action' => 'Registrarse',
    'email_line2' => 'Si no esperabas esta invitación, puedes ignorar este email.',
];
