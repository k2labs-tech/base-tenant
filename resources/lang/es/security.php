<?php

declare(strict_types=1);

return [

    'title' => 'Seguridad',
    'description' => 'Las reglas que se aplican a todo el mundo en esta cuenta.',
    'saved' => 'Política de seguridad guardada.',

    'two_factor' => [
        'title' => 'Doble factor',
        'description' => 'Exigir un segundo factor a todos los miembros de la cuenta.',
        'require' => 'Exigir doble factor',
        'grace_hours' => 'Periodo de gracia (horas)',
        'grace_hint' => 'Cuánto tiempo tienen los miembros para configurarlo antes de que se les obligue.',
        'affected' => '{1} :count miembro todavía no tiene segundo factor y se le pedirá que lo configure.|[2,*] :count miembros todavía no tienen segundo factor y se les pedirá que lo configuren.',
    ],

    'email_domains' => [
        'title' => 'Dominios de email permitidos',
        'description' => 'Limita a quién se puede invitar a esta cuenta.',
        'label' => 'Dominios',
        'hint' => 'Uno por línea. Vacío permite cualquier dirección.',
    ],

    'ip' => [
        'title' => 'Lista blanca de IP',
        'description' => 'Limita desde dónde se puede acceder a esta cuenta.',
        'mode' => 'Modo',
        'modes' => [
            'off' => 'Desactivada',
            'off_hint' => 'Se permite cualquier dirección.',
            'warn' => 'Sólo avisar',
            'warn_hint' => 'Registra lo que se bloquearía, sin bloquear nada. Empieza por aquí.',
            'enforce' => 'Bloquear',
            'enforce_hint' => 'Rechaza las peticiones que vengan de fuera de la lista.',
        ],
        'label' => 'Direcciones permitidas',
        'hint' => 'Una por línea. Direcciones sueltas o rangos CIDR.',
        'your_address' => 'Esta petición viene de :ip.',
        'add_mine' => 'Añadirla',
    ],

    'session' => [
        'title' => 'Sesiones',
        'description' => 'Cuánto tiempo sigue abierta una sesión inactiva.',
        'timeout' => 'Inactividad máxima (minutos)',
        'timeout_hint' => '0 deja el valor por defecto de la instalación.',
    ],

    'two_factor_required' => 'Esta cuenta exige doble factor. Configúralo para continuar.',
    'ip_blocked' => 'No se puede acceder a esta cuenta desde :ip.',
    'ip_warn_logged' => 'Una petición desde :ip se habría bloqueado por la lista blanca de IP.',
    'session_expired' => 'Tu sesión ha terminado por inactividad.',
    'email_domain_not_allowed' => 'Esta cuenta sólo acepta direcciones de: :domains.',

    'errors' => [
        'invalid_entries' => 'No son direcciones ni rangos válidos: :entries',
        'would_lock_you_out' => 'Bloquear con esta lista te dejaría fuera: tu dirección (:ip) no está en ella. Añádela primero.',
    ],

];
